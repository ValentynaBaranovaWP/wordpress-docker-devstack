<?php

defined( 'ABSPATH' ) || exit;

class COP_Audit_Log {

	const TABLE = 'cop_access_log';

	const EVENT_FOREIGN_ORDER   = 'foreign_order_access';
	const EVENT_BLOCKED_SCREEN  = 'blocked_admin_screen';
	const EVENT_BLOCKED_WRITE   = 'blocked_write_request';
	const EVENT_BLOCKED_AJAX    = 'blocked_ajax_action';
	const EVENT_BLOCKED_REST    = 'blocked_rest_request';
	const EVENT_LOGIN_THROTTLED = 'login_throttled';
	const EVENT_RATE_LIMITED    = 'cabinet_rate_limited';

	public static function install() {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			event VARCHAR(64) NOT NULL,
			object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			ip VARCHAR(45) NOT NULL DEFAULT '',
			request_uri VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY event (event),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function log( $event, $object_id = 0, $user_id = null ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . self::TABLE,
			array(
				'user_id'     => null === $user_id ? get_current_user_id() : (int) $user_id,
				'event'       => sanitize_key( $event ),
				'object_id'   => (int) $object_id,
				'ip'          => self::get_ip(),
				'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 0, 255 ) : '',
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s' )
		);

		self::maybe_prune();
	}

	public static function get_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return substr( $ip, 0, 45 );
	}

	private static function maybe_prune() {
		if ( wp_rand( 1, 100 ) > 2 ) {
			return;
		}

		global $wpdb;

		$days = (int) apply_filters( 'cop_audit_retention_days', 90 );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}cop_access_log WHERE created_at < %s",
				gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS )
			)
		);
	}
}
