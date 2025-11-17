<?php
/**
 * Content Summarizer Feature
 *
 * Generates concise summaries of post content using AI.
 *
 * @package JP\WP_AI\Features\Content_Summarizer
 */

namespace JP\WP_AI\Features\Content_Summarizer;

use WordPress\AI\Abstracts\Abstract_Experiment;
use JP\WP_AI\Services\OpenAI_Client;

/**
 * Content Summarizer experiment implementation.
 *
 * @since 1.0.0
 */
class Content_Summarizer extends Abstract_Experiment {
	/**
	 * Loads experiment metadata.
	 *
	 * @since 1.0.0
	 *
	 * @return array{id: string, label: string, description: string} Experiment metadata.
	 */
	protected function load_experiment_metadata(): array {
		return array(
			'id'          => 'jp-content-summarizer',
			'label'       => __( 'Content Summarizer', 'jp-wp-ai' ),
			'description' => __( 'Generate concise summaries of post content using AI.', 'jp-wp-ai' ),
		);
	}

	/**
	 * Renders experiment-specific settings fields.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_fields(): void {
		?>
		<p class="description">
			<?php esc_html_e( 'Adds AI-powered summarization capabilities to the block editor and classic editor, allowing you to generate concise summaries of your content.', 'jp-wp-ai' ); ?>
		</p>
		<p class="description">
			<strong><?php esc_html_e( 'Available in:', 'jp-wp-ai' ); ?></strong>
			<?php esc_html_e( 'Block editor sidebar, Classic editor meta box', 'jp-wp-ai' ); ?>
		</p>
		<?php
	}

	/**
	 * Registers the experiment.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		// Register the ability.
		add_action( 'wp_abilities_api_init', array( $this, 'register_ability' ) );

		// Add AJAX handler.
		add_action( 'wp_ajax_ai_summarize_content', array( $this, 'ajax_summarize_content' ) );

		// Enqueue scripts for block editor.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );

		// Add meta box for classic editor.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
	}

	/**
	 * Registers the content summarization ability.
	 *
	 * @since 1.0.0
	 */
	public function register_ability(): void {
		wp_register_ability(
			'ai/summarize-content',
			array(
				'label'               => __( 'Summarize Content', 'jp-wp-ai' ),
				'description'         => __( 'Generates a concise summary of the provided content using AI.', 'jp-wp-ai' ),
				'category'            => 'content-creation',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'content'    => array(
							'type'        => 'string',
							'description' => 'The content to summarize.',
						),
						'max_length' => array(
							'type'        => 'integer',
							'description' => 'Maximum length of summary in words.',
							'default'     => 50,
							'minimum'     => 10,
							'maximum'     => 200,
						),
					),
					'required'   => array( 'content' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'summary'    => array(
							'type'        => 'string',
							'description' => 'The generated summary.',
						),
						'word_count' => array(
							'type'        => 'integer',
							'description' => 'Word count of the summary.',
						),
					),
				),
				'execute_callback'    => array( $this, 'execute_ability' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'meta'                => array(
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes the content summarization ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Generated summary or error.
	 */
	public function execute_ability( array $input ) {
		$content    = $input['content'] ?? '';
		$max_length = $input['max_length'] ?? 50;

		if ( empty( $content ) ) {
			return new \WP_Error(
				'missing_content',
				__( 'Content is required.', 'jp-wp-ai' )
			);
		}

		// Generate summary using OpenAI.
		$result = OpenAI_Client::summarize_content( $content, $max_length );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result;
	}

	/**
	 * Checks if user has permission to summarize content.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user can edit posts.
	 */
	public function check_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Handles AJAX request to summarize content.
	 *
	 * @since 1.0.0
	 */
	public function ajax_summarize_content(): void {
		check_ajax_referer( 'ai-summarize-content', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Insufficient permissions.', 'jp-wp-ai' ) ),
				403
			);
		}

		$content    = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$max_length = isset( $_POST['max_length'] ) ? absint( $_POST['max_length'] ) : 50;

		if ( empty( $content ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No content provided.', 'jp-wp-ai' ) )
			);
		}

		// Execute the ability.
		$ability = wp_get_ability( 'ai/summarize-content' );

		if ( ! $ability ) {
			wp_send_json_error(
				array( 'message' => __( 'Content summarization ability not found.', 'jp-wp-ai' ) )
			);
		}

		$result = $ability->execute(
			array(
				'content'    => $content,
				'max_length' => $max_length,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array( 'message' => $result->get_error_message() )
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Enqueues block editor assets.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_block_editor_assets(): void {
		if ( ! OpenAI_Client::has_api_key() ) {
			return;
		}

		$asset_file = JP_WP_AI_DIR . 'build/content-summarizer.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			'jp-wp-ai-content-summarizer',
			plugins_url( 'build/content-summarizer.js', JP_WP_AI_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'jp-wp-ai-content-summarizer',
			'aiContentSummarizer',
			array(
				'nonce' => wp_create_nonce( 'ai-summarize-content' ),
			)
		);
	}

	/**
	 * Adds meta box for classic editor.
	 *
	 * @since 1.0.0
	 */
	public function add_meta_box(): void {
		if ( ! OpenAI_Client::has_api_key() ) {
			return;
		}

		// Only add meta box for classic editor (not block editor).
		$current_screen = get_current_screen();
		if ( $current_screen && $current_screen->is_block_editor() ) {
			return;
		}

		$post_types = get_post_types( array( 'public' => true ) );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'jp-wp-ai-content-summarizer',
				__( 'AI Content Summarizer', 'jp-wp-ai' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Renders the meta box content.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'ai-summarize-content', 'ai_summarize_nonce' );
		?>
		<div class="ai-summarizer-meta-box">
			<p>
				<label for="ai-summary-length">
					<?php esc_html_e( 'Summary length (words):', 'jp-wp-ai' ); ?>
				</label>
				<input
					type="number"
					id="ai-summary-length"
					min="10"
					max="200"
					value="50"
					style="width: 100%;"
				/>
			</p>
			<p>
				<button type="button" class="button button-primary" id="ai-generate-summary-classic">
					<?php esc_html_e( 'Generate Summary', 'jp-wp-ai' ); ?>
				</button>
			</p>
			<div id="ai-summary-result" style="margin-top: 10px; display: none;">
				<p><strong><?php esc_html_e( 'Generated Summary:', 'jp-wp-ai' ); ?></strong></p>
				<div id="ai-summary-text" style="padding: 10px; background: #f0f0f1; border-radius: 4px;"></div>
				<p style="margin-top: 10px;">
					<button type="button" class="button" id="ai-copy-summary">
						<?php esc_html_e( 'Copy to Clipboard', 'jp-wp-ai' ); ?>
					</button>
					<button type="button" class="button" id="ai-insert-summary">
						<?php esc_html_e( 'Insert at Top', 'jp-wp-ai' ); ?>
					</button>
				</p>
			</div>
			<div id="ai-summary-status"></div>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#ai-generate-summary-classic').on('click', function() {
				var $button = $(this);
				var $status = $('#ai-summary-status');
				var $result = $('#ai-summary-result');
				var content = '';
				
				// Get content from TinyMCE or textarea.
				if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
					content = tinymce.get('content').getContent();
				} else {
					content = $('#content').val();
				}

				if (!content) {
					$status.html('<div class="notice notice-error inline"><p><?php esc_html_e( 'Please add some content first.', 'jp-wp-ai' ); ?></p></div>');
					return;
				}

				$button.prop('disabled', true).text('<?php esc_html_e( 'Generating...', 'jp-wp-ai' ); ?>');
				$status.html('');
				$result.hide();

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'ai_summarize_content',
						nonce: '<?php echo esc_js( wp_create_nonce( 'ai-summarize-content' ) ); ?>',
						content: content,
						max_length: $('#ai-summary-length').val()
					},
					success: function(response) {
						if (response.success) {
							$('#ai-summary-text').text(response.data.summary);
							$result.show();
							$status.html('<div class="notice notice-success inline"><p><?php esc_html_e( 'Summary generated successfully!', 'jp-wp-ai' ); ?></p></div>');
						} else {
							$status.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
						}
					},
					error: function() {
						$status.html('<div class="notice notice-error inline"><p><?php esc_html_e( 'An error occurred. Please try again.', 'jp-wp-ai' ); ?></p></div>');
					},
					complete: function() {
						$button.prop('disabled', false).text('<?php esc_html_e( 'Generate Summary', 'jp-wp-ai' ); ?>');
					}
				});
			});

			$('#ai-copy-summary').on('click', function() {
				var summary = $('#ai-summary-text').text();
				navigator.clipboard.writeText(summary).then(function() {
					alert('<?php esc_html_e( 'Summary copied to clipboard!', 'jp-wp-ai' ); ?>');
				});
			});

			$('#ai-insert-summary').on('click', function() {
				var summary = $('#ai-summary-text').text();
				if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
					var editor = tinymce.get('content');
					editor.setContent('<p>' + summary + '</p>' + editor.getContent());
				} else {
					var $textarea = $('#content');
					$textarea.val(summary + '\n\n' + $textarea.val());
				}
				alert('<?php esc_html_e( 'Summary inserted at the top of your content!', 'jp-wp-ai' ); ?>');
			});
		});
		</script>
		<?php
	}
}

