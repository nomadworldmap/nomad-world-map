<?php
/*
Plugin Name: Nomad World Map
Plugin URI: http://nomadworldmap.com/
Description: Create your own custom travel map. Link locations on the map to blog posts and share your travel plans.
Version: 1.2.31
Author: Tijmen Smit
Author URI: http://nomadworldmap.com/
License: GPLv2

Copyright 2013 Tijmen Smit, tijmen at nomadworldmap.com

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License version 2 or later
as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA

*/

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'NWN_VERSION_NUM' ) )	
	define( 'NWN_VERSION_NUM', '1.2.31' );
	
if ( ! defined( 'NWM_URL' ) )
	define( 'NWM_URL', plugin_dir_url( __FILE__ ) );

if ( !defined( 'NWM_BASENAME' ) )
	define( 'NWM_BASENAME', plugin_basename( __FILE__ ) );
	
nwm_version_check();
nwm_define_tables();

add_filter( 'wp_loaded', 'nwm_load_textdomain' );

function nwm_load_textdomain() {
	load_plugin_textdomain( 'nwm', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

require 'includes/nwm-widget-class.php';
require 'includes/nwm-geocode-functions.php';

if ( is_admin() ) {
	require 'admin/nwm-admin-functions.php';
	
	register_activation_hook( __FILE__, 'nwm_activate' );
	register_deactivation_hook( __FILE__, 'nwm_deactivate' );
	
	add_action( 'admin_enqueue_scripts', 'nwm_admin_scripts' );
} else {
	require 'includes/nwm-frontend-functions.php';
}

/* Check if the WP version is equal or higher then 3.5 */
function nwm_version_check() {

	global $wp_version;

	if ( ( version_compare( $wp_version, '3.5', '<' ) == TRUE ) ) {
		if ( is_admin() && ( !defined( 'DOING_AJAX' ) || !DOING_AJAX ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' ); 
			deactivate_plugins( NWM_BASENAME );
			wp_die( "<strong>Nomad World Map</strong> requires WordPress 3.5 or higher, and has been disabled. Please upgrade WordPress and try again. <br /><br />Back to the WordPress <a href='".get_admin_url( null, 'plugins.php' )."'>Plugins page</a>." );
		} else {
			return;	
		}
	}
	
}

function nwm_define_tables() {

	global $wpdb;

	$wpdb->nwm_routes = $wpdb->prefix . 'nwm_routes';
	$wpdb->nwm_custom = $wpdb->prefix . 'nwm_custom';

}

/* On activation create the required db table and set the default options */
function nwm_activate() {
	require 'admin/nwm-install.php';
}

/* If the plugin is deactivated, delete the transient form the db */
function nwm_deactivate() {
	delete_transient('nwm_locations');
}

?>