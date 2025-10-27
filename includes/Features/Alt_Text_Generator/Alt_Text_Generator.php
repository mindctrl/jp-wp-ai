<?php
/**
 * Alt Text Generator Feature
 *
 * Generates descriptive alt text for images using AI.
 *
 * @package JP\WP_AI\Features\Alt_Text_Generator
 */

namespace JP\WP_AI\Features\Alt_Text_Generator;

use WordPress\AI\Abstracts\Abstract_Feature;
use JP\WP_AI\Services\OpenAI_Client;

/**
 * Alt Text Generator feature implementation.
 *
 * @since 1.0.0
 */
class Alt_Text_Generator extends Abstract_Feature {
	/**
	 * Loads feature metadata.
	 *
	 * @since 1.0.0
	 *
	 * @return array{id: string, label: string, description: string} Feature metadata.
	 */
	protected function load_feature_metadata(): array {
		return array(
			'id'          => 'alt-text-generator',
			'label'       => __( 'Alt Text Generator', 'jp-wp-ai' ),
			'description' => __( 'Automatically generate descriptive alt text for images using AI.', 'jp-wp-ai' ),
		);
	}

	/**
	 * Registers the feature hooks.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		// Register the ability.
		add_action( 'wp_abilities_api_init', array( $this, 'register_ability' ) );

		// Add Media Library UI.
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_media_library_field' ), 10, 2 );
		add_action( 'wp_ajax_ai_generate_alt_text', array( $this, 'ajax_generate_alt_text' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_library_scripts' ) );

		// Enqueue scripts for block editor.
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
	}

	/**
	 * Registers the alt text generation ability.
	 *
	 * @since 1.0.0
	 */
	public function register_ability(): void {
		wp_register_ability(
			'ai/generate-alt-text',
			array(
				'label'               => __( 'Generate Alt Text', 'jp-wp-ai' ),
				'description'         => __( 'Generates descriptive alt text for an image using AI vision capabilities.', 'jp-wp-ai' ),
				'category'            => 'content-creation',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'image_url'     => array(
							'type'        => 'string',
							'description' => 'The URL of the image to analyze.',
							'format'      => 'uri',
						),
						'attachment_id' => array(
							'type'        => 'integer',
							'description' => 'The WordPress attachment ID (optional).',
						),
						'context'       => array(
							'type'        => 'string',
							'description' => 'Optional context about the image to improve accuracy.',
						),
					),
					'required'   => array( 'image_url' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'alt_text'   => array(
							'type'        => 'string',
							'description' => 'The generated alt text.',
						),
						'confidence' => array(
							'type'        => 'string',
							'description' => 'Confidence level of the generated text.',
							'enum'        => array( 'high', 'medium', 'low' ),
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
	 * Executes the alt text generation ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Generated alt text or error.
	 */
	public function execute_ability( array $input ) {
		$image_url = $input['image_url'] ?? '';
		$context   = $input['context'] ?? '';

		if ( empty( $image_url ) ) {
			return new \WP_Error(
				'missing_image_url',
				__( 'Image URL is required.', 'jp-wp-ai' )
			);
		}

		// Generate alt text using OpenAI.
		$result = OpenAI_Client::generate_alt_text( $image_url, $context );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// If attachment_id is provided, update the alt text.
		if ( ! empty( $input['attachment_id'] ) ) {
			$attachment_id = absint( $input['attachment_id'] );
			if ( $attachment_id > 0 ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $result['alt_text'] );
			}
		}

		return $result;
	}

	/**
	 * Checks if user has permission to generate alt text.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user can upload files.
	 */
	public function check_permission(): bool {
		return current_user_can( 'upload_files' );
	}

	/**
	 * Adds generate button to media library attachment fields.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $form_fields Array of attachment form fields.
	 * @param \WP_Post $post        Attachment post object.
	 * @return array Modified form fields.
	 */
	public function add_media_library_field( array $form_fields, \WP_Post $post ): array {
		if ( ! wp_attachment_is_image( $post->ID ) ) {
			return $form_fields;
		}

		if ( ! OpenAI_Client::has_api_key() ) {
			return $form_fields;
		}

		// Add button after the alt text field.
		if ( isset( $form_fields['image_alt'] ) ) {
			$form_fields['image_alt']['helps'] = sprintf(
				'<button type="button" class="button ai-generate-alt-text" data-attachment-id="%d">%s</button><span class="ai-alt-text-status"></span>',
				$post->ID,
				esc_html__( 'Generate Alt Text', 'jp-wp-ai' )
			);
		}

		return $form_fields;
	}

	/**
	 * Handles AJAX request to generate alt text.
	 *
	 * @since 1.0.0
	 */
	public function ajax_generate_alt_text(): void {
		check_ajax_referer( 'ai-generate-alt-text', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Insufficient permissions.', 'jp-wp-ai' ) ),
				403
			);
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid attachment ID.', 'jp-wp-ai' ) )
			);
		}

		// Get image URL.
		$image_url = wp_get_attachment_image_url( $attachment_id, 'large' );

		if ( ! $image_url ) {
			wp_send_json_error(
				array( 'message' => __( 'Could not retrieve image URL.', 'jp-wp-ai' ) )
			);
		}

		// Get post title as context.
		$post    = get_post( $attachment_id );
		$context = $post->post_title ?? '';

		// Execute the ability.
		$ability = wp_get_ability( 'ai/generate-alt-text' );

		if ( ! $ability ) {
			wp_send_json_error(
				array( 'message' => __( 'Alt text generation ability not found.', 'jp-wp-ai' ) )
			);
		}

		$result = $ability->execute(
			array(
				'image_url'     => $image_url,
				'attachment_id' => $attachment_id,
				'context'       => $context,
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
	 * Enqueues media library scripts.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_media_library_scripts( string $hook ): void {
		// Only load on media upload pages.
		if ( ! in_array( $hook, array( 'upload.php', 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		if ( ! OpenAI_Client::has_api_key() ) {
			return;
		}

		wp_enqueue_script(
			'jp-wp-ai-alt-text-media-library',
			plugins_url( 'src/alt-text-generator/media-library.js', JP_WP_AI_FILE ),
			array( 'jquery' ),
			JP_WP_AI_VERSION,
			true
		);

		wp_localize_script(
			'jp-wp-ai-alt-text-media-library',
			'aiAltTextGenerator',
			array(
				'nonce' => wp_create_nonce( 'ai-generate-alt-text' ),
			)
		);
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

		$asset_file = JP_WP_AI_DIR . 'build/alt-text-generator.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			'jp-wp-ai-alt-text-generator',
			plugins_url( 'build/alt-text-generator.js', JP_WP_AI_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'jp-wp-ai-alt-text-generator',
			'aiAltTextGenerator',
			array(
				'nonce' => wp_create_nonce( 'ai-generate-alt-text' ),
			)
		);
	}
}
