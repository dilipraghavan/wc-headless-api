<?php
/**
 * Plugin Name: WC Headless API
 * Description: Custom REST API endpoints for headless WooCommerce with JWT authentication.
 * Version: 1.0.0
 * Author: Dilip Developer
 * Author URI: https://wpshiftstudio.com
 * Text Domain: wc-headless-api
 * Requires at least: 5.8
 * Requires PHP: 8.0
 * WC requires at least: 6.0
 *
 * @package WCHeadlessAPI
 */

declare(strict_types=1);

namespace WCHeadlessAPI;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'WCHA_VERSION', '1.0.0' );
define( 'WCHA_PLUGIN_FILE', __FILE__ );
define( 'WCHA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCHA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoloader.
if ( file_exists( WCHA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once WCHA_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Check if WooCommerce is active.
 *
 * @return bool
 */
function wcha_is_woocommerce_active(): bool {
	return class_exists( 'WooCommerce' );
}

/**
 * Display admin notice if WooCommerce is not active.
 *
 * @return void
 */
function wcha_woocommerce_missing_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			esc_html_e(
				'WC Headless API requires WooCommerce to be installed and activated.',
				'wc-headless-api'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function wcha_init(): void {
	if ( ! wcha_is_woocommerce_active() ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\wcha_woocommerce_missing_notice' );
		return;
	}

	// Boot the plugin.
	$plugin = new Plugin();
	$plugin->init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\wcha_init' );

/**
 * Plugin activation hook.
 *
 * @return void
 */
function wcha_activate(): void {
	// Generate JWT secret if not exists.
	if ( ! get_option( 'wcha_jwt_secret' ) ) {
		update_option( 'wcha_jwt_secret', wp_generate_password( 64, true, true ) );
	}

	// Set default options.
	if ( ! get_option( 'wcha_settings' ) ) {
		update_option(
			'wcha_settings',
			array(
				'jwt_expiration'    => 3600,
				'refresh_expiration' => 604800,
				'allowed_origins'   => array(),
				'rate_limit'        => 100,
				'rate_limit_window' => 60,
			)
		);
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\wcha_activate' );

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function wcha_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\wcha_deactivate' );
