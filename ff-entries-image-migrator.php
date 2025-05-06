<?php
/**
 * Plugin Name: Fluent Forms Entries Image Migrator
 * Description: Processes images from Fluent Forms submissions and adds them to the WordPress media library.
 * Version: 1.0.0
 * Author: John Lomat
 * Author URI: https://johnlomat.vercel.app/
 * Text Domain: ff-entries-image-migrator
 * Requires PHP: 7.2
 * Requires at least: 5.0
 * 
 * @package FluentFormsEntriesImageMigrator
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'FF_IMAGE_PROCESSOR_VERSION', '1.0.0' );
define( 'FF_IMAGE_PROCESSOR_PLUGIN_FILE', __FILE__ );
define( 'FF_IMAGE_PROCESSOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FF_IMAGE_PROCESSOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load the image processor class.
require_once FF_IMAGE_PROCESSOR_PLUGIN_DIR . 'includes/class-ff-image-processor.php';

/**
 * Initialize the plugin.
 *
 * @return void
 */
function ff_image_processor_init() {
	// Check if Fluent Forms is active.
	if ( ! class_exists( 'FluentForm\App\Models\Form' ) ) {
		// Add admin notice if Fluent Forms is not active.
		add_action( 'admin_notices', 'ff_image_processor_admin_notice' );
		return;
	}
	
	// Initialize our processor.
	$ff_image_processor = new FF_Image_Processor();
	$ff_image_processor->init();
}
add_action( 'plugins_loaded', 'ff_image_processor_init' );

/**
 * Admin notice for Fluent Forms dependency.
 *
 * @return void
 */
function ff_image_processor_admin_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Fluent Forms Entries Image Migrator requires Fluent Forms plugin to be installed and activated.', 'ff-entries-image-migrator' ); ?></p>
	</div>
	<?php
}

/**
 * Activation hook.
 *
 * @return void
 */
function ff_image_processor_activate() {
	// Add any activation tasks here if needed.
}
register_activation_hook( __FILE__, 'ff_image_processor_activate' );

/**
 * Deactivation hook.
 *
 * @return void
 */
function ff_image_processor_deactivate() {
	// Add any cleanup tasks here if needed.
}
register_deactivation_hook( __FILE__, 'ff_image_processor_deactivate' );
