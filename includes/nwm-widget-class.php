<?php

class NWM_Widget extends WP_Widget {

    function __construct() {
        parent::__construct( 'nwm_widget', __( 'Nomad World Map', 'nwm' ), 
            array( 'description' => __( 'Show your current location in the sidebar.', 'nwm' ), ) 
        );
    }

    /* Frontend of the widget */
    public function widget( $args, $instance ) {

        $title = apply_filters( 'widget_title', $instance['title'] );

        echo $args['before_widget'];
        echo '<div class="nwm-widget">';
        
        if ( !empty( $title ) ) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
      
        /* Auto detect the last location based on the map the user created */
        if ( $instance['location_detection'] == 'auto_location' ) {

            /* Check if a map is selected. Otherwise show an error */
            if ( absint( $instance['map_id'] ) ) {

                /* Check if we there is a transient for the map widget that we can use */
                if ( false === ( $last_location = get_transient( 'nwm_widget_'.$instance['map_id'] ) ) ) {	
                    $last_location = $this->find_last_location( $instance['map_id'] ); 
                    set_transient( 'nwm_widget_'.$instance['map_id'], $last_location, $last_location->transient_lifetime ); 
                }
                
                if ( !empty( $last_location->lat ) ) {
                    $widget_params = array(
                        'latlng'       => $last_location->lat.','.$last_location->lng,
                        'location'     => $last_location->location,
                        'country_code' => $last_location->iso2_country_code
                    ); 
                } else {
                     delete_transient( 'nwm_widget_'.$instance['map_id'] );
                    echo '<p>' . __( 'There is a problem geocoding your location, please check your route on the selected map.', 'nwm' ) . '</p>';  
                }                    
            } else {
                echo '<p>' . __( 'Please select a map in the Nomad World Map widget settings.', 'nwm' ) . '</p>';  
            }  
        } // end auto detect location
            
        /* If the location detection is set to manual, or the display type is set to text_style use the latlng from the provided location */
        if ( $instance['location_detection'] == 'manual_location' ) {
            $widget_params = array(
                'latlng'       => $instance['geocode']['latlng']['lat'].','.$instance['geocode']['latlng']['lng'],
                'location'     => $instance['manual_location'],
                'country_code' => $instance['geocode']['country_code']['short_name']
            );
        } // end manual location
        
        /* Check if we should show a country flags */
        if ( !empty( $widget_params['country_code'] ) && ( $instance['flag'] ) && ( $instance['display_type'] != 'map_style' ) ) {
            $flag_url = NWM_URL . 'img/flags/' . strtolower( $widget_params['country_code'] ) . '.png';
            $flag_css = ' style="background:url(' . $flag_url . ') left center no-repeat; padding-left:25px;"';
        } else {
            $flag_css = '';
        }   
        
        if ( $instance['display_type'] != 'map_style' ) {
            echo '<p id="nwm-widget-location"' . $flag_css . '>' . esc_html( $widget_params['location'] ) . '</p>';
        }
            
        if ( ( !empty( $widget_params['latlng'] ) ) && ( $instance['display_type'] != 'text_style' ) ) {
            $widget_params['zoom'] = $instance['zoom_level'];

            echo '<div id="nwm-map-widget" style="height:200px;"></div>';
            ?>
            <style>
                #nwm-map-widget {
                    margin-bottom:15px;
                }
                
                #nwm-widget-location {
                    margin-bottom: 15px !important;
                }
                
                /* Make sure the map images are not scaled and show a box shadow */
                #nwm-map-widget img, 
                #nwm-map-widget div {
                    max-width:none !important; 
                    box-shadow:none !important;
                    background:none !important;
                }
            </style>

            <script type="text/javascript">
                var nwm_google_src_url = '<?php echo nvm_add_key_to_gmaps_url("//maps.google.com/maps/api/js?callback=handleApiReady"); ?>';
            </script>
            <?php

            wp_enqueue_script( 'nwm-widget', NWM_URL.'js/nwm-widget.js' );
            wp_localize_script( 'nwm-widget', 'nwmWidget', $widget_params );
        }
        
        if ( !empty( $instance['map_description'] ) ) {
            $allowed_html = array(
                'a' => array(
                    'href'  => array(),
                    'title' => array()
                ),
                'strong' => array()
            );
            echo wpautop( wp_kses( stripslashes( $instance['map_description'] ), $allowed_html ) ); 
        }   
        
        echo '</div>';
        echo $args['after_widget'];
    }
    
    public function find_last_location( $map_id ) {
        
        global $wpdb;

        $nwm_route_order = get_option( 'nwm_route_order' );
		$route_order = esc_sql( implode( ',', wp_parse_id_list( $nwm_route_order[$map_id] ) ) );
        $i = 0;
        $last_location = new StdClass;
        $transient_lifetime = 0;
        $remaining_time = '';
        $first_future_date = null;
        $current_index = null;
        
		$nwm_location_data = $wpdb->get_results("
												SELECT nwm_id, lat, lng, location, iso2_country_code, arrival
												FROM $wpdb->nwm_routes
												WHERE nwm_id IN ( $route_order )
												ORDER BY field( nwm_id, $route_order )
												"
											    );


		foreach ( $nwm_location_data as $k => $nwm_location ) {	
		
			/* If the date is in the future, then we need to change the line color on the map */		
			if ( strtotime( $nwm_location->arrival ) > time() ) {
                
                /* Filter out the first arrival date that is set in the future */
				if ( empty( $first_future_date ) ) {
					$first_future_date = $nwm_location->arrival;
				}
                
				/* Get the index of the first stop before the future stop, or in other words. The current stop. */
                if ( empty( $current_index ) ) {
                    $current_index = $i - 1;
                    break;
                }
            }
            
            $i++;
        }
        
        if ( !empty( $nwm_location_data[$current_index] ) ) {
            $last_location = $nwm_location_data[$current_index];
        } else {
            $last_index = count( $nwm_location_data ) - 1;
            $last_location = $nwm_location_data[$last_index];
        }

        /* Only calculate a transient lifetime if we have a future data, otherwise the transient is valid indefinitely */
        if ( ( $first_future_date  !== '0000-00-00 00:00:00' ) && ( !empty( $first_future_date ) ) ) {
            $current_epoch     = time();
            $dt                = new DateTime("@$current_epoch");
            $current_converted = $dt->format('Y-m-d'); 
            $arrival_epoch     = strtotime( $first_future_date );
            $remaining_time    = abs( $current_epoch - $arrival_epoch );
        }

        if ( $remaining_time > 0 ) {
            $transient_lifetime = $remaining_time;
        }
        
        if ( !empty( $nwm_location_data ) ) { 
            $last_location->transient_lifetime = $transient_lifetime;
        }
        
        return $last_location;        
    }
		
    /* Backend of the widget */
    public function form( $instance ) {

        $instance = wp_parse_args((array)$instance,
            array(
                'title' => '',
                'map_id' => '',
                'zoom_level' => '3',
                'display_type' => '',
                'flag' => '',
                'map_description' => '',
                'location_detection' => '',
                'manual_location' => '',
                'latlng' => ''
            )
        );
        $title = strip_tags($instance['title']);
        $selected_map = strip_tags($instance['map_id']);
        $zoom_level = strip_tags($instance['zoom_level']);
        $display_type = strip_tags($instance['display_type']);
        $flag = strip_tags($instance['flag']);
        $map_description = strip_tags($instance['map_description']);
        $location_detection = strip_tags($instance['location_detection']);
        $manual_location = strip_tags($instance['manual_location']);
        $latlng = strip_tags($instance['latlng']);

        $map_ids = get_option( 'nwm_map_ids' );
        
        $display_options = array(
            "map_text_style" => __( 'Show it on the map and as text', 'nwm' ),
            "text_style"     => __( 'Text only', 'nwm' ),
            "map_style"      => __( 'Map only', 'nwm' )
        );
 
        $location_detection_options = array(
            "manual_location" => __( 'I will fill it in manually', 'nwm' ),
            "auto_location"   => __( 'Automatically, use my travel schedule', 'nwm' ),
        );
        ?>

        <p>
            <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
            <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
        </p>
        <?php _e( 'How do you want to display your location in the sidebar?', 'nwm' ); ?>
        <p id="nwm-display-options" class="nwm-widget-labels">
            <select autocomplete="off" name="<?php echo $this->get_field_name( 'display_type' ); ?>" id="<?php echo $this->get_field_id( 'display_type' ); ?>">
                <?php
                foreach (  $display_options as $display_key => $display_option ) {
                    echo '<option value="' . esc_attr( $display_key ) . '"' . ( $display_type == esc_attr( $display_key ) ? 'selected="selected"' : '' ) . '>' . esc_html( $display_option ) . '</option>';
                }
                ?>
            </select>
        </p>
        <p id="nwm-location-flag" <?php if ( $display_type == 'map_style' ) { echo 'style="display:none;"'; } ?>>
            <label for="<?php echo $this->get_field_id( 'flag' ); ?>">
                <input id="<?php echo $this->get_field_id( 'flag' ); ?>" name="<?php echo $this->get_field_name( 'flag' ); ?>" type="checkbox" value="1" <?php checked( '1', $flag ); ?>  />
                <?php _e( 'Show a country flag before your location?', 'nwm' ); ?>
            </label> 
        </p>        
        <?php _e( 'How do you want to determine your current location?', 'nwm' ); ?>
        <p id="nwm-location-detection" class="nwm-widget-labels">
            
            <select autocomplete="off" name="<?php echo $this->get_field_name( 'location_detection' ); ?>" id="<?php echo $this->get_field_id( 'location_detection' ); ?>">
                <?php
                foreach ( $location_detection_options as $location_key => $location_option ) {
                    echo '<option value="' . esc_attr( $location_key ) . '"' . ( $location_detection == esc_attr( $location_key ) ? 'selected="selected"' : '' ) . '>' . esc_html( $location_option ) . '</option>';
                }
                ?>
            </select>  
        </p>                
        <p id="nwm-manually" <?php if ( $location_detection == 'auto_location' ) { echo 'style="display:none;"'; } ?>>
            <label for="<?php echo $this->get_field_id( 'manual_location' ); ?>"><?php _e( 'Your location:', 'nwm' ); ?></label> 
            <input class="widefat" id="<?php echo $this->get_field_id( 'manual_location' ); ?>" name="<?php echo $this->get_field_name( 'manual_location' ); ?>" type="text" value="<?php echo esc_attr( $manual_location  ); ?>" placeholder="<?php _e( 'City, Country', 'nwm' ); ?>" />
        </p>
        <p id="nwm-automatically" <?php if ( $location_detection == 'manual_location' ) { echo 'style="display:none;"'; } ?>>
            <label for="<?php echo $this->get_field_id( 'map_id' ); ?>"><?php _e( 'Select the map that should be used to determine your current location:', 'nwm' ); ?></label> 
            <select autocomplete="off" name="<?php echo $this->get_field_name( 'map_id' ); ?>" id="<?php echo $this->get_field_id( 'map_id' ); ?>">
                <option value=""><?php _e( 'Select map', 'nwm' ); ?></option>
                <?php 
                foreach ( $map_ids as $map_id => $map_name ) {
                    echo '<option value="' . esc_attr( $map_id ) . '"' . ( $map_id == $selected_map ? 'selected="Selected"' : '' ) .  '>' . esc_html( $map_name ) . '</option>';
                }
                ?>
            </select>
        </p>
        <p id="nwm-zoom-level" <?php if ( $display_type == 'text_style' ) { echo 'style="display:none;"'; } ?>>
            <label for="<?php echo $this->get_field_id( 'zoom_level' ); ?>"><?php _e( 'Zoom level for the map', 'nwm' ); ?></label> 
            <select autocomplete="off" name="<?php echo $this->get_field_name( 'zoom_level' ); ?>" id="<?php echo $this->get_field_id( 'zoom_level' ); ?>">
                <?php 
                for ( $i = 1; $i < 13; $i++ ) {
                    $selected = ( $zoom_level == $i ) ? 'selected="selected"' : '';

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

                    echo "<option value='$i' $selected>$i $zoom_desc</option>";	
                }
                ?>
            </select>
        </p>
        <p id="nwm-widget-description">
            <label for="<?php echo $this->get_field_id( 'map_description' ); ?>"><?php _e( 'Optional text under your location:', 'nwm' ); ?></label> 
            <textarea rows="5" placeholder="<?php _e( 'Here you can add more text or link back to a map page.', 'nwm' ) ?>" name="<?php echo $this->get_field_name( 'map_description' ); ?>" id="<?php echo $this->get_field_id( 'map_description' ); ?>"><?php echo esc_textarea( $map_description ); ?></textarea>
            <em><?php _e( 'Link and strong tags are allowed.', 'nwm' ) ?></em>
        </p>
        <?php 
    }
	
    /* Update the widget data */
    public function update( $new_instance, $old_instance ) {
        
        $instance = array();
        
        $instance['title']              = ( !empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
        $instance['location_detection'] = strip_tags( $new_instance['location_detection'] );
        $instance['zoom_level']         = absint( $new_instance['zoom_level'] );
        $instance['map_id']             = ( !empty( $new_instance['map_id'] ) ) ? absint( $new_instance['map_id'] ) : '';
        $instance['display_type']       = strip_tags( $new_instance['display_type'] );
        $instance['flag']               = strip_tags( $new_instance['flag'] );
        $instance['manual_location']    = ( !empty( $new_instance['manual_location'] ) ) ? strip_tags( $new_instance['manual_location'] ) : '';
        $instance['geocode']            = ( !empty( $new_instance['manual_location'] ) ) ? nwm_geocode_location( $new_instance['manual_location'] ) : '';        
        $instance['map_description']    = ( !empty( $new_instance['map_description'] ) ) ? $new_instance['map_description'] : '';

        return $instance;
    }
}

add_action( 'widgets_init',
     create_function( '', 'return register_widget( "NWM_Widget" );' )
);

