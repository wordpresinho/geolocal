jQuery(document).ready(function($) {
    if (typeof google === 'undefined') {
        return;
    }
    
    // Initialize map
    const map = new google.maps.Map(document.getElementById('map'), {
        zoom: 15,
        center: {
            lat: parseFloat($('input[name="geobloqueo_latitude"]').val()),
            lng: parseFloat($('input[name="geobloqueo_longitude"]').val())
        }
    });
    
    // Add marker
    const marker = new google.maps.Marker({
        position: map.getCenter(),
        map: map,
        draggable: true
    });
    
    // Add circle for radius
    const circle = new google.maps.Circle({
        map: map,
        radius: parseFloat($('input[name="geobloqueo_max_distance"]').val()),
        fillColor: '#FF0000',
        fillOpacity: 0.2,
        strokeColor: '#FF0000',
        strokeOpacity: 0.8,
        strokeWeight: 2
    });
    
    // Bind circle to marker
    circle.bindTo('center', marker, 'position');
    
    // Update form fields when marker is dragged
    google.maps.event.addListener(marker, 'dragend', function() {
        const pos = marker.getPosition();
        $('input[name="geobloqueo_latitude"]').val(pos.lat());
        $('input[name="geobloqueo_longitude"]').val(pos.lng());
        
        // Update address using reverse geocoding
        const geocoder = new google.maps.Geocoder();
        geocoder.geocode({ location: pos }, function(results, status) {
            if (status === 'OK') {
                if (results[0]) {
                    $('input[name="geobloqueo_address"]').val(results[0].formatted_address);
                    
                    // Try to find postal code
                    for (const component of results[0].address_components) {
                        if (component.types.includes('postal_code')) {
                            $('input[name="geobloqueo_postal_code"]').val(component.long_name);
                            break;
                        }
                    }
                }
            }
        });
    });
    
    // Update circle radius when max distance changes
    $('input[name="geobloqueo_max_distance"]').on('change', function() {
        circle.setRadius(parseFloat($(this).val()));
    });
    
    // Update marker position when coordinates change
    $('input[name="geobloqueo_latitude"], input[name="geobloqueo_longitude"]').on('change', function() {
        const lat = parseFloat($('input[name="geobloqueo_latitude"]').val());
        const lng = parseFloat($('input[name="geobloqueo_longitude"]').val());
        const pos = new google.maps.LatLng(lat, lng);
        marker.setPosition(pos);
        map.setCenter(pos);
    });
});