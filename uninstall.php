<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$cop_users         = get_users(
	array(
		'role'   => 'client',
		'fields' => 'ID',
	)
);
$cop_fallback_role = get_role( 'customer' ) ? 'customer' : 'subscriber';

foreach ( $cop_users as $cop_user_id ) {
	$cop_user = new WP_User( $cop_user_id );
	$cop_user->remove_role( 'client' );
	if ( empty( $cop_user->roles ) ) {
		$cop_user->add_role( $cop_fallback_role );
	}
}

remove_role( 'client' );
delete_option( 'cop_version' );

if ( defined( 'COP_REMOVE_DATA' ) && COP_REMOVE_DATA ) {
	global $wpdb;
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cop_access_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
