<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['cop_test_transients'] = array();
$GLOBALS['cop_test_time']       = null;

function cop_test_now() {
	return null !== $GLOBALS['cop_test_time'] ? (int) $GLOBALS['cop_test_time'] : time();
}

function get_transient( $key ) {
	if ( ! isset( $GLOBALS['cop_test_transients'][ $key ] ) ) {
		return false;
	}

	$item = $GLOBALS['cop_test_transients'][ $key ];

	if ( $item['expires'] > 0 && cop_test_now() >= $item['expires'] ) {
		unset( $GLOBALS['cop_test_transients'][ $key ] );
		return false;
	}

	return $item['value'];
}

function set_transient( $key, $value, $expiration = 0 ) {
	$GLOBALS['cop_test_transients'][ $key ] = array(
		'value'   => $value,
		'expires' => $expiration > 0 ? cop_test_now() + (int) $expiration : 0,
	);
	return true;
}

function delete_transient( $key ) {
	unset( $GLOBALS['cop_test_transients'][ $key ] );
	return true;
}

function apply_filters( $hook_name, $value ) {
	return $value;
}

function add_action( ...$args ) {
	return true;
}

function add_filter( ...$args ) {
	return true;
}

function get_current_user_id() {
	return 0;
}

function absint( $maybeint ) {
	return abs( (int) $maybeint );
}

function wp_json_encode( $data ) {
	return json_encode( $data );
}

function wp_unslash( $value ) {
	return $value;
}

function sanitize_text_field( $str ) {
	return is_string( $str ) ? trim( $str ) : '';
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function current_time( $type, $gmt = 0 ) {
	return gmdate( 'Y-m-d H:i:s', cop_test_now() );
}

function wp_rand( $min = 0, $max = 0 ) {
	return $max;
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function esc_html( $text ) {
	return $text;
}

function esc_html__( $text, $domain = 'default' ) {
	return $text;
}

function esc_attr( $text ) {
	return $text;
}

class WP_Error {

	private $code;
	private $message;

	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

class COP_Test_WPDB {

	public $prefix  = 'wp_';
	public $inserts = array();

	public function insert( $table, $data, $format = null ) {
		$this->inserts[] = array(
			'table' => $table,
			'data'  => $data,
		);
		return 1;
	}

	public function query( $query ) {
		return 0;
	}

	public function prepare( $query, ...$args ) {
		return $query;
	}

	public function get_charset_collate() {
		return '';
	}
}

$GLOBALS['wpdb'] = new COP_Test_WPDB();

require_once dirname( __DIR__ ) . '/includes/class-cop-audit-log.php';
require_once dirname( __DIR__ ) . '/includes/class-cop-rate-limiter.php';
