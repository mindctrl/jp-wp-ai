<?php
/**
 * Admin Settings Page
 *
 * Provides settings interface for JP WP AI plugin configuration.
 *
 * @package JP\WP_AI\Admin
 */

namespace JP\WP_AI\Admin;

use JP\WP_AI\Services\OpenAI_Client;

/**
 * Manages the JP WP AI plugin settings page.
 *
 * @since 1.0.0
 */
class Settings_Page {
	/**
	 * Registers the settings page and related hooks.
	 *
	 * @since 1.0.0
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'wp_ajax_jp_wp_ai_test_openai_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Adds the settings page to the WordPress admin menu.
	 *
	 * @since 1.0.0
	 */
	public static function add_settings_page(): void {
		add_options_page(
			__( 'JP WP AI Settings', 'jp-wp-ai' ),
			__( 'JP WP AI', 'jp-wp-ai' ),
			'manage_options',
			'jp-wp-ai-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Registers plugin settings.
	 *
	 * @since 1.0.0
	 */
	public static function register_settings(): void {
		register_setting(
			'jp_wp_ai_settings',
			'jp_wp_ai_openai_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		add_settings_section(
			'jp_wp_ai_openai_section',
			__( 'OpenAI Configuration', 'jp-wp-ai' ),
			array( __CLASS__, 'render_openai_section' ),
			'jp-wp-ai-settings'
		);

		add_settings_field(
			'jp_wp_ai_openai_api_key',
			__( 'API Key', 'jp-wp-ai' ),
			array( __CLASS__, 'render_api_key_field' ),
			'jp-wp-ai-settings',
			'jp_wp_ai_openai_section'
		);
	}

	/**
	 * Renders the OpenAI section description.
	 *
	 * @since 1.0.0
	 */
	public static function render_openai_section(): void {
		?>
		<p>
			<?php
			printf(
				/* translators: %s: OpenAI API keys URL */
				esc_html__( 'Configure your OpenAI API key to enable AI-powered features. You can obtain an API key from %s.', 'jp-wp-ai' ),
				'<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">OpenAI Platform</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Renders the API key field.
	 *
	 * @since 1.0.0
	 */
	public static function render_api_key_field(): void {
		$api_key = OpenAI_Client::get_api_key();
		$has_key = OpenAI_Client::has_api_key();
		?>
		<input
			type="password"
			id="jp_wp_ai_openai_api_key"
			name="jp_wp_ai_openai_api_key"
			value="<?php echo esc_attr( $api_key ?? '' ); ?>"
			class="regular-text"
			placeholder="sk-..."
		/>
		<button
			type="button"
			id="jp-wp-ai-test-connection"
			class="button button-secondary"
			<?php disabled( ! $has_key ); ?>
		>
			<?php esc_html_e( 'Test Connection', 'jp-wp-ai' ); ?>
		</button>
		<p class="description">
			<?php esc_html_e( 'Your OpenAI API key. This key is stored securely and never shared.', 'jp-wp-ai' ); ?>
		</p>
		<div id="jp-wp-ai-connection-status" style="margin-top: 10px;"></div>
		<?php
	}

	/**
	 * Renders the settings page.
	 *
	 * @since 1.0.0
	 */
	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle settings update message.
		if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_settings_error(
				'jp_wp_ai_messages',
				'jp_wp_ai_message',
				__( 'Settings saved.', 'jp-wp-ai' ),
				'updated'
			);
		}

		settings_errors( 'jp_wp_ai_messages' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<div class="notice notice-info">
				<p>
					<?php
					esc_html_e( 'This plugin provides experimental AI features for WordPress. Configure your AI provider below to get started.', 'jp-wp-ai' );
					?>
				</p>
				<p>
					<?php
					printf(
						/* translators: %s: Link to AI Experiments settings page */
						esc_html__( 'To enable or disable individual features, visit the %s page.', 'jp-wp-ai' ),
						'<a href="' . esc_url( admin_url( 'options-general.php?page=ai-experiments' ) ) . '">' . esc_html__( 'AI Experiments settings', 'jp-wp-ai' ) . '</a>'
					);
					?>
				</p>
			</div>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'jp_wp_ai_settings' );
				do_settings_sections( 'jp-wp-ai-settings' );
				submit_button( __( 'Save Settings', 'jp-wp-ai' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handles AJAX request to test OpenAI connection.
	 *
	 * @since 1.0.0
	 */
	public static function ajax_test_connection(): void {
		check_ajax_referer( 'jp-wp-ai-test-connection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Insufficient permissions.', 'jp-wp-ai' ) ),
				403
			);
		}

		$result = OpenAI_Client::test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array( 'message' => $result->get_error_message() )
			);
		}

		wp_send_json_success(
			array( 'message' => __( 'Connection successful! Your API key is working correctly.', 'jp-wp-ai' ) )
		);
	}

	/**
	 * Enqueues scripts for the settings page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_scripts( string $hook ): void {
		if ( 'settings_page_jp-wp-ai-settings' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'jp-wp-ai-admin-settings',
			plugins_url( 'assets/js/admin-settings.js', JP_WP_AI_FILE ),
			array( 'jquery' ),
			JP_WP_AI_VERSION,
			true
		);

		wp_localize_script(
			'jp-wp-ai-admin-settings',
			'jpWpAiSettings',
			array(
				'nonce'   => wp_create_nonce( 'jp-wp-ai-test-connection' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}
}

