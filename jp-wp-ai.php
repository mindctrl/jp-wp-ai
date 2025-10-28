<?php
/**
 * Plugin Name: JP WP AI
 * Description: AI Experimentation Plugin
 * Version: 1.0.0
 * Author: John Parris and AI.
 * Requires Plugins: ai
 *
 * @package JP\WP_AI
 */

namespace JP\WP_AI;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'JP_WP_AI_VERSION', '1.0.0' );
define( 'JP_WP_AI_FILE', __FILE__ );
define( 'JP_WP_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'JP_WP_AI_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load the plugin.
 */
function load() {
	// Check if AI plugin is active.
	if ( ! class_exists( 'WordPress\AI\Feature_Registry' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\display_dependency_notice' );
		return;
	}

	// Register our features with the AI plugin.
	add_action( 'ai_register_features', __NAMESPACE__ . '\register_features' );

	// Initialize settings page.
	Admin\Settings_Page::register();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\load' );

/**
 * Displays admin notice about missing AI plugin dependency.
 *
 * @since 1.0.0
 */
function display_dependency_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			esc_html_e( 'JP WP AI requires the AI plugin to be installed and activated.', 'jp-wp-ai' );
			?>
		</p>
	</div>
	<?php
}

/**
 * Registers custom features with the AI plugin.
 *
 * @since 1.0.0
 *
 * @param \WordPress\AI\Feature_Registry $registry The feature registry instance.
 */
function register_features( $registry ): void {
	// Load feature classes.
	require_once JP_WP_AI_DIR . 'includes/Features/Alt_Text_Generator/Alt_Text_Generator.php';
	require_once JP_WP_AI_DIR . 'includes/Features/Content_Summarizer/Content_Summarizer.php';
	require_once JP_WP_AI_DIR . 'includes/Features/Content_Translator/Content_Translator.php';

	// Register features.
	$registry->register_feature( new Features\Alt_Text_Generator\Alt_Text_Generator() );
	$registry->register_feature( new Features\Content_Summarizer\Content_Summarizer() );
	$registry->register_feature( new Features\Content_Translator\Content_Translator() );
}

// Load required classes.
require_once JP_WP_AI_DIR . 'includes/Services/OpenAI_Client.php';
require_once JP_WP_AI_DIR . 'includes/Admin/Settings_Page.php';