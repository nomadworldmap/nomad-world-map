<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function nwm_geocode_location( $location ) {

    $url = nvm_add_key_to_gmaps_url('https://maps.googleapis.com/maps/api/geocode/json?address='.urlencode( $location ).'', 'server');

    $resp = wp_remote_get( $url );

    if ( 200 == $resp['response']['code'] ) {
        $body = $resp['body'];
        $api_data = json_decode( $body, true );

        if ( $api_data['status'] == 'OK' ) {
            $response = array(
                "latlng"       => $api_data['results'][0]['geometry']['location'],
                "country_code" => nwm_filter_country_code( $api_data )
            );

            return $response;
        }
    }

}

function nwm_filter_country_code( $response ) {

    $length = count( $response['results'][0]['address_components'] );
    $country_code = array();

    /* Loop over the address components untill we find the country,political part */
    for ( $i = 0; $i < $length; $i++ ) {
        $address_component = $response['results'][0]['address_components'][$i]['types'];

        if ( $address_component[0] == 'country' && $address_component[1] == 'political' ) {
            $country_code['short_name'] = $response['results'][0]['address_components'][$i]['short_name'];
            break;
        }
    }

    return $country_code;
} 

/* Select all location data that has an missing country code, and attempt to get the missing country code from the Google Maps API. */
function nwm_check_country_codes() {
    
    global $wpdb;

    $nwm_location_data = $wpdb->get_results( "SELECT nwm_id, location, iso2_country_code FROM $wpdb->nwm_routes WHERE iso2_country_code = ''" );

    if ( !empty( $nwm_location_data ) ) {
       $empty_responses = nwm_request_location_data ( $nwm_location_data ); 
        
       /* Check if we have empty responses, if so we make a second API request. */
       if ( !empty( $empty_responses ) ) {
           foreach ( $empty_responses as $key => $location_id ) {
               
                /* Clean up the location data array, so that it only contains location data 
                 * for location_ids that recieved an empty API response.
                 * This cleaned array will be used for a new API request
                 */
               foreach ( $nwm_location_data as $k => $nwm_location ) {	
                   if ( $nwm_location->nwm_id != $location_id ) {
                       unset( $nwm_location_data[$k] );
                   }
               }
                              
               nwm_request_location_data ( $nwm_location_data );
           }
       } // end empty_response check      
    } // end empty nwm_location_data check

}

/* Make a request to the Google Maps API for the country code */
function nwm_request_location_data ( $nwm_location_data ) {

    $sleep_value =  150000;

    $country_codes = array();
    /* Try to retrieve all the country codes from the Google Maps API */
    foreach ( $nwm_location_data as $k => $nwm_location ) {	
        if ( empty( $nwm_location->iso2_country_code ) ) {
             $country_codes[$nwm_location->nwm_id] = nwm_geocode_location( $nwm_location->location );
        } 

        /* Throttle the Google Maps API requests */
        usleep( $sleep_value );
    }

    /* Loop over the results, and either collect the empty results or update the country code in the db */
    foreach ( $country_codes as $nwm_id => $country_code ) {
        if ( empty( $country_code ) ) {
           $empty_responses[] = $nwm_id;
        } else {
            nwm_update_country_code( $country_code['country_code']['short_name'], $nwm_id );
        }
    }
    
    if ( !empty( $empty_responses ) ) {
        return $empty_responses;
    }
    
} 

/* Update the country code for each location */
function nwm_update_country_code( $country_code, $nwm_id ) {
    
    global $wpdb;	
    
    $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->nwm_routes SET iso2_country_code = %s WHERE nwm_id = %d", $country_code, $nwm_id ) );	
    
}

function nvm_add_key_to_gmaps_url($url, $type='browser')
{

    $options  = get_option( 'nwm_settings' );

    if ($type == 'server') {
        $key  = isset( $options['google_api_server_key'] ) ? $options['google_api_server_key'] : '';
    } else {
        $key  = isset( $options['google_api_browser_key'] ) ? $options['google_api_browser_key'] : '';
    }

    // If no key added no point in checking
    if (empty($key) || !$key) {
        return $url;
    }

    if (strstr($url, "key=") === false) {// it needs a key
        $url = add_query_arg('key', $key, $url);
        if ($type != 'server') {
            $url = str_replace("&#038;", "&amp;", $url); // or $url = $original_url
        }
    }

    return $url;
}