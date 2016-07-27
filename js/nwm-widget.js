/**
 * Check if we need to append the Google Maps library, or if it has already been loaded through another plugin 
 *
 * @since 1.2
 * @return void
 */
function appendBootstrap() {
	if ( typeof google === "object" && typeof google.maps === "object" ) {
		handleApiReady();
	} else {
		var content = document.body.textContent || document.body.innerText;
		var count = (content.match(/maps.google.com\/maps\/api\/js/g) || []).length;
		if(count == 1){
			var script = document.createElement( "script" );
			script.type = "text/javascript";
			script.src = nwm_google_src_url;
			document.body.appendChild(script);
		}
	}
}

/**
 * If the Google Maps library has finished loading initialize the map for the widget
 *
 * @since 1.2
 * @return void
 */
function handleApiReady() {
	var widgetLatLng = nwmWidget.latlng.split(','),
		currentLatlng = new google.maps.LatLng( widgetLatLng[0], widgetLatLng[1] );
	
	var widgetOptions = {
		scrollwheel: false,
		mapTypeControl: false,
		navigationControl: false,
		panControl: false,
		streetViewControl: false,
		zoom: parseInt( nwmWidget.zoom ),
		center: currentLatlng,
		mapTypeId: google.maps.MapTypeId.ROADMAP,
		zoomControlOptions: {
			style: google.maps.ZoomControlStyle.SMALL,
			position: google.maps.ControlPosition.RIGHT_TOP
		}
	};
	
	var map = new google.maps.Map( document.getElementById( "nwm-map-widget" ), widgetOptions );
	var marker = new google.maps.Marker({
		position: currentLatlng,
		map: map,
		title: nwmWidget.location
	  });
}

jQuery(document).ready(function() {
	appendBootstrap();
});
