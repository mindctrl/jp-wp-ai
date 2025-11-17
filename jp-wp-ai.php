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
use WordPress\AI\Experiment_Registry;

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
	if ( ! class_exists( Experiment_Registry::class ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\display_dependency_notice' );
		return;
	}

	// Register our experiments with the AI plugin.
	add_action( 'ai_register_experiments', __NAMESPACE__ . '\register_experiments' );

	// Initialize settings page for API key configuration.
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
 * Registers custom experiments with the AI plugin.
 *
 * @since 1.0.0
 *
 * @param \WordPress\AI\Experiment_Registry $registry The experiment registry instance.
 */
function register_experiments( Experiment_Registry $registry ): void {
	// Load experiment classes.
	require_once JP_WP_AI_DIR . 'includes/Features/Alt_Text_Generator/Alt_Text_Generator.php';
	require_once JP_WP_AI_DIR . 'includes/Features/Content_Summarizer/Content_Summarizer.php';
	require_once JP_WP_AI_DIR . 'includes/Features/Content_Translator/Content_Translator.php';

	// Register our experiments.
	$registry->register_experiment( new Features\Alt_Text_Generator\Alt_Text_Generator() );
	$registry->register_experiment( new Features\Content_Summarizer\Content_Summarizer() );
	$registry->register_experiment( new Features\Content_Translator\Content_Translator() );
}

// Load required classes.
require_once JP_WP_AI_DIR . 'includes/Services/OpenAI_Client.php';
require_once JP_WP_AI_DIR . 'includes/Admin/Settings_Page.php';

// Register ability category.
add_action(
		'wp_abilities_api_categories_init',
		function () {
				wp_register_ability_category(
						'content-creation',
						array(
								'label'       => __( 'Content Creation', 'ai' ),
								'description' => __( 'AI-powered tools for creating and enhancing content.', 'ai' ),
						)
				);
		}
);
