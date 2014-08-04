jQuery( document ).ready( function( $ ) {

if ( $( "#nwm-map-0" ).length ) {
	$( ".nwm-wrap" ).eq(0).parent().wrap( "<div id='nwm-outer'>", "</div>" );
}

var flightPath = [],
	futureFlightPath = [],
	loadedImageList = [],
	placeholder = nwmSettings.path + "img/spacer.gif",
	streetViewVisible = ( nwmSettings.streetView == 1 ) ? true : false,
	newMarker = new google.maps.MarkerImage( nwmSettings.path + "/img/marker.png",
		new google.maps.Size( 30,30 ),
		new google.maps.Point( 0,0 ),
		new google.maps.Point( 8,8 )
	);	

/**
 * Initialize the map
 *
 * @since 1.2
 * @return void
 */
function initializeGmap() {	
	var mapId, mapType, zoomControlPosition, zoomControlStyle, zoomTo, mapType, zoomLevel;
	
	/* Set correct postion of the controls */		
	if ( nwmSettings.controlPosition == "right" ) {
		zoomControlPosition = google.maps.ControlPosition.RIGHT_TOP
	} else {
		zoomControlPosition = google.maps.ControlPosition.LEFT_TOP
	}

	/* Set correct control style */	
	if ( nwmSettings.controlStyle == "small" ) {
		zoomControlStyle = google.maps.ZoomControlStyle.SMALL
	} else {
		zoomControlStyle = google.maps.ZoomControlStyle.LARGE
	}

	/* Initialize the map(s) */
	$( ".nomad-world-map" ).each( function( i ) {			
		mapId = $(this).attr( "id" );
		var nwmLocations = "";
		
		/* Get the correct location data */
		nwmLocations = window['nwmMap_'+i];
		
		/* For each map we set the correct maptype, zoomlevel and content location */
		mapType   = setMapType( nwmLocations );
		zoomLevel = parseInt( nwmLocations.settings.zoomLevel );
		
		/* Check if we need to remove the slider, and show the content in the tooltip instead */
		if ( nwmLocations.settings.contentLocation == "tooltip" ) {
			$( "#" + mapId + "" ).parent( ".nwm-wrap" ).addClass( "nwm-no-slider" );
		}

		/* Initialize the map */			
		$( "#" + mapId + "" ).gmap3({
			map:{
				options:{
				  center: [zoomTo],
				  scrollwheel: false,
				  mapTypeControl: false,
				  navigationControl: false,
				  panControl: false,
				  zoom: zoomLevel,
				  mapTypeId: mapType,
				  streetViewControl: streetViewVisible,
				  zoomControlOptions: {
						style: zoomControlStyle,
						position: zoomControlPosition
					}
				},
				callback: function( map ) {
					if ( map.getBounds() ) {
						processLocationData( $( this ), nwmLocations, i );
					} else {
						$(this).gmap3({
							map:{
								onces:{
									bounds_changed: function(){
										processLocationData( $( this ), nwmLocations, i );
									}
								}
							}
						});
					}
				}
			}
		}); //end gmap3 init
		i++;
	});
}

/**
 * Get the correct value for the maptType
 *
 * @since 1.2
 * @param {object} The map data
 * @return {string} mapType The correct Google Map maptype
 */
function setMapType( nwmLocations ) {
	var mapType;
	
	if ( typeof( nwmLocations.settings.mapType !== "undefined" ) ) {
		mapType = nwmLocations.settings.mapType;
	} else {
		mapType = nwmSettings.mapType;
	}

	/* Set the selected map type */
	switch( mapType ) {
		case "roadmap":
		  mapType = google.maps.MapTypeId.ROADMAP
		  break;
		case "satellite":
		  mapType = google.maps.MapTypeId.SATELLITE
		  break;
		case "hybrid":
		  mapType = google.maps.MapTypeId.HYBRID
		  break;
		case "terrain":
		  mapType = google.maps.MapTypeId.TERRAIN
		  break;		  
		default:
		  mapType = google.maps.MapTypeId.ROADMAP
	}

	return mapType;
}

/**
 * Loop over the location data. 
 * 
 * If lines between markers are enabled we build the flightpath arrays and draw the lines on the map. 
 * Otherwise we only send the collected location data to the addDestination function. 
 *
 * @since 1.0
 * @param {object} $this The current map we are working with
 * @param {object} nwmLocations Holds the location data
 * @param {number} mapId The ID of the map we are working with
 * @return void
 */
function processLocationData( $this, nwmLocations, mapId ) {
	var futurePathCount = 0,
		i = 0,
		zoomToIndex = nwmLocations.settings.zoomToIndex,
		curvedLines = ( nwmSettings.curvedLines == 1 ) ? true : false;

	/* Reset the flightpaths, otherwise line data from the previous map will up on all the maps that follow it */
	flightPath.length = 0;
	futureFlightPath.length = 0;
		
	if ( $( "#nwm-map-1" ).length ) {
		mapId = mapId-1;
	} else {
		mapId = 0;
	}
	
	/* Loop over the location data */
	for ( var key in nwmLocations.locationData ) {
		if ( nwmLocations.locationData.hasOwnProperty( key ) ) {
			
			/* Only create a flightpath if the lines are enabled */
			if ( nwmLocations.settings.lines == 1 ) {
				if ( ( nwmLocations.locationData[i].data.future ) || ( futurePathCount > 0 ) ) {

					/* If this is the first item that is set in the future, select the previous item so we know where to start drawing the other line color */
					if ( futurePathCount == 0 ) {
						if ( i > 1 ) {
							futureFlightPath.push( [nwmLocations.locationData[i-1].lat, nwmLocations.locationData[i-1].lng] );
						}
					}

					if ( i == 1 ) {
						flightPath.push( [nwmLocations.locationData[i].lat, nwmLocations.locationData[i].lng] );
					}

					futureFlightPath.push( [nwmLocations.locationData[i].lat, nwmLocations.locationData[i].lng] );	
					futurePathCount++;
				} else {
					flightPath.push( [nwmLocations.locationData[i].lat, nwmLocations.locationData[i].lng] );
				}
			} // end lines check
			
			/* Add the marker the correct map and if enabled, create the html slider */
			addDestination( $this, i, nwmLocations.locationData[i], zoomToIndex, mapId );
		}
		i++;
	}
	
	/* Check if we need to draw lines between the markers */
	if ( nwmLocations.settings.lines == 1 ) {
		
		/* Check if we need to draw a line for past locations */
		if ( flightPath.length ) {
			$this.gmap3({ 
				polyline:{
					options:{
					  strokeColor: nwmSettings.pastLineColor,
					  strokeOpacity: 1.0,
					  strokeWeight: 2,
					  geodesic: curvedLines,
					  path: flightPath
					}
				}
			});
		}
		
		/* Check if we need to draw a line for future locations */
		if ( futureFlightPath.length ) {
			$this.gmap3({ 
				polyline:{
					options:{
					  strokeColor: nwmSettings.futureLineColor,
					  strokeOpacity: 1.0,
					  strokeWeight: 2,
					  geodesic: curvedLines,
					  path: futureFlightPath
					}
				}
			});
		}
	}
	
	/* Only bind the back and forward buttons if the content is set to slider */
	if ( nwmLocations.settings.contentLocation == 'slider' ) {
		enableControls( mapId );
	}
}

/**
 * Add the destination data to the map.
 * 
 * If enabled, creates the html output for the slider. Adds the markers to the correct location on the map and
 * sets the correct location values, also binds the mouseover events for the tooltip / slider.
 *
 * @since 1.0
 * @param {object} $this The current map we are working with
 * @param {number} i Keeps track of the amount of destinations added to the map, is used to number the marker content and slider li items
 * @param {object} destination Holds the location data
 * @param {number} zoomToIndex Contains the marker index we should zoom to on page load ( first, last, first future item )
 * @param {number} mapId The ID of the map we are working with
 * @return void
 */
function addDestination( $this, i, destination, zoomToIndex, mapId ) {	
	var $sliderTarget = $( "#nwm-map-" + mapId + "" ).next().find( "ul" );

	$this.gmap3({ 
		marker:{
			latLng: [destination.lat, destination.lng],
			options: {
				icon: newMarker
			},
			events:{
			click: function( marker ){
					$(this).gmap3({clear: "overlay" });
				}
			},
			callback: function( marker ) {
				var markerData = {};
				
				/* Check if there is an active slider or not, if so we add the slider content */
				if ( !$( "#nwm-map-" + mapId + "" ).parent( ".nwm-wrap" ).hasClass( "nwm-no-slider" ) ) {	
					var destinationHtml,
						content = destination.data.content;
					
					markerData = getMarkerData( destination, nwmSettings, 'slider' );
					content = content + markerData.readMore;					
											
					if ( destination.data.arrival ) {
						if ( destination.data.departure ) {	
							destinationHtml = '<li data-id="' + destination.data.nwm_id + '">' + markerData.thumb + '<h2>' + markerData.title + '</h2><p class="nwm-travel-schedule"><span>' + destination.data.arrival + '</span><span> - ' + destination.data.departure + '</span></p><p>' + content + '</p></li>';
						} else {
							destinationHtml = '<li data-id="' + destination.data.nwm_id + '">' + markerData.thumb + '<h2>' + markerData.title + '</h2><p class="nwm-travel-schedule"><span>' + destination.data.arrival + '</span></p><p>' + content + '</p></li>';
						}
					} else {
						destinationHtml = '<li data-id="' + destination.data.nwm_id + '">' + markerData.thumb + '<h2>' + markerData.title + '<span>' + destination.data.date + '</span></h2><p>' + content + '</p></li>';
					}
									
					$sliderTarget.append( destinationHtml );
					
					/* On mouseover we move the map to the corresponding location */
					$sliderTarget.find( "li" ).eq( i ).bind( "mouseover", function( ) {
						$this.gmap3( "get" ).panTo(marker.position);

						/* Make sure we haven't already added the marker content */
						if ( !$( "#nwm-map-" + mapId + ".marker-" + i + "" ).length ) {
							$this.gmap3(
							  {clear:"overlay"},
								 {
									overlay:{  /* Show the overlay with the location name at the marker location */
										latLng: marker.position,
										options:{
									    content: "<div class='marker-style marker-" + i + "'>" + destination.data.location + "</div>",
										  offset: {
											x:11,
											y:-15
										  }
										}
									}
								});
							}
					});
					
					/* 
					Check which marker we need to set active on page load, either the first / last one, 
					or the one just before the future route starts
					*/	
					if ( i == zoomToIndex ) {
						$sliderTarget.find( "li:eq(" + zoomToIndex + ")" ).addClass( "nwm-active-destination" ).mouseover();

						/* If the active destination contains a span, it means it's a custom / future date and we don't need to load any thumbnails */
						if ( !$sliderTarget.find( ".nwm-active-destination span.nwm-thumb" ).length ) {
							imageLoader( $sliderTarget );
						}
					}
					
					/* 
					This fixes a case where the settings are set to focus on the last marker before the future route starts, 
					but no previous entry exist before the future route starts. So instead we just focus on the first marker we find (the first future entry). 
					Not what the user selected, but no other way to fix it?
					 */
					if ( ( zoomToIndex == -1 ) && ( i == 0 ) ) {
						$( "#nwm-map-" + mapId + " .nwm-destination-list li:first-child" ).addClass( "nwm-active-destination" ).mouseover();					
					}
				} else {
					if ( i == zoomToIndex ) {
						markerData = getMarkerData( destination, nwmSettings, "tooltip" );
						markerContent = '<div class="marker-style marker-' + i + '"><div class="nwm-marker-wrap">' + markerData.thumb + '<div class="marker-txt"><h2>' + markerData.title + '</h2><p>' + markerData.date + markerData.readMore + '</p></div></div></div>';
				
						if ( nwmSettings.hideTooltip != 1 ) { 
							$this.gmap3( "get" ).panTo( marker.position );					
							$this.gmap3(
							  {clear: "overlay" },
								 {
									overlay:{  /* Show the overlay with the location name at the marker location */
										latLng: marker.position,
										options:{
										content: markerContent,
										  offset: {
											x:11,
											y:-15
										  }
										}
									}
								});
						}
					}
				}
			},
			events:{
			  mouseover: function( marker ) {
				var markerContent;
				
				/* Check if there is an active slider or not, if so we move the slider to the correct location */
				if ( !$( "#nwm-map-" + mapId + "" ).parent( ".nwm-wrap" ).hasClass( "nwm-no-slider" ) ) {  
					$sliderTarget.find( "li" ).removeClass(); 
					$sliderTarget.find( "li" ).eq(i).addClass( "nwm-active-destination" );
					$sliderTarget.find( ".nwm-active-destination img" ).attr( "src", placeholder );
	
					if ( !$sliderTarget.find( ".nwm-active-destination span.nwm-thumb" ).length ) {
						imageLoader( $sliderTarget );
					}
										
					markerContent = "<div class='marker-style marker-" + i + "'>" + destination.data.location + "</div>";
				} else {
					markerData = getMarkerData( destination, nwmSettings, 'tooltip' );
					markerContent = '<div class="marker-style marker-' + i + '"><div class="nwm-marker-wrap">' + markerData.thumb + '<div class="marker-txt"><h2>' + markerData.title + '</h2><p>' + markerData.date + markerData.readMore + '</p></div></div></div>';
				}
								
				$(this).gmap3(
				  {clear:"overlay"},
					{
					  overlay:{
						latLng: marker.getPosition(),
						options:{
						  content: markerContent,
						  offset: {
							x:11,
							y:-15
						  }
						}
					  }				  
				});
			  }
			}
		}
	});	
}

/**
 * Create the data that is either shown inside the tooltip or slider
 * 
 * @since 1.0
 * @param {object} destination Holds the location data
 * @param {object} nwmSettings Holds the map settings
 * @param {string} contentType Is either set to 'tooltip' or 'slider'
 * @return {object} markerData
 */
function getMarkerData( destination, nwmSettings, contentType ) {
	var markerData = {},
		circleClass = checkCircleClass( nwmSettings.thumbCircles ), 
		thumbHtml = "", 
		titleHtml = "",
		spanDate = "", 
		readMoreLabel = "",
		readMoreHtml = "";
				
	if ( destination.data.arrival ) {
		spanDate = "<span>" + destination.data.arrival + " - " + destination.data.departure + "</span>";
	} else {
		spanDate = "<span>" + destination.data.date + "</span>";
	}
	
	/* Check which thumb format to use, and whether we should show the placeholder. */
	if ( contentType == 'tooltip' ) {
		if ( destination.data.thumb != null ) {		
			thumbHtml = '<img class="nwm-thumb nwm-marker-img ' + circleClass + '" src="' + destination.data.thumb + '" width="64" height="64" />';
		} else {
			thumbHtml = '';	
		}
	} else {
		if ( destination.data.thumb != null ) {
			thumbHtml = '<img class="nwm-thumb ' + circleClass + '" data-src="' + destination.data.thumb + '" src="' + placeholder + '" width="64" height="64" />';
		} else {
			thumbHtml = '<div><span class="nwm-thumb ' + circleClass + '" /></span></div>';
		}
	}
	
	/* Create the correct header based on the available data */
	titleHtml = checkHeaderFormat( destination.data.url, destination.data.title, destination.data.location );
	
	/* Check if we should show the read more link */
	if ( ( destination.data.url.length > 0 ) && ( nwmSettings.readMore == 1 ) ) {
		if ( typeof( nwmSettings.readMoreLabel ) !== "undefined" ) {
			readMoreLabel = nwmSettings.readMoreLabel;
		} else {
			readMoreLabel = "Read more";
		}
		
		readMoreHtml = "<a class='nwm-read-more' href='" + destination.data.url + "'>" + readMoreLabel + "</a>";
	}
	
	/* Check if we should show the location name under the header */
	if ( ( destination.data.url.length > 0 ) && ( nwmSettings.locationHeader == 1 ) ) {
		titleHtml = titleHtml + "<span>" + destination.data.location + "</span>";
	}	
	
	markerData = {
		circleClass: circleClass,
		thumb: thumbHtml,
		date: spanDate,
		readMore: readMoreHtml,
		title: titleHtml
	};
	
	return markerData;
}

/**
 * Load the required image for the route location
 * 
 * @since 1.0
 * @param {object} $map A reference to the ul in the current map
 * @return void
 */
function imageLoader( $map ) {
	var $li		  = $map.find( ".nwm-active-destination" ),
		img		  = new Image(),
		imgTarget = $li.find( ".nwm-thumb" ),
		imgSrc	  = $li.find( ".nwm-thumb" ).data( "src" ),
		id	      = $li.data( "id" ),
		preloader = '<img class="nwm-preloader" id="nwm-preload-img-' + id + '" src="' + nwmSettings.path + 'admin/img/ajax-loader.gif" />';
	
	/* 
	Check if we have loaded the thumbnail before, 
	if not then we try todo so and show a preloader. 
	Otherwise we just change the src attr.
	*/
	if ( $.inArray( id, loadedImageList ) === -1 ) {
		$li.append( preloader );

		img.onload = function(){
			$( "#nwm-preload-img-" + id ).remove();
			imgTarget.attr( "src", this.src );
			loadedImageList.push( id );
		};
		img.src = imgSrc;
	} else {
		imgTarget.attr( "src", imgSrc );
	}
}

/**
 * Create the correct header format.
 * 
 * Either show a link with the title in it, just show the title or only show the destination.
 * 
 * @since 1.0
 * @param {string} markerUrl The url that can be used in the marker
 * @param {string} markerTitle The post title
 * @param {string] destination The destination name
 * @return {string} title The correct header format
 */
function checkHeaderFormat( markerUrl, markerTitle, destination ) {
	var title;

	if ( markerUrl ) {
		title = "<a href='" + markerUrl + "'>" + markerTitle + "</a>";
	} else {
		if ( markerTitle ) {
			title = markerTitle;
		} else {
			title = destination; 
		}		
	}	
	
	return title;
}

/**
 * Check which class we need to use on the thumbnails
 * 
 * @since 1.0
 * @param {number} thumbCircles Either 1 or 0 to enable/disable the circles on the thumbs
 * @return {string} circleClass The circle class
 */
function checkCircleClass( thumbCircles ) {
	var circleClass;
	
	if ( thumbCircles == 1 ) {
		circleClass = "nwm-circle";	
	} else {
		circleClass = "";	
	}		

	return circleClass;
}

/**
 * Bind the back and forward buttons
 * 
 * @since 1.0
 * @param {string} mapId The ID of the map
 * @return void
 */
function enableControls( mapId ) {
	var $map = $( "#nwm-map-" + mapId + "" ).next();
	
	$map.find( ".nwm-forward" ).on( "click", function () {
		var currentDestination = $map.find( ".nwm-active-destination" );	

		if ( currentDestination.next().length ){
			currentDestination.removeClass()
							  .next()
							  .addClass( "nwm-active-destination" )
							  .mouseover();
		} else {
			currentDestination.removeClass( "nwm-active-destination" );
			$map.find( "li:first-child" ).addClass( "nwm-active-destination" )
									     .mouseover();									 
		}
		
		if ( !$map.find( ".nwm-active-destination span.nwm-thumb" ).length ) {
			imageLoader( $map );
		}		
	});

	$map.find( ".nwm-back" ).on( "click", function() {
		var currentDestination = $map.find( ".nwm-active-destination" );

		if ( currentDestination.prev().length ){
			currentDestination.removeClass()
							  .prev()
							  .addClass( "nwm-active-destination" )
							  .mouseover();
		} else {
			currentDestination.removeClass( "nwm-active-destination" );
			$map.find( "li:last-child" ).addClass( "nwm-active-destination" )
										.mouseover();
		}

		if ( !$map.find( ".nwm-active-destination span.nwm-thumb" ).length ) {
			imageLoader( $map );
		}	
	});	
}

/**
 * Enable keyboard navigation for the slider.
 * 
 * Only enable this if we are dealing with a single map.
 * 
 * @since 1.0
 * @param string mapId The ID of the map
 * @return void
 */
if ( !$( "#nwm-map-1" ).length ) {
	$(document).keydown( function( eventObject ) {
		 if ( eventObject.which == 37 ) {
			$( ".nwm-back" ).trigger( "click" );
		 } else if ( eventObject.which == 39 ) {
			$( ".nwm-forward" ).trigger( "click" ); 
		 }
	});
}

/* Once the 'window' has finished loading initialize the map */
google.maps.event.addDomListener( window, "load", initializeGmap );
		 
});