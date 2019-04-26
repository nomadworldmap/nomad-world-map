<?php
if (!defined('ABSPATH')) exit;

/* Set the default settings */
function nwm_default_settings()
{

    $settings_check = get_option('nwm_settings');

    if (!$settings_check) {
        $settings = array(
            'flightpath' => '1',
            'curved_lines' => '0',
            'map_type' => 'roadmap',
            'round_thumbs' => '1',
            'zoom_to' => 'last',
            'zoom_level' => '2',
            'past_color' => '#bcbcbc',
            'future_color' => '#51a57c',
            'streetview' => '0',
            'control_position' => 'left',
            'control_style' => 'small',
            'read_more' => '0',
            'content_location' => 'slider',
            'location_header' => '0',
            'read_more_label' => 'Read more',
			'latlng_input' => '0',
			'google_maps_style' => '[ { "featureType": "administrative", "elementType": "labels.text.fill", "stylers": [ { "color": "#444444" } ] }, { "featureType": "administrative.country", "elementType": "geometry", "stylers": [ { "visibility": "off" } ] }, { "featureType": "administrative.country", "elementType": "labels", "stylers": [ { "visibility": "off" } ] }, { "featureType": "landscape", "elementType": "all", "stylers": [ { "color": "#f2f2f2" } ] }, { "featureType": "landscape", "elementType": "labels", "stylers": [ { "visibility": "off" } ] }, { "featureType": "poi", "elementType": "all", "stylers": [ { "visibility": "off" } ] }, { "featureType": "road", "elementType": "all", "stylers": [ { "saturation": -100 }, { "lightness": 45 }, { "visibility": "off" } ] }, { "featureType": "road.highway", "elementType": "all", "stylers": [ { "visibility": "simplified" } ] }, { "featureType": "road.arterial", "elementType": "labels.icon", "stylers": [ { "visibility": "off" } ] }, { "featureType": "transit", "elementType": "all", "stylers": [ { "visibility": "off" } ] }, { "featureType": "water", "elementType": "all", "stylers": [ { "color": "#ffffff" }, { "visibility": "on" } ] } ]'
        );

        update_option('nwm_settings', $settings);
    }

    $maps_check = get_option('nwm_map_ids');

    if (!$maps_check) {
        $maps = array('1' => 'Default');
        update_option('nwm_map_ids', $maps);
    }

}

/* Create the required tables */
function nwm_create_tables()
{

    global $wpdb;

    $collate = '';
    if ($wpdb->has_cap('collation')) {
        if (!empty($wpdb->charset)) $collate = "DEFAULT CHARACTER SET $wpdb->charset";
        if (!empty($wpdb->collate)) $collate .= " COLLATE $wpdb->collate";
    }

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');


    if ($wpdb->get_var("SHOW TABLES LIKE '$wpdb->nwm_routes'") != $wpdb->nwm_routes) {

        $sql = "CREATE TABLE ".$wpdb->nwm_routes." (
				nwm_id int(10) unsigned NOT NULL AUTO_INCREMENT,
				post_id bigint(20) unsigned NOT NULL,
				thumb_id bigint(20) unsigned NOT NULL,
				schedule tinyint(1) NOT NULL,
				lat float(10,6) NOT NULL,
				lng float(10,6) NOT NULL,
				location varchar(255) NOT NULL,
				iso2_country_code char(2) NOT NULL,
				arrival datetime NULL default '0000-00-00 00:00:00',
				departure datetime NULL default '0000-00-00 00:00:00',
				PRIMARY KEY  (nwm_id)
				) $collate ";

        dbDelta($sql);
    }

    if ($wpdb->get_var("SHOW TABLES LIKE '$wpdb->nwm_custom'") != $wpdb->nwm_custom) {

        $sql = "CREATE TABLE " . $wpdb->nwm_custom . " (
				nwm_id int(10) unsigned NOT NULL,
				content text NULL,
				url varchar(255) NULL,
				title text NOT NULL,
				PRIMARY KEY  (nwm_id)
				) $collate ";

        dbDelta($sql);
    }

}

nwm_default_settings();
nwm_create_tables();

?>