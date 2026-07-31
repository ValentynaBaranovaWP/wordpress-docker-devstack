<?php
/**
 * Plugin Name:       Client Orders Panel
 * Description:       Restricted admin panel: Client role with read-only access to own WooCommerce.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Valentyna Baranova
 * Text Domain:       client-orders-panel
 */

defined( 'ABSPATH' ) || exit;

define( 'COP_VERSION', '1.0.0' );
define( 'COP_PLUGIN_FILE', __FILE__ );
define( 'COP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'COP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once COP_PLUGIN_DIR . 'includes/class-cop-role.php';
require_once COP_PLUGIN_DIR . 'includes/class-cop-audit-log.php';
require_once COP_PLUGIN_DIR . 'includes/class-cop-admin-menu.php';
require_once COP_PLUGIN_DIR . 'includes/class-cop-orders-page.php';
require_once COP_PLUGIN_DIR . 'includes/class-cop-guards.php';
require_once COP_PLUGIN_DIR . 'includes/class-cop-rate-limiter.php';
require_once COP_PLUGIN_DIR . 'includes/class-cop-csp.php';

register_activation_hook( __FILE__, 'cop_activate' );

function cop_activate() {
	COP_Role::install();
	COP_Role::ensure_role();
	COP_Audit_Log::install();
	update_option( 'cop_version', COP_VERSION );
}

add_action( 'plugins_loaded', 'cop_bootstrap' );

function cop_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				echo esc_html( 'Client Orders Panel requires an active WooCommerce installation.' );
				echo '</p></div>';
			}
		);
		return;
	}

	COP_Role::ensure_role();
	COP_Role::init();
	COP_Admin_Menu::init();
	COP_Orders_Page::init();
	COP_Guards::init();
	COP_Rate_Limiter::init();
	COP_CSP::init();
}

function cop_is_client( $user = null ) {
	if ( null === $user ) {
		$user = wp_get_current_user();
	} elseif ( is_numeric( $user ) ) {
		$user = get_user_by( 'id', (int) $user );
	}

	if ( ! $user instanceof WP_User || ! $user->exists() ) {
		return false;
	}

	if ( ! in_array( COP_Role::ROLE, (array) $user->roles, true ) ) {
		return false;
	}

	if ( user_can( $user, 'manage_options' ) || user_can( $user, 'manage_woocommerce' ) ) {
		return false;
	}

	return true;
}
