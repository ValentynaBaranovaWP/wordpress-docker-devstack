<?php

defined( 'ABSPATH' ) || exit;

class COP_Role {

	const ROLE = 'client';

	const ROLE_LABEL = 'Client';

	const CAP_VIEW_OWN_ORDERS = 'cop_view_own_orders';

	private static $denied_caps = array(
		'manage_options',
		'edit_posts',
		'edit_pages',
		'edit_others_posts',
		'publish_posts',
		'delete_posts',
		'upload_files',
		'edit_users',
		'list_users',
		'create_users',
		'delete_users',
		'install_plugins',
		'activate_plugins',
		'edit_plugins',
		'install_themes',
		'switch_themes',
		'edit_theme_options',
		'edit_files',
		'import',
		'export',
		'moderate_comments',
		'unfiltered_html',
		'manage_woocommerce',
		'view_woocommerce_reports',
		'edit_shop_orders',
		'edit_others_shop_orders',
		'edit_private_shop_orders',
		'edit_published_shop_orders',
		'publish_shop_orders',
		'read_private_shop_orders',
		'delete_shop_orders',
		'delete_others_shop_orders',
		'delete_private_shop_orders',
		'delete_published_shop_orders',
		'edit_products',
		'edit_others_products',
		'publish_products',
		'delete_products',
		'edit_shop_coupons',
		'publish_shop_coupons',
		'delete_shop_coupons',
	);

	public static function init() {
		add_filter( 'user_has_cap', array( __CLASS__, 'deny_privileged_caps' ), PHP_INT_MAX, 4 );
		add_filter( 'woocommerce_prevent_admin_access', array( __CLASS__, 'allow_admin_access' ), 10, 1 );
		add_filter( 'woocommerce_disable_admin_bar', array( __CLASS__, 'allow_admin_access' ), 10, 1 );
	}

	public static function install() {
		remove_role( self::ROLE );
		add_role(
			self::ROLE,
			self::ROLE_LABEL,
			array(
				'read'                    => true,
				self::CAP_VIEW_OWN_ORDERS => true,
			)
		);
	}

	public static function ensure_role() {
		$role = get_role( self::ROLE );

		if ( ! $role ) {
			self::install();
			return;
		}

		$role->add_cap( 'read', true );
		$role->add_cap( self::CAP_VIEW_OWN_ORDERS, true );

		$wp_roles = wp_roles();
		if ( ! isset( $wp_roles->roles[ self::ROLE ] ) ) {
			return;
		}

		if ( self::ROLE_LABEL !== $wp_roles->roles[ self::ROLE ]['name'] ) {
			$wp_roles->roles[ self::ROLE ]['name'] = self::ROLE_LABEL;
			update_option( $wp_roles->role_key, $wp_roles->roles );
		}
	}

	public static function deny_privileged_caps( $allcaps, $caps, $args, $user ) {
		if ( ! $user instanceof WP_User || ! in_array( self::ROLE, (array) $user->roles, true ) ) {
			return $allcaps;
		}

		if ( ! empty( $allcaps['manage_options'] ) ) {
			return $allcaps;
		}

		foreach ( self::$denied_caps as $cap ) {
			$allcaps[ $cap ] = false;
		}

		return $allcaps;
	}

	public static function allow_admin_access( $prevent_or_disable ) {
		if ( cop_is_client() ) {
			return false;
		}
		return $prevent_or_disable;
	}
}
