<?php
if (!defined('ABSPATH')) exit;

/* Based on the version number run updates */
function nwm_version_updates()
{

    global $wpdb;

    $collate = '';
    if ($wpdb->has_cap('collation')) {
        if (!empty($wpdb->charset)) $collate = "DEFAULT CHARACTER SET $wpdb->charset";
        if (!empty($wpdb->collate)) $collate .= " COLLATE $wpdb->collate";
    }

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $current_version = get_option('nwm_version');

    if (version_compare($current_version, NWN_VERSION_NUM, '==='))
        return;

    if (version_compare($current_version, '1.0.3', '<')) {
        $settings = get_option('nwm_settings');

        if (is_array($settings) && empty($settings['zoom_level'])) {
            $settings['zoom_level'] = 3;
            update_option('nwm_settings', $settings);
            delete_transient('nwm_locations');
        }
    }

    if (version_compare($current_version, '1.1', '<')) {

        /* Add the thumb_id field to the table */
        $sql = "CREATE TABLE " . $wpdb->nwm_routes . " (
                nwm_id int(10) unsigned NOT NULL AUTO_INCREMENT,
                post_id bigint(20) unsigned NOT NULL,
                thumb_id bigint(20) unsigned NOT NULL,
                schedule tinyint(1) NOT NULL,
                lat float(10,6) NOT NULL,
                lng float(10,6) NOT NULL,
                location varchar(255) NOT NULL,
                arrival datetime NULL default '0000-00-00 00:00:00',
                departure datetime NULL default '0000-00-00 00:00:00',
				PRIMARY KEY  (nwm_id)
				) $collate ";

        dbDelta($sql);

        $settings = get_option('nwm_settings');
        $route_order = get_option('nwm_route_order');
        $post_ids = get_option('nwm_post_ids');

        if (is_array($settings)) {
            /* Add the curved line option to the map settings */
            if (empty($settings['curved_lines'])) {
                $settings['curved_lines'] = 0;
                update_option('nwm_settings', $settings);
            }

            /* Add the map type option to the map settings */
            if (empty($settings['map_type'])) {
                $settings['map_type'] = 'roadmap';
                update_option('nwm_settings', $settings);
            }
        }

        /* Add the name and ID for the default map to the options */
        add_option('nwm_map_ids', array('1' => 'Default'));

        /* Link the current route order and post ids to the default map */
        if (is_string($route_order)) {
            update_option('nwm_route_order', array("1" => $route_order));
        }

        if (is_string($post_ids)) {
            update_option('nwm_post_ids', array("1" => $post_ids));
            $post_ids = explode(',', $post_ids);

            /* Collect the ids for the thumbnails that are used in the linked blog post */
            if (!empty($post_ids)) {
                foreach ($post_ids as $post_id) {
                    $thumb_id = get_post_thumbnail_id($post_id);

                    if (!empty($thumb_id)) {
                        $thumb_ids[] = array(
                            'post_id' => $post_id,
                            'thumb_id' => $thumb_id
                        );
                    }
                }

                /* Update the thumb_id data */
                if (!empty($thumb_ids)) {
                    foreach ($thumb_ids as $thumb_data) {
                        nwm_set_thumb_id($thumb_data);
                    }
                }
            }
        }

        nwm_delete_all_transients();
    }

    if (version_compare($current_version, '1.1.4', '<')) {
        $settings = get_option('nwm_settings');

        if (is_array($settings)) {
            /* Add the read more option to the map settings */
            if (empty($settings['read_more'])) {
                $settings['read_more'] = 0;
                update_option('nwm_settings', $settings);
            }

            /* Add the content location option to the map settings */
            if (empty($settings['content_location'])) {
                $settings['content_location'] = 'slider';
                update_option('nwm_settings', $settings);
            }

            /* Add the location header option to the map settings */
            if (empty($settings['location_header'])) {
                $settings['location_header'] = '0';
                update_option('nwm_settings', $settings);
            }
        }

        nwm_delete_all_transients();
    }

    if (version_compare($current_version, '1.2', '<')) {

        /* Add the country_code field to the table */
        $sql = "CREATE TABLE " . $wpdb->nwm_routes . " (
                nwm_id int(10) unsigned NOT NULL AUTO_INCREMENT,
                post_id bigint(20) unsigned NOT NULL,
                thumb_id bigint(20) unsigned NOT NULL,
                schedule tinyint(1) NOT NULL,
                lat float(10,6) NOT NULL,
                lng float(10,6) NOT NULL,
                location varchar(255) NOT NULL,
                iso2_country_code char(2) NULL,
                arrival datetime NULL default '0000-00-00 00:00:00',
                departure datetime NULL default '0000-00-00 00:00:00',
				PRIMARY KEY  (nwm_id)
				) $collate ";

        dbDelta($sql);

        /* Add the read more field to the options */
        $settings = get_option('nwm_settings');

        if (is_array($settings)) {
            /* Add the default read more label to the map settings */
            if (empty($settings['read_more_label'])) {
                $settings['read_more_label'] = 'Read more';
                update_option('nwm_settings', $settings);
            }

            if (empty($settings['latlng_input'])) {
                $settings['latlng_input'] = '0';
                update_option('nwm_settings', $settings);
            }
        }

        /* Make sure all country codes exist */
        nwm_check_country_codes();

    }

    if (version_compare($current_version, '1.2.21', '<')) {
        nwm_delete_all_transients();
    }

    if (version_compare($current_version, '1.2.30', '<')) {
        $settings = get_option('nwm_settings');

        if (is_array($settings)) {
            if (empty($settings['initial_tooltip'])) {
                $settings['initial_tooltip'] = 0;
                update_option('nwm_settings', $settings);
            }
        }

        nwm_delete_all_transients();
    }

    update_option('nwm_version', NWN_VERSION_NUM);
}

/* Set the correct thumb_id for the existing post_id */
function nwm_set_thumb_id($thumb_data)
{

    global $wpdb;

    $result = $wpdb->query(
        $wpdb->prepare(
            "
							UPDATE $wpdb->nwm_routes 
							SET thumb_id = %d
							WHERE post_id = %d 
							",
            $thumb_data['thumb_id'],
            $thumb_data['post_id']
        )
    );

}

nwm_version_updates();
?>