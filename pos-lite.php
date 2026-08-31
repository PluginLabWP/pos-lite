<?php
/**
 * Plugin Name:       POS Lite for WooCommerce
 * Plugin URI:        https://example.com/pos-lite
 * Description:       A fast, offline-first point of sale for WooCommerce. Loads the catalog onto the device, searches locally with zero API round-trips, and queues sales when the connection drops. Free version: no payment integration.
 * Version:           0.5.14
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            You
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pos-lite
 * WC requires at least: 8.0
 * WC tested up to:   9.9
 *
 * @package POS_Lite
 */

defined( 'ABSPATH' ) || exit;

define( 'POSLITE_VERSION', '0.5.14' );
define( 'POSLITE_FILE', __FILE__ );
define( 'POSLITE_DIR', plugin_dir_path( __FILE__ ) );
define( 'POSLITE_URL', plugin_dir_url( __FILE__ ) );
define( 'POSLITE_REST_NS', 'pos-lite/v1' );

/**
 * Declare High-Performance Order Storage (HPOS) compatibility.
 *
 * This is the foundation of the whole plugin: we never touch postmeta directly,
 * we go through WooCommerce's order CRUD, so we are HPOS-native by design.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				POSLITE_FILE,
				true
			);
		}
	}
);

/**
 * Boot the plugin once WooCommerce is loaded.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>';
					esc_html_e( 'POS Lite requires WooCommerce to be installed and active.', 'pos-lite' );
					echo '</p></div>';
				}
			);
			return;
		}

		require_once POSLITE_DIR . 'includes/class-poslite-roles.php';
		require_once POSLITE_DIR . 'includes/class-poslite-settings.php';
		require_once POSLITE_DIR . 'includes/class-poslite-loyalty.php';
		require_once POSLITE_DIR . 'includes/class-poslite-register.php';
		require_once POSLITE_DIR . 'includes/class-poslite-rest.php';
		require_once POSLITE_DIR . 'includes/class-poslite-app.php';

		POSLite_Roles::maybe_install();
		( new POSLite_Settings() )->init();
		POSLite_App::instance()->init();

		add_action(
			'rest_api_init',
			function () {
				( new POSLite_REST() )->register_routes();
			}
		);
	}
);

/**
 * Activation: register the /pos/ front-end route, then flush rewrite rules
 * once so the endpoint resolves without a manual permalink re-save.
 */
register_activation_hook(
	POSLITE_FILE,
	function () {
		require_once POSLITE_DIR . 'includes/class-poslite-roles.php';
		require_once POSLITE_DIR . 'includes/class-poslite-app.php';
		POSLite_Roles::install();
		POSLite_App::instance()->add_rewrite_rules();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	POSLITE_FILE,
	function () {
		flush_rewrite_rules();
	}
);
