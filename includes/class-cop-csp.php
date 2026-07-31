<?php

defined( 'ABSPATH' ) || exit;

class COP_CSP {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'send_headers' ), 2 );
	}

	public static function send_headers() {
		if ( ! cop_is_client() || wp_doing_ajax() || headers_sent() ) {
			return;
		}

		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );

		$enabled = apply_filters( 'cop_enable_csp', defined( 'COP_ENABLE_CSP' ) && COP_ENABLE_CSP );
		if ( ! $enabled ) {
			return;
		}

		$policy = apply_filters(
			'cop_csp_policy',
			"default-src 'self'; " .
			"script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
			"style-src 'self' 'unsafe-inline'; " .
			"img-src 'self' data:; " .
			"font-src 'self' data:; " .
			"connect-src 'self'; " .
			"object-src 'none'; " .
			"base-uri 'self'; " .
			"form-action 'self'; " .
			"frame-ancestors 'self'"
		);

		header( 'Content-Security-Policy: ' . $policy );
	}
}
