<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function nwm_settings_page() {
	
	global $wpdb;	 
		
	$options         = get_option( 'nwm_settings' );
    $options['initial_tooltip']  = isset( $options['initial_tooltip'] ) ? $options['initial_tooltip'] : 0;
    $options['google_api_browser_key']  = isset( $options['google_api_browser_key'] ) ? $options['google_api_browser_key'] : '';
    $options['google_api_server_key']  = isset( $options['google_api_server_key'] ) ? $options['google_api_server_key'] : '';
    $nwm_route_order = get_option( 'nwm_route_order' );
	?>
    <div class="wrap">
        <div id="nwm-wrap">
            <h2><?php _e( 'Nomad World Map Settings', 'nwm' ); ?></h2>
          	<?php if ( ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == 'true' ) ) { ?>
            	<div id="message" class="message updated"><p><strong><?php _e( 'Settings updated', 'nwm' ) ?></strong></p></div>
            <?php } ?>
            <form id="nwm-settings-form" action="options.php" method="post">
                <div class="postbox-container">
                    <div class="metabox-holder">
                        <div class="postbox">
                            <h3 class="hndle"><span><?php _e( 'General', 'nwm' ); ?></span></h3>
                            <div class="inside">
                                <p>
                                    <label for="nwm-google-api-browser-key"><?php _e( 'Google API Browser Key:', 'nwm' ); ?></label>
                                    <input type="text" name="nwm-google-api-browser-key" value="<?php echo esc_attr( $options['google_api_browser_key'] ); ?>" id="nwm-google-api-browser-key" />
                                    <br/>
                                    <?php echo sprintf(
                                        __('<b>Note:</b> Google API Browser Key is a requirement to use Google Maps, you can get a key from <a href="%s" target="_blank">here</a>', 'nvm'),
                                        'https://console.developers.google.com/flows/enableapi?apiid=maps_backend,geocoding_backend,directions_backend,distance_matrix_backend,elevation_backend&keyType=CLIENT_SIDE&reusekey=true' ); ?>
                                </p>
                                <p>
                                    <label for="nwm-google-api-server-key"><?php _e( 'Google API Server Key:', 'nwm' ); ?></label>
                                    <input type="text" name="nwm-google-api-server-key" value="<?php echo esc_attr( $options['google_api_server_key'] ); ?>" id="nwm-google-api-server-key" />
                                    <br/>
                                    <?php echo sprintf(
                                        __('<b>Note:</b> Google API Server Key is a requirement to use Google Maps, you can get a key from <a href="%s" target="_blank">here</a>', 'nvm'),
                                        'https://console.developers.google.com/flows/enableapi?apiid=maps_backend,geocoding_backend,directions_backend,distance_matrix_backend,elevation_backend&keyType=CLIENT_SIDE&reusekey=true' ); ?>
                                </p>
                                <p>
                                   <label for="nwm-flightpath"><?php _e( 'Draw lines between the markers?', 'nwm' ); ?></label> 
                                   <input id="nwm-flightpath" type="checkbox" name="nwm-flightpath" value="" <?php checked( $options['flightpath'], true ); ?> />
                                </p>
                                <p <?php if ( $options['flightpath'] != '1' ) { echo 'style="display:none;"'; } ?>   class="nwm-curved-option">
                                   <label for="nwm-curved-lines"><?php _e( 'Draw curved lines on the map?', 'nwm' ); ?></label> 
                                   <input id="nwm-curved-lines" type="checkbox" name="nwm-curved-lines" value="" <?php checked( $options['curved_lines'], true ); ?> />
                                </p>            
                                <p>
                                    <label for="nwm-zoom-to"><?php _e( 'On pageload zoom to:', 'nwm' ); ?></label> 
                                    <?php echo nwm_zoom_to( $options ); ?>
                                </p>
                                <p>
                                    <label for="nwm-zoom-level"><?php _e( 'Zoom level:', 'nwm' ); ?></label> 
                                    <?php echo nwm_zoom_level( $options ); ?>
                                </p>
                                <p>
                                    <label for="nwm-map-type"><?php _e( 'Map type:', 'nwm' ); ?></label> 
                                    <?php echo nwm_map_types( $options ); ?>
                                </p>
                                <div class="nwm-marker-lines">
                                    <label for="nwm-past-color"><?php _e( 'Past route color:', 'nwm' ); ?></label> 
                                    <input type="text" name="nwm-past-color" value="<?php echo esc_attr( $options['past_color'] ); ?>" id="nwm-past-color" />
                                </div>
                                <div class="nwm-marker-lines">
                                    <label for="nwm-future-color"><?php _e( 'Future route color:', 'nwm' ); ?></label> 
                                    <input type="text" name="nwm-future-color" value="<?php echo esc_attr( $options['future_color'] ); ?>" id="nwm-future-color" />
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="metabox-holder">
                        <div class="postbox">
                            <h3 class="hndle"><span><?php _e( 'Content Options', 'nwm' ); ?></span></h3>
                            <div class="inside">
                                <p>
                                   <label for="nwm-round-thumbs"><?php _e( 'Show the post thumbnails in a circle?', 'nwm' ); ?></label> 
                                   <input id="nwm-round-thumbs" type="checkbox" name="nwm-round-thumbs" value="" <?php checked( $options['round_thumbs'], true ); ?> />
                                </p>  
                                <p>
                                   <label for="nwm-readmore"><?php _e( 'Include a "read more" link for blog post?', 'nwm' ); ?></label> 
                                   <input id="nwm-readmore" type="checkbox" name="nwm-readmore" value="" <?php checked( $options['read_more'] == '1', true ); ?> />
                                </p>
                                <p id="nwm-custom-readmore" <?php if ( $options['read_more'] == '0' ) { echo 'style="display:none;"'; }; ?>>
                                   <label for="nwm-readmore-label"><?php _e( 'Read more label', 'nwm' ); ?></label> 
                                   <input id="nwm-readmore-label" type="text" name="nwm-readmore-label" value="<?php echo esc_attr( stripslashes( $options['read_more_label'] ) ); ?>" />
                                </p> 
                                <p>
                                    <label><?php _e( 'Show the location content in the:', 'nwm' ); ?></label>
                                    <span id="nwm-content-options" class="nwm-radioboxes">
                                        <input type="radio" id="nwm-content-slider" name="nwm-content-location" <?php checked( 'slider', $options['content_location'], true ); ?> value="slider" />
                                        <label for="nwm-content-slider"><?php _e( 'Slider', 'nwm' ); ?></label>
                                        <input type="radio" id="nwm-content-tooltip" name="nwm-content-location" <?php checked( 'tooltip', $options['content_location'], true ); ?> value="tooltip" />
                                        <label for="nwm-content-tooltip"><?php _e( 'Tooltip (this will remove the slider)', 'nwm' ); ?></label>
                                    </span>
                                </p>
                                <p id="nwm-hide-tooltip" <?php if ( $options['content_location'] == 'tooltip' ) { echo 'style="display:block"'; } ?>>
                                   <label for="nwm-initial-tooltip"><?php _e( 'Hide tooltip on initial page load?', 'nwm' ); ?></label> 
                                   <input id="nwm-initial-tooltip" type="checkbox" name="nwm-initial-tooltip" value="" <?php checked( $options['initial_tooltip'], true ); ?> />
                                </p>
                                <p>
                                   <label for="nwm-location-header"><?php _e( 'Show the location name under the header?', 'nwm' ); ?></label> 
                                   <input id="nwm-location-header" type="checkbox" name="nwm-location-header" value="" <?php checked( $options['location_header'], true ); ?> />
                                </p>
                            </div>        
                        </div>   
                    </div>
                    
                    <div class="metabox-holder">
                        <div class="postbox">
                            <h3 class="hndle"><span><?php _e( 'Route Editor Options', 'nwm' ); ?></span></h3>
                            <div class="inside">
                                <p>
                                   <label for="nwm-latlng-input"><?php _e( 'Show the coordinates input field', 'nwm' ); ?></label> 
                                   <input id="nwm-latlng-input" type="checkbox" name="nwm-latlng-input" value="" <?php checked( $options['latlng_input'], true ); ?> />
                                </p>  
                            </div>        
                        </div>   
                    </div>
                    
                    <div class="metabox-holder">
                        <div class="postbox">
                            <h3 class="hndle"><span><?php _e( 'Map Controls', 'nwm' ); ?></span></h3>
                            <div class="inside">
                                <p>
                                   <label for="nwm-streetview"><?php _e( 'Show the street view controls?', 'nwm' ); ?></label> 
                                   <input id="nwm-streetview" type="checkbox" name="nwm-streetview" value="" <?php checked( $options['streetview'], true ); ?> />
                                </p> 
                                <p>
                                    <label><?php _e( 'Position of the map controls', 'nwm' ); ?></label>
                                    <span class="nwm-radioboxes">
                                        <input type="radio" id="nwm-control-left" name="nwm-control-position" <?php checked( 'left', $options['control_position'], true ); ?> value="left" />
                                        <label for="nwm-control-left"><?php _e( 'Left', 'nwm' ); ?></label>
                                        <input type="radio" id="nwm-control-right" name="nwm-control-position" <?php checked( 'right', $options['control_position'], true ); ?> value="right" />
                                        <label for="nwm-control-right"><?php _e( 'Right', 'nwm' ); ?></label>
                                    </span>
                                </p>
                                <p>
                                    <label><?php _e( 'Zoom control style', 'nwm' ); ?></label>
                                    <span class="nwm-radioboxes">
                                        <input type="radio" id="nwm-small-style" name="nwm-control-style" <?php checked( 'small', $options['control_style'], true ); ?> value="small" />
                                        <label for="nwm-small-style"><?php _e( 'Small', 'nwm' ); ?></label>
                                        <input type="radio" id="nwm-large-style" name="nwm-control-style" <?php checked( 'large', $options['control_style'], true ); ?> value="large" />
                                        <label for="nwm-large-style"><?php _e( 'Large', 'nwm' ); ?></label>
                                    </span>
                                </p>
                            </div>        
                        </div>   
                    </div>  
                </div>   
                
                <div class="postbox-container side">
                	<div class="metabox-holder">
                        <div class="postbox">
                            <h3 class="hndle"><span><?php _e( 'About', 'nwm' ); ?></span><span style="float:right;"><?php _e( 'Version', 'nwm' ); ?> <?php echo NWN_VERSION_NUM; ?></span></h3>
                            <div class="inside">
                                <p><strong>Nomad World Map </strong><?php echo sprintf( __( 'by <a href="%s">Tijmen Smit</a>', 'nwm' ), 'http://twitter.com/tijmensmit' ); ?></p>
                                <p><?php echo sprintf( __( 'If you like this plugin, please rate it <strong>5 stars</strong> on <a href="%s">WordPress.org</a> or consider making a <a href="%s">donation</a> to support the development.', 'nwm' ), 'http://wordpress.org/plugins/nomad-world-map/', 'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=NFZ6NCFKXQ8EA' ); ?></p>        
                            </div>
                        </div>
                	</div>        
                </div>
 
                <p class="nwm-update-btn"><input id="nwm-add-trip" type="submit" name="nwm-add-trip" class="button-primary" value="<?php _e( 'Update Settings', 'nwm' ); ?>" /></p>
                <?php settings_fields( 'nwm_settings' ); ?>
            </form>
        </div>
    </div>    
    <?php
	
}

/* Process the map settings */
function nwm_settings_check() {
	
	$output = array();
	$zoom_options = array( 
        'first', 
        'schedule_start', 
        'last' 
    );
	$map_types = array( 
        'roadmap', 
        'satellite', 
        'hybrid', 
        'terrain' 
    );
		
	/* Check if we have a valid zoom-to option, otherwise set it to last */
	if ( in_array( $_POST['nwm-zoom-to'], $zoom_options ) ) {
		$output['zoom_to'] = wp_filter_nohtml_kses( $_POST['nwm-zoom-to'] );
	} else {
		$output['zoom_to'] = 'last';
	}
	
	/* Check if we have a valid map type, otherwise set it to roadmap */
	if ( in_array( $_POST['nwm-map-type'], $map_types ) ) {
		$output['map_type'] = wp_filter_nohtml_kses( $_POST['nwm-map-type'] );
	} else {
		$output['map_type'] = 'roadmap';
	}
	
	/* Check if we have a valid zoom level, it has to be between 1 or 12. If not set it to the default of 3 */
	if ( $_POST['nwm-zoom-level'] >= 1 && $_POST['nwm-zoom-level'] <= 12 ) {
		$output['zoom_level'] = $_POST['nwm-zoom-level'];
	} else {
		$output['zoom_level'] = 3;	
	}

    $output['google_api_browser_key']  = sanitize_text_field( $_POST['nwm-google-api-browser-key'] );
    $output['google_api_server_key']  = sanitize_text_field( $_POST['nwm-google-api-server-key'] );
    $output['flightpath']       = isset( $_POST['nwm-flightpath'] ) ? 1 : 0;
	$output['curved_lines']     = isset( $_POST['nwm-curved-lines'] ) ? 1 : 0;		
	$output['round_thumbs']     = isset( $_POST['nwm-round-thumbs'] ) ? 1 : 0;	
	$output['past_color']       = sanitize_text_field( $_POST['nwm-past-color'] );
	$output['future_color']     = sanitize_text_field( $_POST['nwm-future-color'] );
	$output['streetview']       = isset( $_POST['nwm-streetview'] ) ? 1 : 0;	
	$output['control_position'] = ( wp_filter_nohtml_kses( $_POST['nwm-control-position']  == 'left') ) ? 'left' : 'right';	
	$output['control_style']    = ( wp_filter_nohtml_kses( $_POST['nwm-control-style'] == 'small' ) ) ? 'small' : 'large';
	$output['read_more']        = isset( $_POST['nwm-readmore'] ) ? 1 : 0;
    $output['read_more_label']  = sanitize_text_field( $_POST['nwm-readmore-label'] ); 
	$output['location_header']  = isset( $_POST['nwm-location-header'] ) ? 1 : 0;
	$output['content_location'] = ( wp_filter_nohtml_kses( $_POST['nwm-content-location'] == 'slider') ) ? 'slider' : 'tooltip';
    $output['initial_tooltip']  = isset( $_POST['nwm-initial-tooltip'] ) ? 1 : 0;
	$output['latlng_input']     = isset( $_POST['nwm-latlng-input'] ) ? 1 : 0;
    
	nwm_delete_all_transients();
	
	return $output;
	
}

/* Create the dropdown to select which marker is active when the page first loads */
function nwm_zoom_to( $options ) {
	
	$items = array( 
        'first'          => __( 'The first location (default)', 'nwm' ), 
        'schedule_start' => __( 'The last location before your scheduled route starts', 'nwm' ), 
        'last'           => __( 'The last location', 'nwm' )
    );
				   
	$dropdown = '<select id="nwm-zoom-to" name="nwm-zoom-to">';
	
	foreach ( $items as $item => $value ) {
		$selected = ( $options['zoom_to'] == $item ) ? 'selected="selected"' : '';
		$dropdown .= "<option value='$item' $selected>$value</option>";
	}
	
	$dropdown .= "</select>";
	
	return $dropdown;
	
}

/* Create the dropdown for the different map types */
function nwm_map_types( $options ) {
	
	$items = array( 
        'roadmap', 
        'satellite', 
        'hybrid', 
        'terrain' 
    );
	$dropdown = '<select id="nwm-map-type" name="nwm-map-type">';
	
	foreach ( $items as $item => $value ) {
		$selected = ( $options['map_type'] == $value ) ? 'selected="selected"' : '';
		$dropdown .= "<option value='$value' $selected>" . ucfirst( $value ) . "</option>";
	}
	
	$dropdown .= "</select>";
	
	return $dropdown;
	
}

/* Create the dropdown to select the zoom level */
function nwm_zoom_level( $options ) {
					   
	$dropdown = '<select id="nwm-zoom-level" name="nwm-zoom-level">';
	
	for ( $i = 1; $i < 13; $i++ ) {
        $selected = ( $options['zoom_level'] == $i ) ? 'selected="selected"' : '';
		
		switch ( $i ) {
			case '1':
				$zoom_desc = '- World view';
				break;
			case '3':
				$zoom_desc = '- Default';
				break;
			case '12':
				$zoom_desc = '- Roadmap view';
				break;	
			default:
				$zoom_desc = '';		
		}

		$dropdown .= "<option value='$i' $selected>$i $zoom_desc</option>";	
    }
		
	$dropdown .= "</select>";
		
	return $dropdown;
	
}

?>