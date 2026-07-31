<?php

defined( 'ABSPATH' ) || exit;

class COP_Guards {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'guard_admin_requests' ), 0 );
		add_action( 'current_screen', array( __CLASS__, 'guard_admin_screens' ), 0 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'guard_rest' ), 0, 3 );
	}

	public static function guard_admin_requests() {
		if ( ! cop_is_client() ) {
			return;
		}

		if ( wp_doing_ajax() ) {
			self::guard_ajax();
			return;
		}

		self::log_order_edit_attempts();
		self::block_write_requests();
	}

	private static function guard_ajax() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		$allowed = apply_filters(
			'cop_allowed_ajax_actions',
			array(
				'heartbeat',
				'cop_refresh_order_status',
			)
		);

		if ( in_array( $action, $allowed, true ) ) {
			return;
		}

		COP_Audit_Log::log( COP_Audit_Log::EVENT_BLOCKED_AJAX );
		wp_send_json_error( array( 'message' => 'Access denied.' ), 403 );
	}

	private static function log_order_edit_attempts() {
		$order_id = 0;

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'wc-orders' === $page || 0 === strpos( $page, 'wc-orders--' ) ) {
			$order_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		}

		global $pagenow;
		if ( 'post.php' === $pagenow && isset( $_GET['post'] ) ) {
			$maybe_order = absint( $_GET['post'] );
			if ( 'shop_order' === get_post_type( $maybe_order ) ) {
				$order_id = $maybe_order;
			}
		}

		if ( $order_id > 0 || 'wc-orders' === $page ) {
			COP_Audit_Log::log( COP_Audit_Log::EVENT_FOREIGN_ORDER, $order_id );
			wp_safe_redirect( COP_Admin_Menu::cabinet_url() );
			exit;
		}
	}

	private static function block_write_requests() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

		global $pagenow;

		if ( in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
			if ( 'admin-post.php' !== $pagenow ) {
				return;
			}
		}

		$allowed_write_targets = apply_filters( 'cop_allowed_write_pages', array( 'profile.php' ) );

		if ( in_array( $pagenow, $allowed_write_targets, true ) ) {
			return;
		}

		COP_Audit_Log::log( COP_Audit_Log::EVENT_BLOCKED_WRITE );
		wp_die(
			esc_html( 'Access denied: changes from this account are not allowed.' ),
			esc_html( 'Access denied' ),
			array( 'response' => 403 )
		);
	}

	public static function guard_admin_screens( $screen ) {
		if ( ! cop_is_client() || ! $screen instanceof WP_Screen ) {
			return;
		}

		$allowed = array_merge( COP_Admin_Menu::allowed_screen_ids(), array( 'dashboard' ) );

		if ( in_array( $screen->id, $allowed, true ) ) {
			return;
		}

		COP_Audit_Log::log( COP_Audit_Log::EVENT_BLOCKED_SCREEN );
		wp_safe_redirect( COP_Admin_Menu::cabinet_url() );
		exit;
	}

	public static function guard_rest( $result, $server, $request ) {
		if ( null !== $result || ! cop_is_client() ) {
			return $result;
		}

		if ( preg_match( '#^/(wc|wc-admin|wc-analytics|wc-telemetry)(/|$)#', $request->get_route() ) ) {
			COP_Audit_Log::log( COP_Audit_Log::EVENT_BLOCKED_REST );
			return new WP_Error(
				'cop_rest_forbidden',
				'Access to this REST resource is forbidden.',
				array( 'status' => 403 )
			);
		}

		return $result;
	}
}
