<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'nwm_list', 'nwm_show_list' );
add_shortcode( 'nwm_map', 'nwm_show_map' );

/* show all routes on the map */
function nwm_show_map( $atts, $content = null ) {
	
	global $wpdb;
	$data = array();
    /* Required to keep track of the amount of maps on the page */
    static $map_count;
    
    if ( !$map_count ) {
        $map_count = 0;
    }
	
	extract( shortcode_atts( array (
        "width"   => '',
        "height"  => '',
        "id"      => '',
        "zoom"    => '',
        "content" => '',
        "maptype" => '',
        "lines"   => ''  
	), $atts ) ); 
	
	/* Check if we have a valid id, otherwise just set it to 1 (default map) */
	if ( !absint( $id ) ) {
		$id = 1;	
	}	
    
    $output = '';
	$settings = get_option( 'nwm_settings' );
    
	/* 
	Check if the content type is set through a shortcode.
	*/	
	if ( !empty( $content ) ) {
		$allowed_content = array( 
            'tooltip', 
            'slider'
        );
		
		if ( in_array( $content, $allowed_content ) ) {
			$map_settings['contentLocation'] = $content;
		}
	} else {
        $map_settings['contentLocation'] = $settings['content_location'];
    }

	/* Check if there is an existing transient we can use */
	if ( false === ( $map_data = get_transient( 'nwm_locations_'.$id ) ) ) {	
		$nwm_route_order = get_option( 'nwm_route_order' );
		$date_format     = get_option( 'date_format' );
		$route_order     = esc_sql( implode( ',', wp_parse_id_list( $nwm_route_order[$id] ) ) );
		$i               = 0;	
		$json_data       = '';
		$first_future_date = null;

		$nwm_location_data = $wpdb->get_results("
												SELECT nwm_id, post_id, thumb_id, lat, lng, location, arrival, departure
												FROM $wpdb->nwm_routes
												WHERE nwm_id IN ( $route_order )
												ORDER BY field( nwm_id, $route_order )
												"
											    );	
        
		foreach ( $nwm_location_data as $k => $nwm_location ) {	
			$future = '';
		
			/* If the date is in the future, then we need to change the line color on the map */		
			if ( strtotime( $nwm_location->arrival ) > time() ) {
				$future = true;
				
				/* Filter out the first arrival date that is set in the future */
				if ( empty( $first_future_date ) ) {
					$first_future_date = $nwm_location->arrival;
				}
				
				/* See if we need to zoom to the first item of the future route */
				if ( $settings['zoom_to'] == 'schedule_start' ) {
					if ( empty( $zoom_index ) ) {
						$zoom_index = $i - 1;
					}
				}
			}
			
			/* If we have no post_id get the data from the custom table*/
			if ( !$nwm_location->post_id ) {
				$nwm_custom_data = $wpdb->get_results( 'SELECT content, url, title FROM ' . $wpdb->nwm_custom . ' WHERE nwm_id = ' . absint( $nwm_location->nwm_id ) . '' );
				$custom_content  = '';	
				$custom_url      = '';
				$custom_title    = '';	

				if ( count( $nwm_custom_data ) ) {
					$custom_content = strip_tags( stripslashes( $nwm_custom_data[0]->content ) );
					$custom_url     = esc_url( $nwm_custom_data[0]->url );
					$custom_title   = strip_tags( stripslashes( $nwm_custom_data[0]->title ) );
				}

				$post_data = array( 
					'nwm_id'    => (int) $nwm_location->nwm_id,
					'content'   => $custom_content,
					'title' 	=> $custom_title,
					'url' 		=> $custom_url,
					'location'  => esc_html( $nwm_location->location ),
					'thumb' 	=> nwm_get_thumb( $nwm_location->thumb_id ),
					'date' 		=> '',
					'arrival' 	=> esc_html( nwm_convert_date_format( $date_format, $nwm_location->arrival ) ),
					'departure' => esc_html( nwm_convert_date_format( $date_format, $nwm_location->departure ) ),
					'future'    => esc_html( $future )
				);			   
								   
			} else {
				$publish_date = get_the_time( $date_format, $nwm_location->post_id );
				$post_data    = nwm_collect_post_data( $nwm_location, $publish_date, $future, $date_format );
			}

			$data[] = array( 
				'lat'  => $nwm_location->lat,
			    'lng'  => $nwm_location->lng,
			 	'data' => $post_data
			);	
									
			$i++;
		} // end foreach
		
		if ( empty( $zoom_index ) ) {
			if ( $settings['zoom_to'] == 'first' ) {
				$zoom_index = 0;
			} else {
				$zoom_index = $i - 1;
			}
		}
			
		/* 
		Check if a zoomlevel is set through the shortcode, it has to be between 1 or 12. 
		*/
		if ( absint( $zoom ) ) {
			if ( $zoom >= 1 && $zoom <= 12 ) {
				$map_settings['zoomLevel'] = $zoom;
			} else {
                $map_settings['zoomLevel'] = $settings['zoom_level'];
            }
		} else {
            $map_settings['zoomLevel'] = $settings['zoom_level'];
        }
        
        /* 
        Check if the flightpath (lines) is set through the shortcode. 
        */
        if ( is_numeric( $lines ) ) {
            $map_settings['lines'] = $lines;
        } else {
            $map_settings['lines'] = $settings['flightpath'];
        }
        
        /* 
        Check if the map type is set through the shortcode, 
        */	
        if ( !empty( $maptype ) ) {
            $allowed_maptypes = array( 
                'roadmap', 
                'satellite', 
                'hybrid', 
                'terrain' 
            );

            if ( in_array( $maptype, $allowed_maptypes ) ) {
                $map_settings['mapType'] = $maptype; 
            } else {
                $map_settings['mapType'] = $settings['map_type'];
            }
        } else {
            $map_settings['mapType'] = $settings['map_type'];
        }
				
        $map_settings['zoomToIndex'] = $zoom_index;
        $map_data = array( 
            'locationData' => $data,
            'settings' 	   => $map_settings
        );

		$transient_lifetime = '';

		/* Calculate the duration of the transient lifetime  */
		if ( !empty( $first_future_date ) ) {
			$current_epoch     = time();
			$dt                = new DateTime("@$current_epoch");
			$current_converted = $dt->format('Y-m-d'); 
			$arrival_epoch     = strtotime( $first_future_date );
			$remaining_time    = abs( $current_epoch - $arrival_epoch );
					
			if ( $remaining_time > 0 ) {
				$transient_lifetime = $remaining_time;
			} else {
				$transient_lifetime = '';
			}
		}
						 
		set_transient( 'nwm_locations_'.$id, $map_data, $transient_lifetime ); 
	}
  
    $frontend_data['location_data'] = $map_data;
    
    if ( $map_count == 0 ) {
        $output .= '<!-- Nomad World Map - http://nomadworldmap.com -->';
        $frontend_data['settings'] = $settings;
    }
        
	/* Load the required front-end scripts and set the js data */
	nwm_frontend_scripts( $frontend_data, $map_count );
		
	if ( ( int ) $width ) { 
		$width = 'style="width:' . $width . 'px"';
	} else {
		$width = '';	
	}
	
	if ( ( int ) $height ) { 
		$height = 'style="height:'  .$height . 'px"';
	} else {
		$height = '';	
	}
	
    $output .= '<div class="nwm-wrap" ' . $width . '>'; 
    $output .= '<div id="nwm-map-' . $map_count . '" class="nomad-world-map" ' . $height . '></div>';

    /* Check if we need to output the slider html */
    if ( $map_settings['contentLocation'] != 'tooltip' ) {
        $output .= '<div class="nwm-destination-list">';	
        $output .= '<div class="nwm-back nwm-control"></div>';	
        $output .= '<ul></ul>';	
        $output .= '<div class="nwm-forward nwm-control"></div>';	
        $output .= '</div>';	
    }

    $output .= '</div>';
    
    $map_count++;

	return $output;	
	
}

/* Show a list of visited locations */
function nwm_show_list( $atts, $content = null ) {

	global $wpdb;
	
	extract( shortcode_atts( array (
	  "id"    => '',
      "order" => '',
	  "dates" => ''
	), $atts ) ); 
		
	/* Check if we have a valid id, otherwise just set it to 1 (default map) */
	if ( !absint( $id ) ) {
		$id = 1;	
	}
    
    /* If no valid sort value exists, defaul to ASC */
    if ( ( strtoupper( $order ) !== 'ASC' ) && ( strtoupper( $order ) !== 'DESC' ) ) {
        $order = 'ASC';
    }
    
	/* Check if there is an existing transient we can use */
	if ( false === ( $route_list = get_transient( 'nwm_route_list_'.$id ) ) ) {	
		$settings        = get_option( 'nwm_settings' );
		$nwm_route_order = get_option( 'nwm_route_order' );
		$date_format     = get_option( 'date_format' );
		$route_order     = esc_sql( implode( ',', wp_parse_id_list( $nwm_route_order[$id] ) ) );

		$nwm_location_data = $wpdb->get_results("
												SELECT nwm_id, post_id, location, arrival, departure
												FROM $wpdb->nwm_routes
												WHERE nwm_id IN ( $route_order )
												ORDER BY field ( nwm_id, $route_order ) $order
												"
											    );	
        
		foreach ( $nwm_location_data as $k => $nwm_location ) {	
			$future = '';
		
			/* Check if we have a future date */		
			if ( strtotime( $nwm_location->arrival ) > time() ) {
				$future = true;
			}
			
			$post_data = array( 
                'location'  => $nwm_location->location,
				'departure' => nwm_convert_date_format( $date_format, $nwm_location->departure ),
				'future'    => $future
			);
			
			/* If we have no post_id get the data from the custom table*/
			if ( !$nwm_location->post_id ) {
				$nwm_custom_data = $wpdb->get_results( 'SELECT url FROM ' . $wpdb->nwm_custom . ' WHERE nwm_id = ' . absint( $nwm_location->nwm_id ) . '' );
				$custom_url = '';

				if ( count( $nwm_custom_data ) ) {
					$custom_url = esc_url( $nwm_custom_data[0]->url );
				}
				
				$post_data_part = array( 
                    'url'     => $custom_url,
					'arrival' => nwm_convert_date_format( $date_format, $nwm_location->arrival ),
				);	
			} else {
				$arrival_date = nwm_convert_date_format( $date_format, $nwm_location->arrival );
				
				/* If no custom arrival date is set, use the publish date */
				if ( empty( $arrival_date ) ) {
					$arrival_date = get_the_time( $date_format, $nwm_location->post_id );
				}
				
				$post_data_part = array( 
                    'url'     => get_permalink( $nwm_location->post_id ),
					'arrival' => $arrival_date,
				);
			}

			$list_data[] = array_merge( $post_data, $post_data_part );	
		} // end foreach
		
		/* Make sure we have some data before proceeding */		
		if ( !empty( $list_data ) ) {
			$i = 1;
			
			$route_list = '<table id="nwm-route-list" width="100%" border="0" cellspacing="0">';
			$route_list .= '<thead>';
			$route_list .= '<th scope="col"></th>';
			$route_list .= '<th scope="col">' . __( 'Location', 'nwm' ) . '</th>';
			
			/* If the date option attribute set in the shortcode, show the headers */
			if ( !empty( $dates ) ) {
				switch ( $dates ) {
					case 'all':
						$route_list .= '<th scope="col">' . __( 'Arrival', 'nwm' ) . '</th>';
						$route_list .= '<th scope="col">' . __( 'Departure', 'nwm' ) . '</th>';
						break;
					case 'arrival':
						$route_list .= '<th scope="col">' . __( 'Arrival', 'nwm' ) . '</th>';
						break;	
					case 'departure':
						$route_list .= '<th scope="col">' . __( 'Departure', 'nwm' ) . '</th>';
						break;
				}				
			}
			
			$future_class = '';
			$route_list .= '</thead>';		
			$route_list .= '<tbody>';		
			
			foreach ( $list_data as $list_item => $list_value ) {
				
				/* Check if we need to add the css class to color the future td */ 
				if ( empty( $future_class ) ) {
					$future_class = ( $list_value['future'] ) ? 'class="nwm-future-color"' : '' ;
				}
				
				$route_list .= '<tr ' . $future_class . '>';
				$route_list .= '<td class="nwm-location-count">' . $i . '</td>';
				
				/* Check if we need to show the url */
				if ( !empty( $list_value['url'] ) ) {
					$route_list .= '<td><a href="' . esc_url( $list_value['url'] ) . '">' . esc_html( $list_value['location'] ) . '</a></td>';
				} else {
					$route_list .= '<td>' . esc_html( $list_value['location'] ) . '</td>';
				}
				
				/* Check if we need to show arrival / departure dates */
				if ( !empty( $dates ) ) {
					$arrival_date = ( !empty( $list_value['arrival'] ) ) ? $list_value['arrival'] : ' - ';
					$departure_date = ( !empty( $list_value['departure'] ) ) ? $list_value['departure'] : ' - ';
					
					switch ( $dates ) {
						case 'all':
							$route_list .= '<td>' . esc_html( $arrival_date ) . '</td>';
							$route_list .= '<td>' . esc_html( $departure_date ) . '</td>';
							break;
						case 'arrival':
							$route_list .= '<td>' . esc_html( $arrival_date ) . '</td>';
							break;	
						case 'departure':
							$route_list .= '<td>' . esc_html( $departure_date ) . '</td>';
							break;
					}				
				}					
				
				$route_list .= '</tr>';
				$i++;
			} // end foreach
			
			$route_list .= '</tbody>';
			$route_list .= '</table>';					
		}
		
		set_transient( 'nwm_route_list_'.$id, $route_list ); 
	}
	
	return $route_list;
	
}

/* Collect the excerpt, thumbnail and permalink that belongs to the $post_id */
function nwm_collect_post_data( $nwm_location, $publish_date, $future, $date_format ) {	
	
	$excerpt   = nwm_get_post_excerpt( $nwm_location->post_id );
	$permalink = get_permalink( $nwm_location->post_id );
	$title     = get_the_title( $nwm_location->post_id );
	
	$nwm_post_data = array( 
		'nwm_id'    => ( int ) $nwm_location->nwm_id,
		'thumb'     => esc_url( nwm_get_thumb( $nwm_location->thumb_id ) ),
		'url'       => esc_url( $permalink ),
		'content'   => esc_html( $excerpt ),
		'title'     => esc_html( $title ),
		'location'  => esc_html( $nwm_location->location ),
		'date'      => esc_html( $publish_date ),
		'arrival'   => esc_html( nwm_convert_date_format( $date_format, $nwm_location->arrival ) ),
		'departure' => esc_html( nwm_convert_date_format( $date_format, $nwm_location->departure ) ),
		'future'    => esc_html( $future )
	  );
	
	return $nwm_post_data;
	
}

/* Get the thumb src based on the thumb_id */
function nwm_get_thumb( $thumb_id ) {	
	$thumb = wp_get_attachment_image_src( $thumb_id, 'thumbnail' );	
	
	if ( !empty( $thumb[0] ) ) {
		return $thumb[0];
	}
}

/* Change the date format from example 2013-06-28 00:00:00 into M j, Y */
function nwm_convert_date_format( $date_format, $route_date ) {
	if ( $route_date != '0000-00-00 00:00:00' ) {
        return mysql2date( $date_format, $route_date, true );
	}
}

/* 
Get the post excerpt outside of the loop 
from http://www.uplifted.net/programming/wordpress-get-the-excerpt-automatically-using-the-post-id-outside-of-the-loop/
*/
function nwm_get_post_excerpt ( $post_id ) {

	$the_post       = get_post( $post_id );
	$the_excerpt    = $the_post->post_content;
	$excerpt_length = 25;
	$the_excerpt    = strip_tags( strip_shortcodes( $the_excerpt ) );
	$words          = explode( ' ', $the_excerpt, $excerpt_length + 1 );
	
	if( count ( $words ) > $excerpt_length ) :
		array_pop( $words );
		array_push( $words, '…' );
		$the_excerpt = implode( ' ', $words ) ;
	endif;
		$the_excerpt = $the_excerpt;
	return $the_excerpt;	
	
}

/* Load the front-end scripts and localize the required js data */
function nwm_frontend_scripts( $frontend_data, $map_count ) {
    
	wp_enqueue_style( 'nwm', NWM_URL . 'css/styles.css', false );
	wp_enqueue_script( 'nwm-gmap', ( nvm_add_key_to_gmaps_url("//maps.google.com/maps/api/js") ),'' ,'' ,true );
	wp_enqueue_script( 'nwm-gmap3', NWM_URL . 'js/gmap3.min.js', array( 'jquery' ) ); /* the not minified version of gmap3 library is in the js folder -> gmap3.js */
	wp_enqueue_script( 'nwm-gmap-markers', NWM_URL . 'js/nwm-gmap3.js' );

    /* We only need to add the general map settings on the first loop */
    if ( $map_count == 0 ) {
         $params = array(
             'curvedLines' 	   => $frontend_data['settings']['curved_lines'],
             'thumbCircles'    => $frontend_data['settings']['round_thumbs'],
             'pastLineColor'   => $frontend_data['settings']['past_color'],
             'futureLineColor' => $frontend_data['settings']['future_color'],
             'path' 		   => NWM_URL,
             'streetView' 	   => $frontend_data['settings']['streetview'],
             'controlPosition' => $frontend_data['settings']['control_position'],
             'controlStyle'    => $frontend_data['settings']['control_style'],
             'readMore' 	   => $frontend_data['settings']['read_more'],
             'readMoreLabel'   => sanitize_text_field( stripslashes( $frontend_data['settings']['read_more_label'] ) ),
             'locationHeader'  => $frontend_data['settings']['location_header'],
             'hideTooltip'  => $frontend_data['settings']['initial_tooltip']
         );

         wp_localize_script( 'nwm-gmap-markers', 'nwmSettings', $params );
    }     
   
    wp_localize_script( 'nwm-gmap-markers', 'nwmMap_' . $map_count, $frontend_data['location_data'] );

}

?>