<?php
if ( !defined( 'ABSPATH' ) && !defined( 'WP_UNINSTALL_PLUGIN ') ) {
	exit;
}

function nwm_uninstall() {
	
	global $wpdb;

	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'nwm_routes' );
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'nwm_custom' );
    
    nwm_remove_transients();

	delete_option( 'nwm_version' );
	delete_option( 'nwm_settings' );
	delete_option( 'nwm_post_ids' );
	delete_option( 'nwm_map_ids' );
	delete_option( 'nwm_route_order' );
	
}

function nwm_remove_transients() {
    
    $map_values = get_option( 'nwm_map_ids' );

    if ( !empty( $map_values ) ) {
        foreach ( $map_values as $map_id => $map_name )	{
            delete_transient( 'nwm_locations_'.$map_id );
            delete_transient( 'nwm_route_list_'.$map_id );	
            delete_transient( 'nwm_widget_'.$map_id );
        }	
    }

}

/* Delete the tables and options from the db  */
nwm_uninstall();
?>