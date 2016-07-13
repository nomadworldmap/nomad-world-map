<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* Build the edit page with the map and show a list of the existing routes */
function nwm_map_editor() {
	$nwm_map_ids            = get_option( 'nwm_map_ids' );
	$collected_destinations = nwm_map_editor_data( $nwm_map_id = 1 ); // 1 is the id of the default map					
    $options                = get_option( 'nwm_settings' );
    
    if ( $options['latlng_input'] ) {
        $extra_class = 'nwm-latlng-input';
    } else {
        $extra_class = '';
    }
	?>
    
    <div id="nwm-wrap" class="wrap <?php echo $extra_class; ?>">
        <h2>Nomad World Map</h2>
		
        <div class="nwn-new-destination-wrap">
        <p id="nwm-map-selector">
            <label for="nwm-map-list"><?php _e( 'Current map:', 'nwm' ); ?></label>
            <select id="nwm-map-list">
                <?php
                foreach( $nwm_map_ids as $map_key => $map_value ) {
                ?>
                    <option value="<?php echo $map_key; ?>"><?php echo $map_key. ' - ' .$map_value; ?></option>
                <?php
                }
                ?>
            </select>
            <input id="nwm-map-list-nonce" type="hidden" value="<?php echo wp_create_nonce('nwm_map_list'); ?>"  />
        </p>
        <ul id="nwm-menu">
            <li class="nwm-active-item"><a href="#nwm-add-destination"><?php _e( 'Add location', 'nwm' ); ?></a></li>
            <li><a href="#nwm-edit-destination"><?php _e( 'Edit location', 'nwm' ); ?></a></li>                    
        </ul>
                
        <div id="nwm-destination-wrap" class="destination postbox"> 
            <div id="nwm-add-destination" class="nwm-tab nwm-active inside" data-nonce-save="<?php echo wp_create_nonce('nwm_nonce_save'); ?>">
                <form id="nwm-form">
                    <p><label for="nwm-searched-location"><?php _e( 'City / Country:', 'nwm' ); ?></label> 
                       <input id="nwm-searched-location" class="textinput" type="text" name="nwm-searched-location" value="" />
                       <input id="find-nwm-location" class="button-primary" type="button" name="text" value="<?php _e( 'Set', 'nwm' ); ?>" />
                       <em class="nwm-desc"><?php _e( 'You can drag the red marker to a specific location', 'nwm' ); ?></em>

                        <?php 
                            if ( $options['latlng_input'] ) {
                            ?>
                               <label id='nwm-latlng-label' for='nwm-latlng'><?php _e( 'Coordinates:', 'nwm' ); ?></label>
                               <input id="nwm-latlng" class="textinput" type="text" name="nwm-latlng" value="" />
                               <input id="preview-nwm-latlng" class="button-primary" type="button" name="text" value="<?php _e( 'Preview', 'nwm' ); ?>" />
                            <?php
                            } else {
                            ?>    
                                <input id="nwm-latlng" type="hidden" name="nwm-latlng" value="" />
                            <?php
                            }
                        ?>
                       
                       <input id="nwm-latlng" type="hidden" name="nwm-latlng" value="" />
                       <input id="nwm-country-code" type="hidden" name="nwm-country-code" value="" />
                    </p>
                    
                    <div id="nwm-marker-content">
                        <p class="nwm-marker-wrap"><label for="nwm-marker-content-option"><?php _e( 'Location content:', 'nwm' ); ?></label> 
                           <select id="nwm-marker-content-option">
                                <option selected="selected" value="nwm-blog-excerpt"><?php _e( 'Post excerpt', 'nwm' ); ?></option> 
                                <option value="nwm-custom-text"><?php _e( 'Custom content', 'nwm' ); ?></option> 
                                <option value="nwm-travel-schedule"><?php _e( 'Travel schedule', 'nwm' ); ?></option> 
                           </select>
                        </p>
                        <div id="nwm-blog-excerpt" class="nwm-blog-title nwm-marker-option">
                            <label for="nwm-post-title"><?php _e( 'Title or ID of the post you want to link to:', 'nwm' ); ?></label>
                            <input id="nwm-post-title" type="text" class="textinput"> <input id="find-nwm-title" class="button-primary" type="button" name="text" value="<?php _e( 'Search', 'nwm' ); ?>" />
                            <div id="nwm-search-link"><?php _e( 'Link: ', 'nwm' ); ?> <span></span></div>
                             <input id="nwm-search-nonce" type="hidden" value="<?php echo wp_create_nonce('nwm_nonce_search'); ?>"  />
                        </div>
                        
                        <div id="nwm-custom-text" class="nwm-marker-option nwm-hide">
                            <p><label for="nwm-custom-title"><?php _e( 'Title:', 'nwm' ); ?></label><input id="nwm-custom-title" type="text" class="textinput"></p>
                            <p><label for="nwm-custom-url"><?php _e( 'Link:', 'nwm' ); ?></label><input id="nwm-custom-url" type="url" placeholder="http://" class="textinput"></p>
                            <p class="nwm-textarea-wrap">
                                <label for="nwm-custom-desc"><?php _e( 'Description:', 'nwm' ); ?></label>
                                <textarea id="nwm-custom-desc" data-length="25" cols="5" rows="5"></textarea>
                                <em id="char-limit" class="nwm-desc"><?php _e( 'Keep it short, 25 words remaining.', 'nwm' ); ?></em>
                            </p>
                        </div>
                    </div>
                    
                    <div id="nwm-location-position">
                        <p>
                            <label for="nwm-position"><?php _e( 'Location position:', 'nwm' ); ?></label> 
                            <select id="nwm-position">
                                <option value="0" selected="selected"><?php _e( 'After the last item', 'nwm' ); ?></option>
                                <?php 
                                $x = 1;
                                if ( $collected_destinations ) {
                                    foreach ( $collected_destinations as $k => $nwm_destination ) {
                                        echo '<option value ="' . $x . '">' . __( 'Before', 'nwm' ) . ' ' . $x . ' - ' . esc_html( $nwm_destination['data']['location'] ) . '</option>';
                                        $x++;
                                    }
                                }
                                ?>	
                             </select>
                        </p>
                    </div>
                    
                    <div id="nwm-thumb-wrap">
                        <p>
                        	<strong><?php _e( 'Thumbnail', 'nwm' ); ?></strong>
                            <span class="nwm-thumb nwm-circle"></span>
                        </p>
                        <div>
                            <input id="nwm-media-upload" class="button-primary" type="button" name="text" value="<?php _e( 'Change thumbnail', 'nwm' ); ?>" />
                            <input id="nwm-reset-thumb" class="button-primary" type="button" name="text" value="<?php _e( 'Use default', 'nwm' ); ?>" />
                        </div> 
                    </div>
                    
                    <div class="nwm-dates">
                        <p><strong><?php _e( 'Travel dates', 'nwm' ); ?></strong></p>
                        <div>
                            <label for="nwm-from-date"><?php _e( 'Arrival:', 'nwm' ); ?></label>
                            <input type="text" placeholder="<?php _e( 'optional', 'nwm' ); ?>" id="nwm-from-date" />
                            <input type="hidden" name="from_date" />
                        </div>
                        <div>
                            <label for="nwm-till-date"><?php _e( 'Departure:', 'nwm' ); ?></label>
                            <input type="text" placeholder="<?php _e( 'optional', 'nwm' ); ?>" id="nwm-till-date" />
                            <input type="hidden" name="till_date" />
                        </div>
                    </div>
                    <p class="nwm-date-desc"><em class="nwm-desc"><?php _e( 'If no dates are set, then the publish date of the linked post is shown as the travel date.', 'nwm' ); ?></em></p>
                    <p><input id="nwm-add-trip" type="submit" name="nwm-add-trip" class="button-primary" value="<?php _e( 'Save', 'nwm' ); ?>" /></p>
                    <input id="nwm-post-id" type="hidden" name="nwm-post-id" value="" />
                    <input id="nwm-post-type" type="hidden" name="nwm-post-type" value="" />
                </form>      
            </div>   
            <div id="nwm-edit-destination" class="nwm-tab inside">
              <p>
                <select id="nwm-edit-list">
                    <option selected="selected"><?php _e( '- Select destination to edit -', 'nwm' ); ?></option>
                    <?php 
                    $x = 1;
                    if ( $collected_destinations ) {
                        foreach ( $collected_destinations as $k => $nwm_destination ) {
                            echo '<option value ="' . esc_attr( $nwm_destination['data']['nwm_id'] ) . '"> ' . $x . ' - ' . esc_html( $nwm_destination['data']['location'] ) . '</option>';
                            $x++;
                        }
                    }
                    ?>	
                 </select>
              </p>
           </div>
        </div>
            <div class="gmap-wrap">
            	<div id="gmap-nwm"></div>
            </div>
            <div id="nwm-preload-img" class="nwm-hide"><img class="nwm-preloader" alt="preloader" src="<?php echo plugins_url( '/img/ajax-loader.gif', __FILE__ ); ?>"/></div>
    	</div>
                
        <div class="nwn-current-destinations-wrap postbox">            
        	<table id="nwm-destination-list" width="100%" border="0" cellspacing="0" data-map-id="1" data-nonce-sort="<?php echo wp_create_nonce( 'nwm_nonce_sort' ); ?>">
            	<thead>
                    <th scope="col" class="nwm-order"><?php _e( 'Order', 'nwm' ); ?></th>
                    <th scope="col"><?php _e( 'Location', 'nwm' ); ?></th>
                    <th scope="col"><?php _e( 'Url', 'nwm' ); ?></th>
                    <th scope="col"><?php _e( 'Arrival', 'nwm' ); ?></th>
                    <th scope="col"><?php _e( 'Departure', 'nwm' ); ?></th>
                    <th scope="col"><?php _e( 'Thumbnail', 'nwm' ); ?></th>
                    <th scope="col"></th>
                </thead>
                <tbody>
                <?php
				if ( $collected_destinations ) {
					echo nwm_build_tr_list( $collected_destinations );
				}
				?>
            	</tbody>
            </table>
        </div>
    </div>
    <?php	
	
}

function nwm_build_tr_list( $collected_destinations ) {
	
	$i = 1;
	$output = '';

	foreach ( $collected_destinations as $k => $nwm_location ) {
		if ( !$nwm_location['data']['post_id'] ) {
			$nwm_load_nonce = '<input type="hidden" name="load_nonce" value="'. wp_create_nonce( 'nwm_nonce_load_' . $nwm_location['data']['nwm_id'] ) .'" />';
		} else {
			$nwm_load_nonce = '';	
		}
		
		if ( $nwm_location['data']['arrival'] != '0000-00-00 00:00:00' ) {
			$arrival_date = '<input type="hidden" name="arrival_date" value="'. esc_attr( trim( str_replace("00:00:00", '', $nwm_location['data']['arrival'] ) ) ) .'" />';
		} else {
			$arrival_date = '';
		}
		
		if ( $nwm_location['data']['departure'] != '0000-00-00 00:00:00' ) {
			$departure_date = '<input type="hidden" name="departure_date" value="'. esc_attr( trim( str_replace("00:00:00", '', $nwm_location['data']['departure'] ) ) ) .'" />';
		} else {
			$departure_date = '';
		}	
		
		if ( $nwm_location['data']['schedule'] ) {
			$travel_schedule = 'data-travel-schedule="1"';
		} else {
			$travel_schedule = '';	
		}
		
		if ( $nwm_location['data']['url'] ) {
			$url = '<a href="'. esc_url( $nwm_location['data']['url'] ) .'" title="'. esc_url( $nwm_location['data']['url'] ) .'">' . esc_url( $nwm_location['data']['url']  ) .'</a>';
		} else {
			$url = '';
		}
		
		if ( $nwm_location['data']['thumb_id'] ) {
			$thumb = '<img class="nwm-circle" src="'. esc_url( $nwm_location['data']['thumb_url'] ) .'" data-thumb-id="'. esc_attr( $nwm_location['data']['thumb_id'] ). '" width="24" height="24" />';
		} else {
			$thumb = '';	
		}
        
        if ( $nwm_location['data']['country_code'] ) {
            $flag_url = '<img src="' . NWM_URL . 'img/flags/' . strtolower( esc_attr( $nwm_location['data']['country_code'] ) ) . '.png" />'; 
        } else {
            /* If we don't have a county code yet, try to get one */
            $response = nwm_geocode_location( $nwm_location['data']['location'] );
            nwm_update_country_code( $response['country_code']['short_name'], $nwm_location['data']['nwm_id'] );
            $flag_url = '<img src="' . NWM_URL . 'img/flags/' . strtolower( esc_attr( $response['country_code']['short_name'] ) ) . '.png" />'; 
        }
        
        /* Select the correct country code */
        $country_code = ( $nwm_location['data']['country_code'] ) ? $nwm_location['data']['country_code'] : $response['country_code']['short_name'];
        
		$output .= '<tr '. $travel_schedule .' data-nwm-id="'. esc_attr( $nwm_location['data']['nwm_id'] ) . '"data-country="' . esc_attr( $country_code ) . '" data-latlng="' . esc_attr( $nwm_location['lat'] ) . ',' . esc_attr( $nwm_location['lng'] ) . '" data-post-id="'. esc_attr( $nwm_location['data']['post_id'] ) .'">'."\n"; 	
		$output .= '<td class="nwm-order"><span>' . $i .'</span></td>'."\n";
		$output .= '<td class="nwm-location">' . $flag_url . ' ' .  esc_html( $nwm_location['data']['location'] ) .'</td>'."\n";
		$output .= '<td class="nwm-url">'. $url .'</td>'."\n";
		$output .= '<td class="nwm-arrival">'. $arrival_date .' <span>'. esc_html( $nwm_location['data']['arrival_formated'] ) .'</span></td>'."\n";
		$output .= '<td class="nwm-departure">'. $departure_date .' <span>'. esc_html( $nwm_location['data']['departure_formated'] ) .'</span></td>'."\n";	
		$output .= '<td class="nwm-thumb-td">'. $thumb .'</td>'."\n";						
		$output .= '<td class="nwm-btn">
						<input class="delete-nwm-destination button" type="button" name="text" value="' . __( 'Delete', 'nwm' ) . '" /> 
						<input type="hidden" name="delete_nonce" value="'. wp_create_nonce( 'nwm_nonce_delete_'.$nwm_location['data']['nwm_id'] ) .'" />
						<input type="hidden" name="update_nonce" value="'. wp_create_nonce( 'nwm_nonce_update_'.$nwm_location['data']['nwm_id'] ) .'" /> ' 
						.$nwm_load_nonce. 
					'</td>'."\n";			
		$output .= '</tr>'."\n";
		
		$i++;
	}
	
	return $output;
	
}

?>