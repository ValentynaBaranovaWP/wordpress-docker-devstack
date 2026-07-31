<?php

defined( 'ABSPATH' ) || exit;

class COP_Admin_Menu {

	const PAGE_ORDERS = 'cop-orders';
	const PAGE_HELP   = 'cop-help';

	private static $page_hooks = array();

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_pages' ), 9 );
		add_action( 'admin_menu', array( __CLASS__, 'strip_foreign_menus' ), PHP_INT_MAX );
		add_action( 'load-index.php', array( __CLASS__, 'redirect_dashboard' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'trim_admin_bar' ), PHP_INT_MAX );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function allowed_screen_ids() {
		return array_merge( self::$page_hooks, array( 'profile' ) );
	}

	public static function cabinet_url() {
		return admin_url( 'admin.php?page=' . self::PAGE_ORDERS );
	}

	public static function register_pages() {
		$hook = add_menu_page(
			'My Orders',
			'My Orders',
			COP_Role::CAP_VIEW_OWN_ORDERS,
			self::PAGE_ORDERS,
			array( 'COP_Orders_Page', 'render' ),
			'dashicons-cart',
			3
		);
		self::$page_hooks[] = $hook;

		$hook = add_submenu_page(
			self::PAGE_ORDERS,
			'Help',
			'Help',
			COP_Role::CAP_VIEW_OWN_ORDERS,
			self::PAGE_HELP,
			array( __CLASS__, 'render_help' )
		);
		self::$page_hooks[] = $hook;

		global $submenu;
		if ( isset( $submenu[ self::PAGE_ORDERS ] ) ) {
			$submenu[ self::PAGE_ORDERS ][] = array( // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				'Profile',
				'read',
				'profile.php',
			);
		}
	}

	public static function strip_foreign_menus() {
		if ( ! cop_is_client() ) {
			return;
		}

		global $menu;

		$allowed = array( self::PAGE_ORDERS, 'profile.php' );

		foreach ( (array) $menu as $item ) {
			$slug = isset( $item[2] ) ? $item[2] : '';
			if ( '' === $slug || in_array( $slug, $allowed, true ) ) {
				continue;
			}
			remove_menu_page( $slug );
		}
	}

	public static function redirect_dashboard() {
		if ( cop_is_client() ) {
			wp_safe_redirect( self::cabinet_url() );
			exit;
		}
	}

	public static function login_redirect( $redirect_to, $requested, $user ) {
		if ( $user instanceof WP_User && cop_is_client( $user ) ) {
			return self::cabinet_url();
		}
		return $redirect_to;
	}

	public static function trim_admin_bar( $bar ) {
		if ( ! cop_is_client() ) {
			return;
		}

		foreach ( array( 'new-content', 'comments', 'updates', 'search', 'customize', 'edit', 'wp-logo', 'dashboard', 'themes', 'widgets', 'menus' ) as $node ) {
			$bar->remove_node( $node );
		}
	}

	public static function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, self::$page_hooks, true ) ) {
			return;
		}

		wp_enqueue_style( 'cop-admin', COP_PLUGIN_URL . 'assets/admin.css', array(), COP_VERSION );
		wp_enqueue_script( 'cop-admin', COP_PLUGIN_URL . 'assets/admin.js', array(), COP_VERSION, true );
		wp_localize_script(
			'cop-admin',
			'copAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cop_order_status' ),
			)
		);
	}

	public static function render_help() {
		if ( ! current_user_can( COP_Role::CAP_VIEW_OWN_ORDERS ) ) {
			wp_die( esc_html( 'Access denied.' ), 403 );
		}
		?>
		<div class="wrap cop-wrap">
			<h1>Help</h1>
			<div class="cop-card">
				<h2>How to use the cabinet</h2>
				<ul class="cop-help-list">
					<li>The My Orders tab shows only orders placed from your account.</li>
					<li>Click View to see order items, total, shipping address, and current status.</li>
					<li>Orders in the cabinet are read-only. Contact a manager to change an order.</li>
					<li>Use the Profile tab to change your password and contact details.</li>
				</ul>
			</div>
			<div class="cop-card">
				<h2>Contact</h2>
				<p>
					For order questions, email
					<a href="mailto:<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"><?php echo esc_html( get_option( 'admin_email' ) ); ?></a>.
				</p>
			</div>
		</div>
		<?php
	}
}
