<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* Manage and create multiple maps that can be used in the editor */
function nwm_manage_maps() {
	
	$nwm_map_ids     = get_option( 'nwm_map_ids' );
	$nwm_route_order = get_option( 'nwm_route_order' );
	$msg_type        = 'success';
	?>
    
    <div id="nwm-wrap" class="wrap">
    <h2><?php _e( 'Manage Maps', 'nwm' ); ?></h2>
   	<?php
	
	/* Check if we just finished an action */
    $action = ( isset( $_GET['action'] ) ) ? $_GET['action'] : '';
    
	if ( $action ) {
		switch ( $action ) {
			case 'delete_entries':
				$msg = __( 'Map entries successfully removed', 'nwm' );
				break;
			case 'delete_map':
				$msg = __( 'Map(s) successfully deleted', 'nwm' );
				break;
			case 'name_updated':
				$msg = __( 'Name updated', 'nwm' );
				break;	
			case 'add_map':
				$msg = __( 'Map successfully created. You can use this shortcode <strong>[nwm_map id="' . absint( $_GET['id'] ) . '"]</strong> to show it on your page.', 'nwm' );
				break;	
			case 'invalid_name':
				$msg_type = 'error';
				$msg = __( 'Invalid map name', 'nwm' );
				break;
			case 'invalid_map':
				$msg_type = 'error';
				$msg = __( 'All the map entries are removed. But the map with ID 1 is used as the default map and cannot be removed', 'nwm' );
				break;		
		}
		
		echo nwm_show_msg( $msg, $msg_type );
	}  		
    ?>
    <form method="POST" action="admin.php?page=nwm_manage_maps" accept-charset="utf-8">
		<?php wp_nonce_field( 'nwm_bulkaction' ) ?>
    	<input type="hidden" name="nwm_map_manager" value="" />
        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="nwm_bulkaction" id="nwm_bulkaction">
                    <option value="no_action" ><?php _e( 'Bulk actions', 'nwm' ); ?></option>
                    <option value="delete_map" ><?php _e( 'Delete', 'nwm' ); ?></option>
                    <option value="delete_entries" ><?php _e( 'Remove route entries', 'nwm' ); ?></option>
                </select>
                <input class="button-secondary" type="submit" value="<?php _e( 'Apply' ,'nwm' ); ?>" />
                <input id="nwm-add-map" type="submit" name="doaction" class="button-secondary action"  value="<?php _e( 'Add new map', 'nwm' ) ?>"/>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th id="cb" class="manage-column column-cb check-column" scope="col">
                        <label for="cb-select-all-1" class="screen-reader-text"><?php _e( 'Select All', 'nwm' ); ?></label>
                        <input type="checkbox" id="cb-select-all-1">
                    </th>
                    <th id="id" scope="col" class="manage-column column-id sortable asc"><?php _e( 'ID', 'nwm' ); ?></th>
                    <th id="description" scope="col" class="manage-column column-description"><?php _e( 'Name', 'nwm' ); ?></th>
                    <th id="route-entries" scope="col" class="manage-column column-description"><?php _e( 'Entries', 'nwm' ); ?></th>
                </tr>
            </thead>
            <tbody id="the-list">
            <?php
            $alternate_class = '';
            
            foreach ( $nwm_map_ids as $map_id => $map_name ) {
                if ( isset( $nwm_route_order[$map_id] ) ) {
                    $route_count = explode( ',' ,$nwm_route_order[$map_id] );
                }
                
                $alternate_class = ( $alternate_class == 'class="alternate"' ) ? '' : 'class="alternate"';
						
				if ( ( isset( $route_count ) ) && ( !empty( $route_count[0] ) ) ) {
					$route_count = count( $route_count );
				} else {
					$route_count = 0;
				}
            ?>
               <tr <?php echo $alternate_class; ?>>
                    <th class="column-cb check-column" scope="row">
                        <input type="checkbox" name="maplist[]" value="<?php echo esc_attr( $map_id ); ?>" >
                    </th>
                    <td><?php echo absint( $map_id ); ?></td>
                    <td>
                    	<a href="<?php echo admin_url( 'admin.php?page=nwm_map_editor&map_id=' . absint( $map_id ) ); ?>" class="nwm-current-name"><?php echo esc_html( $map_name ); ?></a>
                    	<div class="row-actions">
                        	<a href="#" class="nwm-edit-name" title="<?php _e( 'edit this item', 'nwm' ); ?>"><?php _e( 'Edit name', 'nwm' ); ?></a>
                        </div>
                    </td>
                    <td><?php echo $route_count; ?></td>
                </tr> 
            <?php
            }
            ?>    
            </tbody>
            <tfoot>
                <tr>
                    <th class="manage-column column-cb check-column" scope="col">
                        <label for="cb-select-all-2" class="screen-reader-text"><?php _e( 'Select All', 'nwm' ); ?></label>
                        <input type="checkbox" id="cb-select-all-2">
                    </th>
                    <th scope="col" class="manage-column column-id sortable asc"><?php _e( 'ID', 'nwm' ); ?></th>
                    <th scope="col" class="manage-column column-description"><?php _e( 'Name', 'nwm' ); ?></th>
                    <th scope="col" class="manage-column column-description"><?php _e( 'Entries', 'nwm' ); ?></th>
                </tr>
            </tfoot>
        </table>
    </form>
    
	<div id="nwm-edit-name-box" style="display: none;" >
		<form method="post" action="" accept-charset="utf-8">
        	<?php wp_nonce_field( 'nwm_update_name' ); ?>
            <input type="hidden" name="page" value="nwm_map_manager" />
            <input id="nwm-map-id" type="hidden" name="nwm_map_id" value="" />
			<p>
				<label for="nwm_edit_name"><?php _e( 'Map name', 'nwm' ); ?>:</label> 
                <input type="text" size="35" name="nwm_new_name" value="" />
			</p>
            <p>
                <input class="button-primary" type="submit" name="nwm_update_name" value="<?php _e( 'Update','nwm' ); ?>" />
                <input class="button-secondary dialog-cancel" type="reset" value="<?php _e( 'Cancel', 'nwm' ); ?>" />
            </p>
		</form>
	</div>    
    
	<div id="nwm-add-map-box" style="display: none;" >
		<form method="post" action="" accept-charset="utf-8">
        	<?php wp_nonce_field( 'nwm_add_map' ); ?>
            <input type="hidden" name="page" value="nwm_map_manager" />
			<p>
				<label for="nwm_map_name"><?php _e( 'Map name', 'nwm' ); ?>:</label> 
                <input type="text" size="35" name="nwm_map_name" value="" />
			</p>
            <p>
                <input class="button-primary" type="submit" name="nwm_add_map" value="<?php _e( 'Add','nwm' ); ?>" />
                <input class="button-secondary dialog-cancel" type="reset" value="<?php _e( 'Cancel', 'nwm' ); ?>" />
            </p>
		</form>
	</div>
	
	<?php
	if ( !empty( $_POST ) ) {
		nwm_process_map_changes( $nwm_map_ids, $nwm_route_order );
	}
	
}

/* Process the map manager data */
function nwm_process_map_changes( $nwm_map_ids, $nwm_route_order ) {
	
	global $wpdb;
	
	if ( !current_user_can( 'manage_options' ) )
		die( '-1' );
		
	if ( isset( $_POST['nwm_map_manager'] ) ) {
		check_admin_referer( 'nwm_bulkaction' );
	
		/* Handle the removal of the map entries or the entire map */
		if ( ( $_POST['nwm_bulkaction'] == 'delete_entries' ) || ( $_POST['nwm_bulkaction'] == 'delete_map' ) ) {
			$map_list = wp_parse_id_list( $_POST['maplist'] );
			
			if ( count( $map_list ) ) {
				$nwm_post_ids = get_option( 'nwm_post_ids' );
		
				foreach ( $map_list as $k => $map_id ) {				
					/* Collect all the nmw_ids that should be deleted from the db based on the selected map id */
					if ( $nwm_route_order[$map_id] ) {
						$used_nwm_ids .= $nwm_route_order[$map_id] . ',';
					}
					
					/* Remove the route order and post id values from the option data */
					unset( $nwm_route_order[$map_id] );
					unset( $nwm_post_ids[$map_id] );
				}
				
				$used_nwm_ids = explode( ',', rtrim( $used_nwm_ids, ',' ) );
				$used_nwm_ids = esc_sql( implode( ',', wp_parse_id_list( $used_nwm_ids ) ) );
				
				/* Delete the route entries from the db */						
				$result = $wpdb->query( "DELETE FROM $wpdb->nwm_routes WHERE nwm_id IN ( $used_nwm_ids )" );
				
				if ( $result !== false ) {
					update_option( 'nwm_route_order', $nwm_route_order );
					update_option( 'nwm_post_ids', $nwm_post_ids );	
				}
				
				/* Check if we also need to remove the map itself */
				if ( $_POST['nwm_bulkaction'] == 'delete_map' ) {
					foreach ( $map_list as $k => $map_id ) {
						
						/* Prevent the default map from being deleted */
						if ( $map_id == 1 ) {
							wp_redirect( admin_url( 'admin.php?page=nwm_manage_maps&action=invalid_map' ) ); 
							exit();
						} else {
							unset( $nwm_map_ids[$map_id] );
						}
					}
					
					update_option( 'nwm_map_ids', $nwm_map_ids );
				}
				
				wp_redirect( admin_url( 'admin.php?page=nwm_manage_maps&action=' . esc_attr( $_POST['nwm_bulkaction'] ) . '' ) ); 
				exit();
			}
		}		
	}
	
	/* Add the new map to the option list */
	if ( isset( $_POST['nwm_add_map'] ) ) {
		check_admin_referer( 'nwm_add_map' );
				
		$map_name = esc_attr( $_POST['nwm_map_name'] );
		
		if ( !empty( $map_name ) ) {
			$map_ids = get_option( 'nwm_map_ids' );
			array_push( $map_ids, $map_name );
			update_option( 'nwm_map_ids', $map_ids );
			
			/* Get the index of the last entry */
			end( $map_ids );
			$last_index = key( $map_ids );
			
			wp_redirect( admin_url( 'admin.php?page=nwm_manage_maps&action=add_map&id=' . $last_index . '' ) ); 
			exit();	
		} else {
			wp_redirect( admin_url( 'admin.php?page=nwm_manage_maps&action=invalid_name' ) ); 
			exit();
		}
	}
	
	/* Update the name of the map */
	if ( isset( $_POST['nwm_update_name'] ) ) {
		check_admin_referer( 'nwm_update_name' );		
		
		$new_name = esc_attr( $_POST['nwm_new_name'] );
		$modified_map_id = absint( $_POST['nwm_map_id'] );
		
		if ( ( !empty( $new_name ) ) && ( $modified_map_id ) ) {
			foreach ( $nwm_map_ids as $map_id => $map_name ) {
				if ( $modified_map_id == $map_id ) {
					$nwm_map_ids[$map_id] = $new_name;
					break;	
				}
			}
			
			update_option( 'nwm_map_ids', $nwm_map_ids );
			wp_redirect( admin_url( 'admin.php?page=nwm_manage_maps&action=name_updated' ) );
			exit();	
		} else {
			wp_redirect( admin_url( 'admin.php?page=nwm_manage_maps&action=invalid_name' ) ); 
			exit();	
		}
	}
}


?>