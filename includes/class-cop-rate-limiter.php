<?php

defined( 'ABSPATH' ) || exit;

class COP_Rate_Limiter {

	public static function init() {
		add_action( 'wp_login_failed', array( __CLASS__, 'record_failed_login' ) );
		add_filter( 'authenticate', array( __CLASS__, 'maybe_block_login' ), 30, 3 );
		add_action( 'wp_login', array( __CLASS__, 'clear_login_counter' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'throttle_cabinet' ), 1 );
	}

	private static function login_key( $username ) {
		return 'cop_rl_login_' . md5( COP_Audit_Log::get_ip() . '|' . strtolower( trim( (string) $username ) ) );
	}

	public static function record_failed_login( $username ) {
		$key      = self::login_key( $username );
		$attempts = (int) get_transient( $key );
		set_transient( $key, $attempts + 1, self::lockout_seconds() );
	}

	public static function maybe_block_login( $user, $username, $password ) {
		if ( empty( $username ) ) {
			return $user;
		}

		$attempts = (int) get_transient( self::login_key( $username ) );

		if ( $attempts >= self::max_login_attempts() ) {
			COP_Audit_Log::log( COP_Audit_Log::EVENT_LOGIN_THROTTLED, 0, 0 );
			return new WP_Error(
				'cop_too_many_attempts',
				'Too many failed login attempts. Try again later.'
			);
		}

		return $user;
	}

	public static function clear_login_counter( $user_login, $user ) {
		delete_transient( self::login_key( $user_login ) );
	}

	private static function max_login_attempts() {
		return max( 1, (int) apply_filters( 'cop_login_max_attempts', 5 ) );
	}

	private static function lockout_seconds() {
		return max( 60, (int) apply_filters( 'cop_login_lockout_seconds', 15 * MINUTE_IN_SECONDS ) );
	}

	public static function throttle_cabinet() {
		if ( ! cop_is_client() ) {
			return;
		}

		$limit = max( 10, (int) apply_filters( 'cop_cabinet_rate_limit', 120 ) );
		$key   = 'cop_rl_cab_' . get_current_user_id();
		$count = (int) get_transient( $key );

		if ( 0 === $count ) {
			set_transient( $key, 1, MINUTE_IN_SECONDS );
			return;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		if ( $count + 1 > $limit ) {
			if ( $count + 1 === $limit + 1 ) {
				COP_Audit_Log::log( COP_Audit_Log::EVENT_RATE_LIMITED );
			}
			wp_die(
				esc_html( 'Too many requests. Wait a minute and try again.' ),
				esc_html( 'Too many requests' ),
				array( 'response' => 429 )
			);
		}
	}
}
