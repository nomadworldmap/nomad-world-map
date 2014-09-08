jQuery( document ).ready( function( $ ) { 

var map, geocoder, preloadImgSrc, uploadFrame, 
	flightLines		= {},
	markersArray	= [], 
	flightPlanArray = [],
	preloadImgSrc   = $( "#nwm-preload-img img" ).attr( "src" ),
	defaultLatlng	= new google.maps.LatLng( "52.378153", "4.899363" ),
	url				= window.location.href;

/* Load Google Maps */
function initializeGmap() {
	var myOptions = {
			zoom: 2,
			center: defaultLatlng,
			mapTypeId: google.maps.MapTypeId.ROADMAP,
			streetViewControl: false
		};

	map		 = new google.maps.Map( document.getElementById( "gmap-nwm" ), myOptions );
	geocoder = new google.maps.Geocoder();	
	
	rebuildFlightPlan();
}

/* Show the correct flightplan for the selected map. */
function rebuildFlightPlan() {
	var latlng, alt, draggable, location;
		
	deleteOverlays();
	
	/* Add the default draggable marker to the map */
	addMarker( defaultLatlng, alt, draggable = true );
		
	/* If the tr exist, loop over the table data and collect the latlng and location data */	
	if ( $( "#nwm-destination-list tbody tr" ).length ) {
		
		/* Add all the markers to the map */
		$( "#nwm-destination-list tbody tr" ).each( function( i ) {
			latlng   = $(this).attr( "data-latlng" ).split( "," );
			location = new google.maps.LatLng( latlng[0], latlng[1] );	
			alt		 = $(this).find( ".nwm-location" ).text();
			addMarker( location, alt, draggable = false );
			flightPlanArray.push( location );
		});
	}
		
	/* Draw the new lines on the map */
	drawFlightPlan( flightPlanArray );
}

/* Remove all existing markers and route lines from the map */
function deleteOverlays() {
		
	/* Empty the flightplan array */
	flightPlanArray.length = 0;
	
	/* Remove all the markers from the map, and empty the array */
	if ( markersArray ) {
        for ( i = 0; i < markersArray.length; i++ ) {
            markersArray[i].setMap( null );
        }
    markersArray.length = 0;
    }
	
	/* If an old flightpath exist on the map, remove it */
	if ( typeof( flightLines.path ) !== "undefined" ) {
		flightLines.path.setMap( null );
	}
}

/* Draw lines between all markers */
function drawFlightPlan( flightPlanCoordinates ) {	
	var trCount    = $( "#nwm-destination-list tbody tr" ).length,
		tableId    = $( "#nwm-destination-list" ).attr( "data-map-id" ),
		mapListId  = $( "#nwm-map-list" ).val(),
		flightPath = new google.maps.Polyline({
			path: flightPlanCoordinates,
			geodesic: true,
			strokeColor: "#ad1700",
			strokeOpacity: 1.0,
			strokeWeight: 2
		});
	
	flightLines.path = flightPath;
	
	/* Bind the new flightPath to the map */
	flightPath.setMap( map );
	
	/* 
	Only run the fitBounds function if we have 2 or more entries and the tableId matches with the maplistId.
	We need to make this extra check because on page load it always loads the first map. And that could have more then 2 items so it will change the focus of the map.
	But if the map_id is specified in the url (for example &map_id=3) it should only run when that map is loaded. 
	And if in this example map 3 has zero entries, the center of the map will be wrong.
	*/
	if ( ( tableId == mapListId ) && ( trCount >= 2 ) ) {
		fitBounds( flightPlanCoordinates );
	} else {
		map.setCenter( defaultLatlng );	
		map.setZoom( 2 );
	}
}

/* Zoom the map so that all markers fit in the window */
function fitBounds( flightPlanCoordinates ) {
	var maxZoom = 4,
		bounds = new google.maps.LatLngBounds( defaultLatlng );
	
	/* Make sure we don't zoom to far */
    google.maps.event.addListenerOnce( map, "bounds_changed", function( event ) {
        if ( this.getZoom() > maxZoom ) {
            this.setZoom( maxZoom );
        }
    });

	for ( var i = 0, flightPlanLen = flightPlanCoordinates.length; i < flightPlanLen; i++ ) {
		bounds.extend ( flightPlanCoordinates[i] );
	}
	
	map.fitBounds( bounds );
}

/* Add a new marker to the map based on the provided location (latlng) */
function addMarker( location, alt, draggable_state ) {
	var image, marker;
		
	if ( !draggable_state ) {
		image = new google.maps.MarkerImage( nwmMarker.path + "marker.png",
				new google.maps.Size(30,30),
				new google.maps.Point(0,0),
				new google.maps.Point(8,8)
			);
		marker = new google.maps.Marker({
			position: location,
			map: map,
			title: alt,
			icon: image,
			draggable: draggable_state
		});	
	} else {
		marker = new google.maps.Marker({
			position: location,
			map: map,
			draggable: draggable_state
		});
	}
	
	markersArray.push( marker );
	
	if ( draggable_state ) {
		google.maps.event.addListener( marker, "dragend", function() {
			geocodeDraggedPosition( marker.getPosition() );
		});
	}
}

/* Lookup the location where the marker is dropped */
function geocodeDraggedPosition( pos ) {
	geocoder.geocode({
		latLng: pos
	}, function( responses ) {
		if ( responses && responses.length > 0 ) {
			updateLocationFields( responses );
		} else {
			alert( nwmL10n.addressFailed );
		}
	});
}

/* Update the input fields with the received data */
function updateLocationFields( responses ) {
	var fullLocation = filterApiResponse( responses ),
		coordinates  = stripCoordinates( responses[0].geometry.location ),
		lat			 = roundLatlng( coordinates[0], 6 ),
		lng			 = roundLatlng( coordinates[1], 6 );
	
	$( "#nwm-latlng" ).val( lat + "," + lng );
	$( "#nwm-searched-location" ).val( fullLocation.location );
	$( "#nwm-country-code" ).val( fullLocation.country_code );
}

/* Filter out the city / country, and country code from the API response from Google */
function filterApiResponse( responses ) {
	var responseLocation, countryLong, countryShort, formatted_address, 
		fullLocation = {},
		addressLength = responses[0].address_components.length;
		
	/* Loop over the API response */
	for ( i = 0; i < addressLength; i++ ){
		
		/* filter out country name */
		if ( /^country,political$/.test( responses[0].address_components[i].types ) ) {
			countryLong  = responses[0].address_components[i].long_name;
			countryShort = responses[0].address_components[i].short_name;
		}
		
		/* filter out location name */
		if ( /^locality,political$/.test( responses[0].address_components[i].types ) ) {
			responseLocation = responses[0].address_components[i].long_name;
		}

		/* if no locality is found, try to administrative levels */
		if ( typeof responseLocation === "undefined" ) {
			if ( /^administrative_area_level_1,political$/.test( responses[0].address_components[i].types ) ) {
				responseLocation = responses[0].address_components[i].long_name;
			}
			
			if ( /^administrative_area_level_2,political$/.test( responses[0].address_components[i].types ) ) {
				responseLocation = responses[0].address_components[i].long_name;
			}
			
			if ( /^administrative_area_level_3,political$/.test( responses[0].address_components[i].types ) ) {
				responseLocation = responses[0].address_components[i].long_name;
			}	
			
			/* if there is still no location then use the formatted address as the location */
			if ( typeof responseLocation === "undefined" ) {
				formatted_address = responses[0].formatted_address;
			}
		}
	}
	
	/* If responseLocation is set use the responseLocation + responseCountry, otherwise the formatted_address will be used */
	if ( typeof responseLocation !== "undefined" ) {
		formatted_address = responseLocation + ", " + countryLong;
	}
	
	fullLocation = {
		location: formatted_address,
		country_code: countryShort
	};
	
	return fullLocation;
}

/* strip the '(' and ')' from the captured coordinates, and split them */
function stripCoordinates( coordinates ) {
	var latlng    = [],
		selected  = coordinates.toString(),
		latlngStr = selected.split( ",",2 );
	
	latlng[0] = latlngStr[0].replace( "(", "" );
	latlng[1] = latlngStr[1].replace( ")", "" );	
	
	return latlng;
}

/* Geocode the user input */ 
function codeAddress() {
    var fullLocation, lastIndex, draggable, alt,
		address = $( "#nwm-searched-location" ).val();
		
    geocoder.geocode( { 'address': address}, function( responses, status ) {
		if ( status == google.maps.GeocoderStatus.OK ) {
			
			/* 
			Before we add a new draggable marker to the map we need to remove the old one. 
			On pageload the draggable marker will be the first item, but once the "set" button has been clicked it will be the last one. 
			But to be sure we check if draggable is set to true before actually removing it.
			*/
			if ( markersArray[0].draggable ) {
				markersArray[0].setMap( null );
				markersArray.splice( 0, 1 );
			} else {
				lastIndex = markersArray.length-1;
				if ( markersArray[lastIndex].draggable ) {
					markersArray[lastIndex].setMap( null );
					markersArray.splice( lastIndex, 1 );
				}
			}
			
			/* Center and zoom to the searched location */
			map.setCenter( responses[0].geometry.location );
			map.setZoom( 6 );
			addMarker( responses[0].geometry.location, alt, draggable = true );

			/* Filter out the city, country and country code from the response */
			fullLocation = filterApiResponse( responses );
					
			$( "#nwm-searched-location" ).val( fullLocation.location );
			$( "#nwm-country-code" ).val( fullLocation.country_code );
			setCurrentCoordinates( responses[0].geometry.location );
		} else {
			alert( nwmL10n.geocodeFailed + status );
		}
    }
)};

/* Round the latlng to 6 digits after the comma */
function roundLatlng( num, decimals ) {
	return Math.round( num * Math.pow( 10, decimals ) ) / Math.pow( 10, decimals );
}

/* Set the width of the new added td in the route list so that it doesn't collapse when dragged */
setTdWidth();

$( "#nwm-destination-wrap input[type=text], #nwm-destination-wrap input[type=url], #nwm-destination-wrap input[type=hidden]" ).not( "#nwm-search-nonce" ).val( "" );

/* Make sure the first select option is always selected on page load */		
$( "#nwm-marker-content-option option[value=nwm-blog-excerpt]" ).attr( "selected", "selected" );
$( "#nwm-map-list option[value=1]" ).attr( "selected", "selected" );

/* Load the data for the selected map */
$( "#nwm-map-list" ).change( function () {
	var mapId = dropdownValue = $( this ).val(),
		mapNonce = $( "#nwm-map-list-nonce" ).val(),
		ajaxData = {
			action: "load_map",
			map_id: mapId,
			_ajax_nonce: mapNonce
		};

	showPreloader( $( this ) );
				
	$.post( ajaxurl, ajaxData, function( response ) {	
		if ( response == -1 ) {
			alert( nwmL10n.securityFailed );
		} else {
			if ( response.success ) {
				$( "#nwm-destination-list tbody" ).html( response.data );	
			} else {
				$( "#nwm-destination-list tbody" ).html( "" );
			}
			
			$( "#nwm-destination-list" ).attr( "data-map-id", mapId );			
			rebuildFlightPlan();
			
			/* Set the width of the new added td so that it doesnt collapse when dragged */
			setTdWidth();
						
			/* Hide the edit fields and rebuild the dropdown list */
			$( "#nwm-edit-destination #nwm-form" ).hide();
			rebuildDropdowns();
		}
		
		$( ".nwm-preloader" ).remove();
	});
});

/* Detect changes in the marker content options */
$( "#nwm-marker-content-option" ).change( function () {
	var routeId, 
		dropdownValue = $( this ).val();
	
	/* Remove all errors messages */
	$( "#nwm-error-msg" ).remove();
	$( "#nwm-destination-wrap input, #nwm-destination-wrap textarea" ).removeClass( "nwm-error" );
	$( ".nwm-marker-option" ).hide();	
	
	/* Check if we need to show the date description and the optional placeholder text */
	if ( dropdownValue == "nwm-blog-excerpt" ) {
		$( ".nwm-date-desc" ).show();
		$( ".nwm-dates input" ).attr( "placeholder", "optional" );
	} else if ( dropdownValue == "nwm-travel-schedule" ) {
		$( ".nwm-dates input" ).attr( "placeholder", "" );
		$( ".nwm-date-desc" ).hide();
	} else {
		$( ".nwm-dates input" ).attr( "placeholder", "optional" );
		$( ".nwm-date-desc" ).hide();
	}
	
	$( "#nwm-destination-list tbody tr" ).each( function() {
		routeId = $( this ).data( "nwm-id" );

		if ( routeId == dropdownValue ) {
			setTravelDates( routeId ); /* If arrival / departure dates exist set them in the edit screen */
			return false;
		}
	});	
	
	$( "#" + dropdownValue + "" ).show();
});

$( "#nwm-form" ).on( "click", "#nwm-add-trip", function() {		
	addTrip( $( this ) );	
	return false;
});

/* Add a new trip to the database */
function addTrip( elem ) {
	var destinationUrl,
		tripData = {
			postId: $( "#nwm-post-id" ).val(),
			mapId: $( "#nwm-map-list" ).val(),
			thumbId: $( "#nwm-thumb-wrap img" ).attr( "data-img-id" ),
			countryCode: $( "#nwm-country-code" ).val(),
			destinationLatlng: $( "#nwm-latlng" ).val(),
			destinationName: $.trim( $( "#nwm-searched-location" ).val() ),
			dropdownValue: $( "#nwm-marker-content-option" ).val()
		};

	/* Show the preloader next to the clicked button */
	showPreloader( elem );
	
	if ( tripData.dropdownValue == "nwm-blog-excerpt" ) {
		destinationUrl = $( "#nwm-search-link a" ).attr( "href" );
		destinationUrl = ( typeof( destinationUrl ) !== "undefined" ) ? destinationUrl : "";
	} else {
		destinationUrl = $( "#nwm-custom-url" ).val();

		if ( !destinationUrl ) {
			destinationUrl = '';
		}
	}
	
	tripData.destinationUrl = destinationUrl;
	
	/* Make sure we have a latlng value and a destination name before saving the data */
	if ( checkFormData( tripData.destinationLatlng, tripData.destinationName ) ) {
		saveDestination( tripData );
	}	
}

/* Save the new destination */
function saveDestination( tripData ) {
	var ajaxData,
		lastData = {
			post_id: tripData.postId,
			map_id: tripData.mapId,
			thumb_id: tripData.thumbId
		},
		markerContentOption = $( "#nwm-marker-content-option" ).val(),
		saveNonce			= $( "#nwm-add-destination" ).data( "nonce-save" );
	
	/* Build a data object based on the selected dropdown value. */
	if ( markerContentOption == "nwm-custom-text" ) {
		lastData.custom = {
			latlng:	tripData.destinationLatlng,
			location: tripData.destinationName,
			country_code: tripData.countryCode,
			title: $( "#nwm-custom-title" ).val(),
			content: $( "#nwm-custom-text textarea" ).val(),
			url: tripData.destinationUrl,
			arrival: $( "#nwm-from-date" ).val(),
			departure: $( "#nwm-till-date" ).val()
		};
	} else if ( markerContentOption == "nwm-travel-schedule" ) {
		lastData.schedule = {
			latlng:	tripData.destinationLatlng,
			location: tripData.destinationName,
			country_code: tripData.countryCode,
			arrival: $( "#nwm-from-date" ).val(),
			departure: $( "#nwm-till-date" ).val()
		};	
	} else {
		lastData.excerpt = {
			latlng:	tripData.destinationLatlng,
			location: tripData.destinationName,
			country_code: tripData.countryCode,
			arrival: $( "#nwm-from-date" ).val(),
			departure: $( "#nwm-till-date" ).val()
		};
	}
					
	lastData = JSON.stringify( lastData );
			
	ajaxData = {
		action: "save_location",
		last_update: lastData,
		 _ajax_nonce: saveNonce
	};

	$( "#find-nwm-title" ).after( $( "#nwm-preload-img" ).html() );
	$( "#nwm-add-trip" ).attr( "disabled", "disabled" );

	$.post( ajaxurl, ajaxData, function( response ) {				
		
		/* Check if we have a valid response */
		if ( response == -1 ) {
			alert( nwmL10n.securityFailed );
		} else {	
			if ( response.success ) {
				/* Make sure the response contains a number */
				if( !isNaN( response.id ) ) {	
					addNewDestinationRow( tripData, response );
					resetTravelDates();
							
					$( "#nwm-add-trip" ).after( "<span class='nwm-save-msg'>" + nwmL10n.locationAdded + "</span>" );	
					setTimeout( function() {
						$( ".nwm-save-msg" ).fadeOut( "slow", function() {
							$( this ).remove();
						});
					}, 2000);
					
					rebuildFlightPlan();					
					
					/* Reset the thumbnail img */
					$( "#nwm-reset-thumb" ).trigger( "click" );
					
					/* Reset the textarea word count */
					$( "#nwm-custom-desc" ).focus().blur();
				}
			} else {
				if ( typeof response.data !== "undefined" ) {
					alert( response.data.msg );
				} else {
					alert( nwmL10n.saveFailed );
				}
			}
			
			$( "#nwm-add-trip" ).removeAttr( "disabled" );
			$( ".nwm-preloader" ).remove();
		}
	});	
}

function removeErrorClasses() {
	$( "#nwm-destination-wrap input, #nwm-destination-wrap textarea" ).removeClass( "nwm-error" );	
}

/* Make sure we have all the required data (latlng, location name, and in some situation the dates) before saving it */
function checkFormData( destinationLatlng, destinationName ) {
	var errors,
		customTitle	  = $( "#nwm-custom-title" ),
		customDesc	  = $( "#nwm-custom-desc" ),
		fromDate	  = $( "#nwm-from-date" ),
		tillDate	  = $( "#nwm-till-date" ),
		dropdownValue = $( "#nwm-marker-content-option" ).val();
		
	removeErrorClasses();
	
	if ( ( !destinationLatlng ) || ( !destinationName ) ) {
		$( "#nwm-searched-location" ).addClass( "nwm-error" );
		errors = true;
		/* 
		If you scrolldown far enough and click the save button, you won't be able to see the red error border.
		To prevent this from happening we scroll back to the top of the page.
		*/
		$( window ).scrollTop(0); 
	}
	
	/* Check if we have all the data for the blog excerpt */
	if ( dropdownValue == "nwm-blog-excerpt" ) {
		
		/* Check if a url is set */
		if ( !$( "#nwm-search-link a" ).attr( "href" ) ) {
			$( "#nwm-post-title" ).addClass( "nwm-error" );
			errors = true;
		}
		
		/* If an end date is set, then make sure there is also a start date */
		if ( !checkBothDates( fromDate, tillDate ) ) {
			errors = true;	
		}
	}	
	
	/* Check if there we have all the required data for the custom fields */
	if ( dropdownValue == "nwm-custom-text" ) {
		if( !checkBothDates( fromDate, tillDate ) ) {
			errors = true;	
		}
		
		if ( !customTitle.val() ) {
			customTitle.addClass( "nwm-error" );
			errors = true;
		}
		
		if ( !customDesc.val() ) {
			customDesc.addClass( "nwm-error" );
			errors = true;
		}
	}	
	
	/* Check if both travel dates are set */
	if ( dropdownValue == 'nwm-travel-schedule' ) {	
		if ( !fromDate.val() ) {
			fromDate.addClass( "nwm-error" );
			errors = true;	
		}
		
		if ( !tillDate.val() ) {
			tillDate.addClass( "nwm-error" );
			errors = true;	
		}
		
		if ( ( fromDate.val() ) && ( tillDate.val() ) ) {
			if ( !checkDates( fromDate, tillDate ) ) {
				errors = true;
			}
		}		
	}

	if ( !errors ) {
		return true;	
	}
	
	$( ".nwm-preloader" ).remove();			
}

/* If an end date is set, then make sure there is also a start date */
function checkBothDates( fromDate, tillDate ) {
	var errors;
	
	if ( tillDate.val() ) {
		if ( !fromDate.val() ) {
			fromDate.addClass( "nwm-error" );
			errors = true;	
		} else {			
			if ( !checkDates( fromDate, tillDate) ) {
				errors = true;
			}
		}
	}	
	
	if ( errors ) {
		return false;	
	} else {
		return true;
	}
}

/* Check if the from date is equal or set to before the departure date */
function checkDates( fromDate, tillDate ) {
	var fromCompareDate = new Date( fromDate.val().toString() ),
		tillCompareDate = new Date( tillDate.val().toString() );

	if ( fromCompareDate > tillCompareDate ) {
		alert( nwmL10n.arrivalDataError );
		fromDate.addClass( "nwm-error" );
		return false;
	} else {
		return true;
	}
}

/* Handle the clicks on the delete button */
$("#nwm-destination-list").on( "click", ".delete-nwm-destination", function( e ) {	
	var elem		= $( this ), 
		$tr			= elem.closest( "tr" ), 
		routeId		= $tr.data( "nwm-id" ),
		postId		= $tr.data( "post-id" ),
		deleteNonce = $tr.find( "input[name=delete_nonce]" ).val();
	
	showPreloader( elem );
	elem.attr( "disabled", "disabled" );
	deleteDestination( routeId, postId, deleteNonce, elem );
	
	e.preventDefault();
});

/* Insert the preloader after the button */
function showPreloader( elem ) {
	if ( !elem.parent().find( ".nwm-preloader" ).length ) {
		elem.after( "<img class='nwm-preloader' src=" + preloadImgSrc + " />" ).show();
	}
}

/* Delete the destination from the database */
function deleteDestination( routeId, postId, deleteNonce, btn ) {
	var ajaxData = {
			action: "delete_location",
			nwm_id: routeId,
			post_id: postId,
			map_id: $( "#nwm-map-list" ).val(),
			 _ajax_nonce: deleteNonce
		};
	
	jQuery.ajaxQueue({
		url: ajaxurl,
		data: ajaxData,
		type: "POST"
	}).done(function( response ) {
		if ( response == -1 ) {
			alert( nwmL10n.securityFailed );
			$( ".nwm-preloader" ).remove();
			btn.removeAttr( "disabled" );
		} else {	
			if ( response.success ) {
				$( "[data-nwm-id=" + routeId + "]" ).animate( {height:0, opacity : 0}, 500, function() {
					$(this).remove();
					
					/* Remove the delete item from the dropdown list */
					$( "#nwm-edit-list option[value=" + routeId + "], .nwm-delete-btn-wrap" ).remove();
										
					/* Only hide the form if the last item (edit destination is selected */
					if ( $( "#nwm-menu li" ).last().hasClass( "nwm-active-item" ) ) {
						$( "#nwm-form" ).hide();
					}
					
					$( "#nwm-searched-location, #nwm-latlng, #nwm-post-id" ).val( "" );
					
					updateDestinationCount();
					rebuildDropdowns();
					rebuildFlightPlan();
				});
			} else {
				alert( nwmL10n.deleteFailed );
				$( ".nwm-preloader" ).remove();
				btn.removeAttr( "disabled" );
			}
		}	

	});	
}

function rebuildDropdowns() {
	var dropdownEditList = "", dropdownSortList = "", destination, 
		dropdownEditItem = "", 
		dropdownSortItem = "",
		i = 1;
	
	/* Loop over the tr elements, collect the nwm-id's and rebuild the dropdown list */
	$( "#nwm-destination-list tbody tr" ).each( function () {
		destination	  = $(this).find( ".nwm-location" ).text();
		
		dropdownEditItem  = "<option value=" + $(this).data( "nwm-id" ) + ">" + i + ' - ' + destination + "</option>";
		dropdownEditList += dropdownEditItem;
		
		dropdownSortItem  = "<option value=" + i + ">" + nwmL10n.before + ' ' + i +' - ' + destination + "</option>";
		dropdownSortList += dropdownSortItem;
		
		i++;
	});
					
	/* Update the edit dropdown list with the correct order */
	populateEditDropdown( dropdownEditList );
	
	/* Update the sort dropdown list with the correct order */
	populateSortDropdown( dropdownSortList );	
}

/* Update the destination data */
function updateDestination( tripData ) {
	var ajaxData, tr, listOrder, thumbSrc, thumbImg, flagLocation,
		locationPosition = $( "#nwm-position" ).val(),
		lastData = {
			nwm_id: tripData.nwmId,
			thumb_id: tripData.thumbId,
			map_id: $( "#nwm-map-list" ).val(),
			previous: $( "#nwm-post-type" ).val()
		},
		arrivalDate = "", 
		departureDate = "";
	
	if ( tripData.markerContentOption == "nwm-custom-text" ) {
		lastData.custom = {
			latlng:	tripData.latlng,
			location: tripData.location,
			country_code: tripData.countryCode,
			title: $( "#nwm-custom-title" ).val(),
			content: $( "#nwm-custom-text textarea" ).val(),
			url: $( "#nwm-custom-url" ).val(),
			arrival: $( "#nwm-from-date" ).val(),
			departure: $( "#nwm-till-date" ).val()
		 };
	} else if ( tripData.markerContentOption == "nwm-travel-schedule" ) {
		lastData.schedule = {
			latlng:	tripData.latlng,
			location: tripData.location,
			country_code: tripData.countryCode,
			arrival: $( "#nwm-from-date" ).val(),
			departure: $( "#nwm-till-date" ).val()
		 };	
	} else {
		lastData.excerpt = {
			post_id: $( "#nwm-post-id" ).val(),
			last_id: $( "[data-nwm-id=" + tripData.nwmId + "]" ).attr( "data-post-id" ),
			latlng:	tripData.latlng,
			location: tripData.location,
			country_code: tripData.countryCode,
			arrival: $( "#nwm-from-date" ).val(),
			departure: $( "#nwm-till-date" ).val()
		};
	}
	
	lastData = JSON.stringify( lastData );
				
	ajaxData = {
		action:"update_location",
		last_update: lastData,
		 _ajax_nonce: tripData.updateNonce
	};
	
	$.post( ajaxurl, ajaxData, function( response ) {	
		if ( response == - 1) {
			alert( nwmL10n.securityFailed );
		} else {			
			if ( response.success ) {
				flagLocation = "<img src=" + nwmMarker.path + 'flags/' +  tripData.countryCode.toLowerCase() + ".png >" + tripData.location;
				tr = $( "[data-nwm-id=" + tripData.nwmId + "]" );
				tr.find( ".nwm-location" ).html( flagLocation );
				tr.attr( "data-latlng", tripData.latlng );	
				tr.attr( "data-country", tripData.countryCode );	
				thumbSrc = $( "#nwm-edit-destination .nwm-circle" ).attr( "src" );

				if ( typeof( thumbSrc ) !== "undefined" ) {			
					if ( tr.find( ".nwm-thumb-td img").length ) {
						tr.find( ".nwm-thumb-td img" ).attr( "src", thumbSrc );
					} else {
						thumbImg = "<img class='nwm-circle' data-img-id=" + tripData.thumbId + " src=" + thumbSrc + " width='64' height='64' />";
						tr.find( ".nwm-thumb-td" ).html( thumbImg );
					}
				} else {
					tr.find( ".nwm-thumb-td img" ).remove();	
				}
				
				if ( tripData.markerContentOption == "nwm-blog-excerpt" ) {
					tr.attr( "data-post-id", $( "#nwm-post-id" ).val() );
					$( "#nwm-custom-text input, #nwm-custom-desc" ).val( "" );
					$( "#nwm-post-type" ).val( "blog" );
				} else {
					tr.attr( "data-post-id", 0 );
				}
				
				if ( tripData.markerContentOption == "nwm-custom-text" ) {
					$( "#nwm-search-link span" ).empty(); 
					$( "#nwm-post-id" ).val( "0" );
					$( "#nwm-post-type" ).val( "custom" );
				}
				
				if ( tr.attr( "data-travel-schedule" ) && ( tripData.markerContentOption != "nwm-travel-schedule" ) ) {
					tr.removeAttr( "data-travel-schedule" );
					$( "#nwm-post-type" ).val( "schedule" );
				}
				
				if ( !tr.attr( "data-travel-schedule" ) && ( tripData.markerContentOption == "nwm-travel-schedule" ) ) {
					tr.attr({'data-post-id' : 0, 'data-travel-schedule' : 1});
					/*
					Make sure the url value from the blog post excerpt field is empty. If there first was a blog post excerpt, 
					but it is later changed to a travel schedule, the url would still contain a value.
					*/
					$( "#nwm-search-link span" ).empty(); 
					$( "#nwm-post-id" ).val( "0" );
				}
				
				if ( ( typeof( response.url ) !== "undefined" ) && ( response.url !== "" ) ) {
					tr.find( ".nwm-url" ).html( "<a href=" + response.url + "></a>" );
				} else {
					tr.find( ".nwm-url" ).empty();
				}
				
				/* If the last submitted value was a schedule, then empty the custom input fields and blog post url */
				if ( response.type == "schedule" ) {
					$( "#nwm-custom-text input, #nwm-custom-desc" ).val( "" );
					$( "#nwm-search-link span" ).empty(); 
					$( "#nwm-post-id" ).val( "0" );	
				}

				/* Update the name of the dropdown list */
				listOrder = tr.find( ".nwm-order span" ).html();
				$( "#nwm-edit-list option[value=" + tripData.nwmId + "]" ).html( listOrder + ' - ' + tripData.location );
				
				/* Make sure we only add the msg once */
				if ( !$( "#nwm-form .nwm-save-msg" ).length ) {
					$( ".nwm-delete-btn-wrap" ).after( "<span class='nwm-save-msg'>" + nwmL10n.locationUpdated + "</span>" );
				}
				
				/* Check if we need to update, or remove the current dates */
				if ( $( "#nwm-from-date" ).val() ) {
					arrivalDate   = "<input type='hidden' value=" + $( "#nwm-from-date" ).val() + " name='arrival_date'><span>" + $( "#nwm-form input[name=from_date]" ).val() + "</span>";
					departureDate = "<input type='hidden' value=" + $( "#nwm-till-date" ).val() + " name='departure_date'><span>" + $( "#nwm-form input[name=till_date]" ).val() + "</span>";
				}
				
				tr.find( ".nwm-arrival" ).html( arrivalDate ).end()
				  .find( ".nwm-departure" ).html( departureDate );
			  	
				/* Check if the position of the location in the list has changed */
				if ( locationPosition >= 0 ) {
					var tripTarget = $( "[data-nwm-id=" + tripData.nwmId + "]" );
					var tripCopy   = tripTarget.clone();
					
					/* Insert the location after the last item, or before a specific location? */
					if ( locationPosition == 0 ) {					
						$( "#nwm-destination-list tbody tr" ).last().after( tripCopy );
					} else {
						$( "#nwm-destination-list tbody tr" ).eq( locationPosition-1 ).before( tripCopy );
					}
					
					tripTarget.remove();
					updateDestinationCount();
					updateSortOrder();
				}
				
				/* Fade out the save msg */	
				setTimeout( function() {
					$( ".nwm-save-msg" ).fadeOut( "slow", function() {
						$(this).remove();
					});
				}, 2000);
			} else {
				alert( nwmL10n.updateFailed );
			}
			
			$( ".nwm-preloader" ).remove();	
		}		
	});	
}

/* Add the new created destination to the table */
function addNewDestinationRow( tripData, response ) {
	var lastElement, lastDestination, trCount,
		selectedOption   = $( "#nwm-marker-content-option" ).val(),
		locationPosition = $("#nwm-position").val(),
		nwmId			 = "",
		listData	     = "",
		travelSchedule   = "",
		departureDate    = "",
		arrivalDate      = "",
		thumb		     = "",
		url			     = ""; 
	
	/* If the input is custom set the post id to 0. This 0 is used to indicate custom content should be loaded if the data is edited */	
	if ( ( selectedOption == "nwm-custom-text" ) || ( selectedOption == "nwm-travel-schedule" ) ) {
		tripData.postId = 0;
	}
	
	if ( selectedOption == "nwm-travel-schedule" ) {
		travelSchedule = " data-travel-schedule='1'";	
	}
	
	if ( $( "#nwm-from-date" ).val() ) {
		arrivalDate = "<input type='hidden' name='arrival_date' value=" + $( "#nwm-from-date" ).val() + ">";
	}
	
	if ( $( "#nwm-till-date" ).val() ) {
		departureDate = "<input type='hidden' name='departure_date' value=" + $( "#nwm-till-date" ).val() + ">";
	}
	
	if ( tripData.destinationUrl ) {
		url = "<a target='_blank' title=" + tripData.destinationUrl + " href=" + tripData.destinationUrl + "></a>";	
	}
	
	if ( tripData.thumbId ) {
		thumb = "<img class='nwm-circle' src=" + $( "#nwm-thumb-wrap img" ).attr( "src" ) + " data-thumb-id=" + tripData.thumbId + " width='24' height='24' />";
	}
		
	listData = '<tr' + travelSchedule + ' data-latlng="' + tripData.destinationLatlng + '" data-country="' + tripData.countryCode + '" data-post-id="' + tripData.postId + '" data-nwm-id="">' + 
			   '<td class="nwm-order"><span></span></td>' +
			   '<td class="nwm-location"><img src=' + nwmMarker.path + 'flags/' + tripData.countryCode.toLowerCase() + '.png >' + tripData.destinationName + '</td>' +
			   '<td class="nwm-url">' + url + '</td>' +
			   '<td class="nwm-arrival">' + arrivalDate + '<span>' + $( "input[name=from_date]" ).val() + '</span></td>' +
			   '<td class="nwm-departure">' + departureDate + '<span>' + $( "input[name=till_date]" ).val() + '</span></td>' +
			   '<td class="nwm-thumb-td">' + thumb + '</td>' +
			   '<td><input class="delete-nwm-destination button" type="button" name="text" value='+ nwmL10n.delete +' /><input type="hidden" value="" name="delete_nonce"><input type="hidden" value="" name="update_nonce"><input type="hidden" value="" name="load_nonce"></td>' +
			   '</tr>';

	/* Add the latest entry to the table, and set the data attribute values, location name and url */
	if ( locationPosition == 0 ) {
		$( "#nwm-destination-list" ).append( listData );
		lastElement = $( "#nwm-destination-list tbody tr" ).last();	
	} else {
		$( "#nwm-destination-list tbody td.nwm-order span" ).each( function () {
			if ( $( this ).text() == locationPosition ) {
				nwmId = $( this ).parents( "tr" ).attr( "data-nwm-id" );
				$( "[data-nwm-id=" + nwmId + "]" ).before( listData );
				lastElement = $( "[data-nwm-id=" + nwmId + "]" ).prev( "tr" );
				
				return false;
			}			
		});
	}
	
	/* Set the width of the new added td so that it doesnt collapse when dragged */
	setTdWidth();	
	
	/* Add the id to the data attribute and set the correct nonce values */	
	lastElement.attr( "data-nwm-id", response.id);
	lastDestination = lastElement.find( ".nwm-location" ).text();
	trCount = $( "#nwm-destination-list tbody tr" ).length;
	lastElement.find( "input[name=delete_nonce]" ).val( response.delete_nonce );
	lastElement.find( "input[name=update_nonce]" ).val( response.update_nonce );
	lastElement.find( "input[name=load_nonce]" ).val( response.load_nonce );
	
	/* Add the new saved destination to the edit destination dropdown */
	$( "#nwm-edit-list option" ).last().after( "<option value=" + response.id + ">" + trCount + ' - ' + lastDestination + "" );
	
	/* Loop over the tr's and count them to make sure the order still makes sense */
	updateDestinationCount();
	
	if ( locationPosition !== 0 ) {
		updateSortOrder();
	}
	
	/* Empty all used input fields, and remove the preloader */				
	$( "#nwm-destination-wrap input[type=text], #nwm-destination-wrap input[type=url], #nwm-destination-wrap input[type=hidden]" ).not( "#nwm-search-nonce" ).val( "" );
	$( "#nwm-search-link span" ).empty();	
}

/* If the order of the locations has changed by dragging them around, then save the new location order */
function updateSortOrder( sortedItem ) {
	var postId, routeId, ajaxData,
		updateNonce		 = $( "#nwm-destination-list" ).data( "nonce-sort" ),
		i				 = 1,
		routeOrder		 = "",
		postIds			 = "";
	
	/* Loop over the tr elements and collect the nwm-id's */
	$( "#nwm-destination-list tbody tr" ).each( function () {
		postId		= $(this).data( "post-id" );
		routeId		= $(this).data( "nwm-id" );
		routeOrder += routeId+',';
					
		/* 
		We use this list to determine if we need to grab a 
		new thumbnail / excerpt when the user saves a wp post.
		*/
		if ( postId ) {
			postIds += postId+',';
		}
		
		i++;
	});
	
	rebuildDropdowns();
	
	$( "#nwm-edit-destination #nwm-form" ).hide();
	
	/* Remove the trailing , */
	routeOrder = routeOrder.substring(0, routeOrder.length - 1);
	postIds	   = postIds.substring(0, postIds.length - 1);
	
	if ( typeof( sortedItem ) !== "undefined" ) {
		sortedItem.find( ".delete-nwm-destination" ).after( "<img class='nwm-preloader' src=" + preloadImgSrc + " />" ).show();
	}
	
	/* Set the data for the ajax call */	
	ajaxData = {
		action: "update_order",
		route_order: routeOrder,
		post_ids: postIds,
		map_id: $( "#nwm-map-list" ).val(),
		 _ajax_nonce: updateNonce
	};

	$.post( ajaxurl, ajaxData, function( response ) {			
		if ( response == -1 ) {
			alert( nwmL10n.securityFailed );
		} else {
			rebuildFlightPlan();	
		}
		
		$( ".nwm-preloader" ).remove();
	});		
	
	setTdWidth();
}

/* Populate the dropdown list of destination to edit in the updated order */
function populateEditDropdown( dropdownList ) {
	var dropdown = "<option selected='selected'> - " + nwmL10n.selectDestination + " - </option>";
		dropdown += dropdownList;
		
	$( "#nwm-edit-list" ).html( dropdown );
}

/* Populate the sort dropdown list */
function populateSortDropdown( dropdownList ) {
	var dropdown = "", 
		dropdownPosition = "<option value='0'> - " + nwmL10n.locationPosition + " - </option>",
		dropdownCurrent  = "<option value='-1' selected='selected'>" + nwmL10n.currentPosition + "</option>";
	
	if ( $( "#nwm-edit-destination" ).hasClass( "nwm-active" ) ) {
		dropdown += dropdownCurrent;
	}
	
	dropdown += dropdownPosition;
	dropdown += dropdownList;
		
	$( "#nwm-position" ).html( dropdown );
}

/* Update the destination count */
function updateDestinationCount() {
	var i = 1;
	$( "#nwm-destination-list tbody .nwm-order" ).each( function(){
		$(this).html( "<span>" + i + "</span>" );
		i++;
	});
}

/* We need to set the width of each td element to prevent the tr from collapsing when it's moved around with sortable */
function setTdWidth() {
	$( "#nwm-wrap td" ).each( function() {
		$(this).css( "width", $( this ).width() + "px" );
	});
}

function resetTravelDates() {
	$( ".nwm-dates input, #nwm-custom-desc" ).val( "" );	
}

/* Make the tr elements sortable */
$( "#nwm-destination-list tbody" ).sortable({
	containment: "parent",
	cancel: ".fixed",
	axis: "y",
	tolerance: "pointer",
	update: function( event, ui ) {
		updateDestinationCount(),
		updateSortOrder(ui.item)
	}
});
	
$( "#nwm-menu li a" ).on( "click", function() {
	var form, id = $( this ).attr( "href" );
		id = id.replace( "#", "" );
	
	$( "#nwm-search-link span" ).html('');
	$( "#nwm-menu li" ).removeClass();
	$( this ).parent( "li" ).addClass( "nwm-active-item" );
	$( ".nwm-tab" ).removeClass( "nwm-active" );
	$( ".nwm-location-wrap .nwm-box" ).hide();
	$( "#nwm-destination-wrap input" ).removeClass( ".nwm-error" );
	
	$( "#nwm-country-code, #nwm-latlng" ).val( "" );
					
	if ( id == "nwm-edit-destination" ) {
		$( "#nwm-position" ).find( "option" ).removeAttr( "selected" );
		$( "#nwm-position" ).find( "option" ).first().before( "<option selected='selected' value='-1'>" + nwmL10n.currentPosition + "</option>" );
		
		editForm();
	} else {
		form = $( "#nwm-edit-destination form" ).clone(true);	
		$( "#nwm-add-destination form" ).remove();
				
		if ( $( "#nwm-add-destination #nwm-form" ).length == 0 ) {
			$( "#nwm-add-destination" ).html(form);
			$( "#nwm-add-destination #nwm-form, #nwm-blog-excerpt" ).show();
			$( "#nwm-searched-location" ).val( "" );
			$( "#nwm-custom-text" ).hide();
			$( ".nwm-delete-btn-wrap" ).remove();
		}
		
		/* Instead of a thumbnail image, show the default placeholder */
		$( "#nwm-reset-thumb" ).trigger( "click" );
		
		/* 
		Sometimes if you switch between the add and edit location tabs, 
		both the travel schedule and blog post excerpt would be set to selected
		To prevent this, we remove all of the them and only set te first one as selected.
		*/
		$( "#nwm-marker-content-option option[selected='selected']" ).removeAttr( "selected" ); // fails with removeProp?
		$( "#nwm-marker-content-option option:first" ).prop( "selected", true );
		
		$( "#nwm-update-trip" ).val( "Save" ).attr( "id", "nwm-add-trip" );
		$( "#" + id + "" ).addClass( "nwm-active" );
				
		/* If the first item is the 'current position' option then remove it */
		if ( $( "#nwm-position option" ).first().val() == -1 ) {
			$( "#nwm-position option" ).first().remove();
		}
		
		/* Make sure the date description is shown when required */
		if ( $( "#nwm-marker-content-option" ).val() == "nwm-blog-excerpt" ) {
			$( ".nwm-date-desc" ).show();
		}
		
		rebuildFlightPlan();
		resetTravelDates();
	}
	
	loadCalendar();
		
	return false;
});

/* Show the edit form */
function editForm() {
	var contentState = $( "#nwm-marker-content-option" ).val(),
		form		 = $( "#nwm-add-destination form" ).clone( true );	
		
	$( "#nwm-edit-list option:first" ).prop( "selected", true );
	$( "#nwm-add-destination form" ).remove();
	$( "#nwm-edit-list" ).removeClass( "nwm-error" );
	
	if ( $( "#nwm-edit-destination #nwm-form" ).length == 0 ) {
		$( "#nwm-edit-destination p" ).after( form );
	}
	
	$( "#nwm-edit-destination #nwm-form" ).hide();

	/* Check if we need to show the search post title field, or the custom textarea */
	if ( contentState == "nwm-custom-text" ) {
		$( "#nwm-blog-excerpt" ).hide();
		$( "#nwm-custom-text" ).show();
	} else {
		$( "#nwm-blog-excerpt" ).show();
		$( "#nwm-custom-text" ).hide();	
	}
	
	$( "#nwm-add-trip" ).val( "Update" ).attr( "id", "nwm-update-trip" );
	$( "#nwm-post-type" ).val();
	$( "#nwm-edit-destination" ).addClass( "nwm-active" );
	
	/* Remove the error class from the input / textarea field */
	removeErrorClasses();
}

/* Handle the clicks on the update button */
$( "#nwm-form" ).on( "click", "#nwm-update-trip", function() {	
	var tripData	= {},
		latlng		= $( "#nwm-latlng" ).val(),
		countryCode = $( "#nwm-country-code" ).val(),
		location	= $( "#nwm-searched-location" ).val();
	
	/* Check if we have all the data we need */
	if ( checkFormData( latlng, location ) ) {
		tripData = {
			markerContentOption: $( "#nwm-marker-content-option" ).val(),
			updateNonce: $( "#nwm-edit-destination" ).find( "input[name=update_nonce]" ).val(),
			nwmId: $( "#nwm-edit-list" ).val(),
			thumbId: $( "#nwm-thumb-wrap img" ).attr( "data-img-id" ),
			countryCode: countryCode,
			location: location,
			latlng: latlng
		};

		/* Make sure there is a valid ID */
		if ( !isNaN( tripData.nwmId ) ) {					
			showPreloader( $( ".nwm-delete-btn-wrap" ) );
			updateDestination( tripData );
		} else {
			$( "#nwm-edit-list" ).addClass( "nwm-error" );
		}		
	}
	
	return false;
});	

/* Search for a blog post that belongs to the supplied title */
$( "#nwm-blog-excerpt" ).on( "click", "input[type=button]", function( e ) {
	var postTitle = $( "#nwm-post-title" ).val(),
		ajaxData;
		
	$( "#nwm-post-title" ).removeClass( "nwm-error" );	

	if ( postTitle ) {
		$( "#nwm-add-destination input" ).removeClass( "nwm-error" );
		$( "#nwm-nonce-fail" ).remove();
		
		ajaxData = {
			action: "find_post_title",
			post_title: postTitle,
			_ajax_nonce: $( "#nwm-search-nonce" ).val()
		};
	
		/* Show the preloader next to the clicked button */
		showPreloader( $(this) );
	
		$.post( ajaxurl, ajaxData, function( response ) {				
			if ( response == -1 ) {
				$( "#nwm-add-trip" ).after( "<span id='nwm-nonce-fail'>" + nwmL10n.securityFailed + "</span>" );
			} else {									
				if ( !response.post.id ) { 
					$( "#nwm-search-link span" ).html( "<strong>" + nwmL10n.noPostsFound + "</strong>" );
					$( "#nwm-post-title" ).val( "" );
				} else {
					$( "#nwm-search-link span" ).html( "<a href=" + response.post.permalink + " target='_blank'>" + response.post.permalink + "</a>" );
					$( "#nwm-post-id" ).val( response.post.id );
					$( "#nwm-post-title" ).val( "" );
					
					if ( response.post.thumb != null ) {
						setLocationThumb( response.post.thumb, response.post.thumb_id ); 
					} else {
						$( "#nwm-reset-thumb" ).trigger( "click" );
					}
				}
			}
			
			$( ".nwm-preloader" ).remove();	
		});
	
	} else {
		$( "#nwm-post-title" ).addClass( "nwm-error" );	
	}
				
	e.preventDefault();
});

/* Handle changes to the edit list */
$( "#nwm-edit-list" ).change( function() {
	$( "#nwm-edit-list" ).removeClass( "nwm-error" );
	setEditFormContent( $( this ).val() );
});

/* Set the travel dates for the selected location */
function setTravelDates( routeId ) {
	var $tr = $( "[data-nwm-id=" + routeId + "]" ),
		countryCode		  = $tr.attr( "data-country" ),
		arrivalDate		  = $tr.find( "input[name=arrival_date]" ).val(),
		arrivalFullDate	  = $tr.find( ".nwm-arrival span" ).text(),
		departureDate     = $tr.find( "input[name=departure_date]" ).val(),
		departureFullDate = $tr.find( ".nwm-departure span" ).text();
	
	$( "#nwm-from-date" ).val( arrivalDate );
	$( "#nwm-form input[name=from_date]" ).val( arrivalFullDate );
	$( "#nwm-till-date" ).val( departureDate );	
	$( "#nwm-form input[name=till_date]" ).val( departureFullDate );
	$( "#nwm-edit-destination #nwm-country-code" ).val( countryCode );
}

/* Set the content of the edit form based on the nwm post id */
function setEditFormContent( dropdownValue ) {
	var routeId, thumbId, thumbSrc, postType, destination, latlng, postId, travelSchedule, url, tr;
	
	$( ".nwm-dates input" ).val( "" );
	
	if ( !isNaN( dropdownValue  ) ) {
		$( "#nwm-edit-destination form" ).show();

		$( "#nwm-destination-list tbody tr" ).each( function( index ) {
			routeId = $(this).attr( "data-nwm-id" );

			/* Check if the ID on the tr element matches with the id from the dropdown list */					
			if ( routeId == dropdownValue ) {
				destination	   = $( "[data-nwm-id=" + routeId + "] .nwm-location" ).text();
				tr			   = $( "#nwm-destination-list tbody tr:eq(" + index + ")" );
				latlng		   = tr.attr( "data-latlng" );
				postId		   = tr.attr( "data-post-id" );
				thumbId		   = tr.find( ".nwm-thumb-td img" ).attr( "data-thumb-id" );
				thumbSrc	   = tr.find( ".nwm-thumb-td img" ).attr( "src" );
				travelSchedule = tr.attr( "data-travel-schedule" );
				
				/* If we have no src then show the placeholder */
				if ( typeof( thumbSrc ) !== "undefined" ) {
					setLocationThumb( thumbSrc, thumbId );
				} else {
					$( "#nwm-reset-thumb" ).trigger( "click" );
				}
			
				/* Check if we need to add the edit button, or just need to update the nonce value */				
				checkEditButton( routeId );

				/* Check if we need to show the excerpt edit fields, the custom text or the travel schedule */
				if ( postId != 0 ) {
					postType = "blog";
					url = $( "[data-nwm-id=" + routeId + "] .nwm-url" ).html();
					$( "#nwm-search-link span" ).html( url );
					$( "#nwm-marker-content-option option[value=nwm-blog-excerpt]" ).attr( "selected", "selected" );
					$( "#nwm-blog-excerpt").show();
					$( "#nwm-custom-text" ).hide();
					$( ".nwm-dates input" ).attr( "placeholder", "optional" );
										
					$( "#nwm-marker-content-option" ).change( function() {
						$( "#nwm-custom-url, #nwm-custom-desc, #nwm-custom-title" ).val( "" );
					});
				} else if ( travelSchedule ) {
					postType = "schedule";
					$( "#nwm-blog-excerpt, #nwm-custom-text, .nwm-date-desc" ).hide();
					$( "#nwm-marker-content-option option[value=nwm-travel-schedule]" ).attr( "selected", "selected" );
					$( "#nwm-search-link span").html( "" );
					$( ".nwm-dates input" ).attr( "placeholder", "" );
				} else {
					postType = "custom";
					activateCustomText();
					loadCustomText( routeId );
				}
				
				/* Set the travel dates for the selected location */
				setTravelDates( routeId );
				
				$( "#nwm-searched-location" ).val( destination ).focus();
				$( "#nwm-latlng" ).val( latlng );
				$( "#nwm-post-id" ).val( postId );
				$( "#nwm-post-type" ).val( postType );
				
				/* Show a single draggable marker on the map */
				setDraggableLocation( latlng, zoom = 6 );
	
				return false;
			}
		});
		
	} else {
		$( "#nwm-edit-destination form" ).hide();	
	}	
}

function setDraggableLocation( latlng, zoom ) {
	var latlng, location, alt, draggable;
		
	latlng = latlng.split( ",", 2),
	location = new google.maps.LatLng( latlng[0], latlng[1] );
	
	/* Remove all existing markers and route lines */
	deleteOverlays();
	
	/* Add the draggable marker to the map */
	addMarker( location, alt = "", draggable = true );
	
	/* Focus to the selected location */
	map.setCenter( location );
	map.setZoom( zoom );	
}

/* Show the edit desintation custom text form */
function activateCustomText() {
	$( ".nwm-dates input" ).attr( "placeholder", "optional" );
	$( "#nwm-search-link span" ).html( "" );
	$( "#nwm-marker-content-option option[value=nwm-custom-text]" ).prop( "selected", true );
	$( "#nwm-blog-excerpt, .nwm-date-desc" ).hide();
	$( "#nwm-custom-text" ).show();
}

/* Try to load the custom text that belongs to the selected destination */
function loadCustomText( routeId ) {
	var loadNonce = $( "[data-nwm-id=" + routeId + "]" ).find( "input[name=load_nonce]" ).val(),
		ajaxData = {
			action: "load_content",
			nwm_id: routeId,
			 _ajax_nonce: loadNonce
		};
	
	$( "#nwm-custom-text label[for=nwm-custom-desc]" ).after( "<img class='nwm-preloader' src=" + preloadImgSrc + " />" );
	
	$.post( ajaxurl, ajaxData, function( response ) {	
		if ( response == -1 ) {
			alert( nwmL10n.securityFailed );
		} else {			
			if( response.success ) {
				$( "#nwm-custom-url" ).val( response.url );
				$( "#nwm-custom-desc" ).val( response.content );
				$( "#nwm-custom-title" ).val( response.title );
				$( "textarea[data-length]" ).limitMaxlength();
			} else {
				alert( nwmL10n.loadFailed );
			}
		}
		
		$( ".nwm-preloader" ).remove();	
	});		
}

/* 
Check if the delete button + nonce field exists, if not we add a new span and the button + nonce. 
Otherwise just update the nonce value that belongs to the selected dropdown item 
*/
function checkEditButton( routeId ) {
	var deleteData,
		$trElement  = $( "[data-nwm-id='" + routeId + "']" ),
		updateNonce = $trElement.find( "input[name=update_nonce]" ).val(),
		deleteNonce = $trElement.find( "input[name=delete_nonce]" ).val();

	if ( $( "#nwm-update-trip" ).next().hasClass( "nwm-delete-btn-wrap" ) ) {
		$( ".nwm-delete-btn-wrap input[name=delete_nonce]" ).val( deleteNonce );
		$( ".nwm-delete-btn-wrap input[name=update_nonce]" ).val( updateNonce );
	} else {
		deleteData = $trElement.find( "td" ).last().html();
		$( "#nwm-update-trip" ).after( "<span class='nwm-delete-btn-wrap'>" + deleteData + "</span>" );
		$( "#nwm-form .delete-nwm-destination" ).addClass( "nwm-edit-form" );
		
		$( ".nwm-delete-btn-wrap" ).on( "click", "input", function() {
			var elem		= $(this),		
				deleteNonce = elem.next().val(),
				routeId		= $( "#nwm-edit-list" ).val(),
				postId		= $( "#nwm-post-id" ).val();
	
			showPreloader( elem );
			deleteDestination( routeId, postId, deleteNonce );
		});
	}	
}

/* Check if we need to enable the calendars */
if ( $( "#nwm-from-date" ).length ) {
	loadCalendar(); 
}

/* Bind the jquery UI datepickers */
function loadCalendar() {	
	$( "#nwm-from-date" ).removeClass( "hasDatepicker" ).datepicker({
													 altFormat: "@",
													 dateFormat: "yy-mm-dd",
													 onClose: function( dateText, inst ) {
																	var formatedFromDate = $.datepicker.formatDate( "MM dd, yy", $(this).datepicker( "getDate" ));
																	if( formatedFromDate ) {
																		$(this).closest( "form" ).find( "[name=from_date]" ).val( formatedFromDate );
																	}
																}
												 	});
	
	$( "#nwm-till-date" ).removeClass( "hasDatepicker" ).datepicker({ 
													 altFormat: "@",
													 dateFormat: "yy-mm-dd",
													 onClose: function( dateText, inst ) {
																	var formatedTillDate = $.datepicker.formatDate( "MM dd, yy", $(this).datepicker( "getDate" ));
																	if( formatedTillDate ) {
																		$(this).closest( "form" ).find( "[name=till_date]" ).val( formatedTillDate );
																	}
																}
												 	});	
}

/* update the hidden input field with the current lat/long values. */
function setCurrentCoordinates( clickedCoordinates ) {
	var coordinates = stripCoordinates( clickedCoordinates ),
		lat			= roundLatlng( coordinates[0], 6 ),
		lng			= roundLatlng( coordinates[1], 6 );
		
	$( "#nwm-searched-location" ).removeClass( "nwm-error" );
	$( "#nwm-latlng" ).val( lat + ',' + lng );
}

$( "#nwm-add-map" ).on( "click", function() {	
	$( "#nwm-add-map-box" ).dialog({
		width: 325,
		resizable : false,
		modal: true,
		title: nwmL10n.addMapName
	});
	
	$( "#nwm-add-map-box .dialog-cancel" ).on( "click", function() {	
		$( "#nwm-add-map-box").dialog( "close" ); 
	});
	
	return false;
});

$( ".nwm-edit-name" ).on( "click", function() {	
	var mapName = $(this).parents( "td" ).find( ".nwm-current-name" ).text(),
		mapId   = $(this).parents( "tr" ).find( "input[type=checkbox]" ).val();
		
	$( "#nwm-edit-name-box" ).dialog({
		width: 325,
		resizable : false,
		modal: true,
		title: nwmL10n.editMapName
	});

	$( "#nwm-edit-name-box input[type=text]" ).val( mapName );
	$( "#nwm-map-id" ).val( mapId );
	
	$( "#nwm-edit-name-box .dialog-cancel" ).on( "click", function() {	
		$( "#nwm-edit-name-box" ).dialog( "close" ); 
	});
	
	return false;
});

/* Lookup the provided location name with the Google Maps API */
$( "#find-nwm-location" ).on( "click", function() {	
	codeAddress();
});

/* Activate the colorpicker on the option screen */
$( "#nwm-past-color, #nwm-future-color" ).wpColorPicker();

if ( $( "#nwm-control-left" ).is( ":checked" ) )  {
	var controlPosition = "left";	
} else {
	var controlPosition = "right";
}

/* 
If the map view is set to the page wide option, the map controls need to be aligned to the left. 
But if they change the value again, we set it back to how it was on pageload.
*/
$( "#nwm-content-option" ).change( function() {
	if ( $(this).val() == "carousel_corner" ) {
		$( "#nwm-control-left" ).prop( "checked", true );
		$( "#nwm-control-right" ).prop( "checked", false );
	} else {
		if ( controlPosition == "left" ) {
			$( "#nwm-control-left" ).prop( "checked", true );
			$( "#nwm-control-right" ).prop( "checked", false );
		} else {
			$( "#nwm-control-right" ).prop( "checked", true );	
			$( "#nwm-control-left" ).prop( "checked", false );
		}
	}
});

/* Make sure we check the textarea for a input limit */
$.fn.limitMaxlength = function( options ){

	var settings = $.extend({
		attribute: "data-length",
		onLimit: function(){},
		onEdit: function(){}
	}, options);

	// Event handler to limit the textarea
	var onEdit = function(){
		var textarea = jQuery(this),
			maxlength = parseInt(textarea.attr(settings.attribute)),
			text = textarea.val(),
			limited;

		if ( text === "" ) {
			wordcount = 0;
		} else {
			wordcount = $.trim(text).split(" ").length;
		}
		
		if ( wordcount >= maxlength ) {
			$( "#char-limit" ).html( "<em><span style='color: #DD0000;'>" + nwmL10n.noWordsRemaining + "</span></em>" );
			limited = $.trim(text).split(" ", maxlength);
			limited = limited.join(" ");
			$(this).val(limited);
		} else {
			$( "#char-limit" ).html( "<em>" + (maxlength - wordcount) + ' ' + nwmL10n.wordsRemaining + "</em>" );
		} 
	};

	this.each(onEdit);

	return this.keyup(onEdit)
				.keydown(onEdit)
				.focus(onEdit)
				.on( "input paste", onEdit);
}

/* Trigger the input limit for the textarea */
$( "textarea[data-length]" ).limitMaxlength();

/* If a map_id exist in the url, change the dropdown value so that the correct map data is loaded */
if ( url.indexOf( "map_id" ) != -1 ) {
	var urlsplit = url.split( "&map_id=" );	

	if ( parseInt( urlsplit[1] ) ) {
		$( "#nwm-map-list option[selected='selected']" ).removeAttr( "selected" );
		$( "#nwm-map-list option[value=" + urlsplit[1] + "]" ).attr( "selected", "selected" );
		$( "#nwm-map-list" ).trigger( "change" );
	}
}

/* 
If the option to show the flightpath is changed, either show/hide the option to activate it. 
No point it showing the curve option if the flightpath itself is disabled 
*/
$( "#nwm-flightpath" ).change( function() {
	if ( this.checked ) {
		$( ".nwm-curved-option" ).show();
    } else {
		$( ".nwm-curved-option" ).hide();
	}	
});

/* Handle the selection of custom thumbnails */
$(document.body).on( "click", "#nwm-media-upload", function( e ) {
	e.preventDefault();

	if ( uploadFrame ) {
		uploadFrame.open();
		return;
	}

	uploadFrame = wp.media.frames.uploadFrame = wp.media({
		title: nwmL10n.locationImage,
		frame: "select",
		multiple: false,
		library: {
			type: "image"
		},
		button: {
			text: "upload"
		}
	});

	uploadFrame.on( "select", function(){
		var media_attachment = uploadFrame.state().get( "selection" ).first().toJSON();

		if ( media_attachment.sizes.length > 0 ) {
			setLocationThumb( media_attachment.sizes.thumbnail.url, media_attachment.id ); 
		} else {
			setLocationThumb( media_attachment.url, media_attachment.id );
		}

	});

	uploadFrame.open();
});

/* Show the selected thumb */
function setLocationThumb( thumbUrl, thumbId ) {
	if ( $( "#nwm-thumb-wrap img" ).length ) {
		$( "#nwm-destination-wrap img.nwm-circle" ).attr({ "src" : thumbUrl, "data-img-id" : thumbId  });
	} else {
		var img = "<img class='nwm-circle' data-img-id=" + thumbId + " src=" + thumbUrl + " width='64' height='64' />";
		$( "#nwm-destination-wrap span.nwm-thumb" ).before( img );
		$( "#nwm-destination-wrap .nwm-thumb" ).hide();			
	}	
}

/* Remove the selected thumbnail and show the default placeholder that is used on the map */
$( "#nwm-reset-thumb" ).on( "click", function() {
	$( "#nwm-destination-wrap img.nwm-circle" ).remove();
	$( "#nwm-destination-wrap .nwm-thumb" ).show();	
});

/* Widgets -> Toggle the input fields for manual / auto location detection */
$( document ).on( "change", "#nwm-display-options select", function() {
	var radioValue = $(this).val(),
		$parent = $(this).parents( "form" ); // For some reason, accessing the id directly fails. It only works if we first select the form parent?
		
	if ( radioValue == "text_style" ) {
		$parent.find( "#nwm-zoom-level" ).hide();
	} else {
		$parent.find( "#nwm-zoom-level" ).show();
	}
		
	if ( radioValue == "map_style" ) {
		$parent.find( "#nwm-location-flag" ).hide(); 
	} else {
		$parent.find( "#nwm-location-flag" ).show(); 
	}
});

$( "#nwm-content-options input[type=radio]" ).change(function() {
	if ( $(this).val() == "tooltip" ) {
		$( "#nwm-hide-tooltip" ).show();
	} else {
		$( "#nwm-hide-tooltip" ).hide();
	}
});

/* Widgets -> Toggle the display map option, only show it if we show the location as text */
$( document ).on( "change", "#nwm-location-detection select", function() {
	var radioValue = $(this).val(),
		$parent = $(this).parents( "form" ); // For some reason, accessing the id directly fails. It only works if we first select the form parent?

	if ( radioValue == "manual_location" ) {
		$parent.find( "#nwm-manually" ).show();
		$parent.find( "#nwm-automatically" ).hide();
	} else {
		$parent.find( "#nwm-manually" ).hide();
		$parent.find( "#nwm-automatically" ).show();
	}
});

/* Detect changes for the readmore option on the settings page */
$( "#nwm-readmore" ).on( "change", function() {
	$( "#nwm-custom-readmore" ).toggle();
});

/* Handle clicks on the 'preview' button on the edit route page */
$( "#preview-nwm-latlng" ).on( "click", function() {
	var i, adjustedLatlng = $( "#nwm-latlng" ).val();

	if ( markersArray ) {
		for ( i = 0; i < markersArray.length; i++ ) {
			if ( markersArray[i].draggable ) {
				changeMarkerPosition( adjustedLatlng, i );
				
				break;
			}
		}
	}
});

function changeMarkerPosition( adjustedLatlng, i ) {
	var latlng = adjustedLatlng.split( "," );
		latlng = new google.maps.LatLng( latlng[0], latlng[1] );	

	markersArray[i].setPosition( latlng );
}
/* Load the map */
if ( $( "#gmap-nwm" ).length ) {
	initializeGmap();
}
                
});
