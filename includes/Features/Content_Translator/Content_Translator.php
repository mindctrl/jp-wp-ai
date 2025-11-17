<?php
/**
 * Content Translator Feature
 *
 * Translates post content into multiple languages using AI.
 *
 * @package JP\WP_AI\Features\Content_Translator
 */

namespace JP\WP_AI\Features\Content_Translator;

use WordPress\AI\Abstracts\Abstract_Experiment;
use JP\WP_AI\Services\OpenAI_Client;

/**
 * Content Translator experiment implementation.
 *
 * @since 1.0.0
 */
class Content_Translator extends Abstract_Experiment {
	/**
	 * Supported languages with ISO 639-1 codes.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private const SUPPORTED_LANGUAGES = array(
		'es' => 'Spanish',
		'fr' => 'French',
		'de' => 'German',
		'ja' => 'Japanese',
		'zh' => 'Chinese',
		'pt' => 'Portuguese',
		'it' => 'Italian',
		'ru' => 'Russian',
		'ar' => 'Arabic',
		'hi' => 'Hindi',
	);

	/**
	 * Loads experiment metadata.
	 *
	 * @since 1.0.0
	 *
	 * @return array{id: string, label: string, description: string} Experiment metadata.
	 */
	protected function load_experiment_metadata(): array {
		return array(
			'id'          => 'jp-content-translator',
			'label'       => __( 'Content Translator', 'jp-wp-ai' ),
			'description' => __( 'Translate posts into multiple languages with context awareness.', 'jp-wp-ai' ),
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
			<?php esc_html_e( 'Provides a block that allows site visitors to translate page content into multiple languages using AI.', 'jp-wp-ai' ); ?>
		</p>
		<p class="description">
			<strong><?php esc_html_e( 'Supported Languages:', 'jp-wp-ai' ); ?></strong>
			<?php echo esc_html( implode( ', ', self::SUPPORTED_LANGUAGES ) ); ?>
		</p>
		<p class="description">
			<em><?php esc_html_e( 'Add the Content Translator block to any post or page to enable front-end translation.', 'jp-wp-ai' ); ?></em>
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

		// Register the block immediately (init has already fired by the time this is called).
		$this->register_block();

		// Add AJAX handlers for both logged-in and non-logged-in users.
		add_action( 'wp_ajax_ai_translate_content', array( $this, 'ajax_translate_content' ) );
		add_action( 'wp_ajax_nopriv_ai_translate_content', array( $this, 'ajax_translate_content' ) );

		// Enqueue front-end scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Registers the content translation ability.
	 *
	 * @since 1.0.0
	 */
	public function register_ability(): void {
		wp_register_ability(
			'ai/translate-content',
			array(
				'label'               => __( 'Translate Content', 'jp-wp-ai' ),
				'description'         => __( 'Translates post content into a target language using AI.', 'jp-wp-ai' ),
				'category'            => 'content-creation',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'         => array(
							'type'        => 'string',
							'description' => 'The post title to translate.',
						),
						'content'       => array(
							'type'        => 'string',
							'description' => 'The post content to translate.',
						),
						'excerpt'       => array(
							'type'        => 'string',
							'description' => 'The post excerpt to translate.',
						),
						'target_lang'   => array(
							'type'        => 'string',
							'description' => 'Target language code (ISO 639-1).',
						),
						'source_lang'   => array(
							'type'        => 'string',
							'description' => 'Source language code (optional).',
						),
					),
					'required'   => array( 'content', 'target_lang' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'title'   => array(
							'type'        => 'string',
							'description' => 'The translated title.',
						),
						'content' => array(
							'type'        => 'string',
							'description' => 'The translated content.',
						),
						'excerpt' => array(
							'type'        => 'string',
							'description' => 'The translated excerpt.',
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
	 * Executes the content translation ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Translated content or error.
	 */
	public function execute_ability( array $input ) {
		$target_lang = $input['target_lang'] ?? '';
		$source_lang = $input['source_lang'] ?? 'auto';

		if ( empty( $target_lang ) ) {
			return new \WP_Error(
				'missing_target_lang',
				__( 'Target language is required.', 'jp-wp-ai' )
			);
		}

		// Validate target language.
		if ( ! isset( self::SUPPORTED_LANGUAGES[ $target_lang ] ) ) {
			return new \WP_Error(
				'invalid_language',
				__( 'Unsupported target language.', 'jp-wp-ai' )
			);
		}

		$content_to_translate = array(
			'title'   => $input['title'] ?? '',
			'content' => $input['content'] ?? '',
			'excerpt' => $input['excerpt'] ?? '',
		);

		// Translate content using OpenAI.
		$result = OpenAI_Client::translate_content( $content_to_translate, $target_lang, $source_lang );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result;
	}

	/**
	 * Checks if user has permission to translate content.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user can read content.
	 */
	public function check_permission(): bool {
		// Allow any user (including non-logged-in) to translate content.
		return true;
	}

	/**
	 * Registers the Content Translator block.
	 *
	 * @since 1.0.0
	 */
	public function register_block(): void {
		if ( ! OpenAI_Client::has_api_key() ) {
			return;
		}

		// Register the block type - let WordPress infer everything from block.json.
		register_block_type(
			JP_WP_AI_DIR . 'build/content-translator',
			array(
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Renders the Content Translator block.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attributes Block attributes.
	 * @return string Block HTML.
	 */
	public function render_block( array $attributes ): string {
		// Get current post.
		$post = get_post();

		if ( ! $post ) {
			return '';
		}

		// Enqueue front-end script.
		wp_enqueue_script( 'jp-wp-ai-content-translator-view' );
		wp_enqueue_style( 'jp-wp-ai-content-translator-style' );

		$languages = self::SUPPORTED_LANGUAGES;

		ob_start();
		?>
		<div class="wp-block-jp-wp-ai-content-translator" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
			<div class="content-translator-controls">
				<label for="content-translator-language-select">
					<?php esc_html_e( 'Translate this page:', 'jp-wp-ai' ); ?>
				</label>
				<select id="content-translator-language-select" class="content-translator-language-select">
					<option value=""><?php esc_html_e( 'Select a language', 'jp-wp-ai' ); ?></option>
					<?php foreach ( $languages as $code => $name ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>">
							<?php echo esc_html( $name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="content-translator-button" id="content-translator-translate-btn">
					<?php esc_html_e( 'Translate', 'jp-wp-ai' ); ?>
				</button>
				<button type="button" class="content-translator-button content-translator-button-secondary" id="content-translator-original-btn" style="display: none;">
					<?php esc_html_e( 'Show Original', 'jp-wp-ai' ); ?>
				</button>
			</div>
			<div class="content-translator-status" id="content-translator-status"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handles AJAX request to translate content.
	 *
	 * @since 1.0.0
	 */
	public function ajax_translate_content(): void {
		check_ajax_referer( 'ai-translate-content', 'nonce' );

		$post_id     = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$target_lang = isset( $_POST['target_lang'] ) ? sanitize_text_field( wp_unslash( $_POST['target_lang'] ) ) : '';

		if ( ! $post_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid post ID.', 'jp-wp-ai' ) )
			);
		}

		if ( empty( $target_lang ) || ! isset( self::SUPPORTED_LANGUAGES[ $target_lang ] ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid target language.', 'jp-wp-ai' ) )
			);
		}

		// Check for cached translation.
		$cache_key        = '_ai_translations_' . $target_lang;
		$cached_translation = get_post_meta( $post_id, $cache_key, true );

		if ( ! empty( $cached_translation ) && is_array( $cached_translation ) ) {
			wp_send_json_success(
				array(
					'translation' => $cached_translation,
					'cached'      => true,
				)
			);
		}

		// Get post data.
		$post = get_post( $post_id );

		if ( ! $post ) {
			wp_send_json_error(
				array( 'message' => __( 'Post not found.', 'jp-wp-ai' ) )
			);
		}

		// Execute the ability.
		$ability = wp_get_ability( 'ai/translate-content' );

		if ( ! $ability ) {
			wp_send_json_error(
				array( 'message' => __( 'Content translation ability not found.', 'jp-wp-ai' ) )
			);
		}

		$result = $ability->execute(
			array(
				'title'       => $post->post_title,
				'content'     => apply_filters( 'the_content', $post->post_content ),
				'excerpt'     => $post->post_excerpt,
				'target_lang' => $target_lang,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array( 'message' => $result->get_error_message() )
			);
		}

		// Cache the translation.
		$translation_data = array(
			'title'         => $result['title'],
			'content'       => $result['content'],
			'excerpt'       => $result['excerpt'],
			'translated_at' => time(),
		);

		update_post_meta( $post_id, $cache_key, $translation_data );

		wp_send_json_success(
			array(
				'translation' => $translation_data,
				'cached'      => false,
			)
		);
	}

	/**
	 * Enqueues front-end assets.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_frontend_assets(): void {
		if ( ! OpenAI_Client::has_api_key() ) {
			return;
		}

		// The viewScript in block.json will handle the script enqueuing automatically.
		// We just need to localize it with our AJAX data.
		if ( wp_script_is( 'jp-wp-ai-content-translator-view-script', 'registered' ) ) {
			wp_localize_script(
				'jp-wp-ai-content-translator-view-script',
				'aiContentTranslator',
				array(
					'nonce'   => wp_create_nonce( 'ai-translate-content' ),
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				)
			);
		}

		// Register front-end styles.
		wp_register_style(
			'jp-wp-ai-content-translator-style',
			plugins_url( 'src/content-translator/style.css', JP_WP_AI_FILE ),
			array(),
			JP_WP_AI_VERSION
		);
	}
}

