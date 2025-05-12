<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$latitude = get_option('geobloqueo_latitude', -33.5209);
$longitude = get_option('geobloqueo_longitude', -70.5855);
$max_distance = get_option('geobloqueo_max_distance', 200);
$address = get_option('geobloqueo_address', 'Vicuña mackenna poniente 6482, la Florida, Santiago de chile');
$postal_code = get_option('geobloqueo_postal_code', '8260032');
$is_active = get_option('geobloqueo_is_active', true);
$google_maps_key = get_option('geobloqueo_google_maps_key', '');
$vpn_api_key = get_option('geobloqueo_vpn_api_key', '');
?>

<div class="wrap">
    <h1>GeoBloqueo Settings</h1>
    
    <form method="post" action="options.php">
        <?php settings_fields('geobloqueo_settings'); ?>
        <?php do_settings_sections('geobloqueo_settings'); ?>
        
        <div class="card">
            <h2>General Settings</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="geobloqueo_is_active">Enable Plugin</label>
                    </th>
                    <td>
                        <input type="checkbox" id="geobloqueo_is_active" name="geobloqueo_is_active" value="1" <?php checked(1, $is_active); ?> />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="geobloqueo_google_maps_key">Google Maps API Key</label>
                    </th>
                    <td>
                        <input type="text" id="geobloqueo_google_maps_key" name="geobloqueo_google_maps_key" value="<?php echo esc_attr($google_maps_key); ?>" class="regular-text" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="geobloqueo_vpn_api_key">VPN Detection API Key</label>
                    </th>
                    <td>
                        <input type="text" id="geobloqueo_vpn_api_key" name="geobloqueo_vpn_api_key" value="<?php echo esc_attr($vpn_api_key); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="card">
            <h2>Location Settings</h2>
            
            <div id="map"></div>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="geobloqueo_address">Address</label>
                    </th>
                    <td>
                        <input type="text" id="geobloqueo_address" name="geobloqueo_address" value="<?php echo esc_attr($address); ?>" class="regular-text" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="geobloqueo_postal_code">Postal Code</label>
                    </th>
                    <td>
                        <input type="text" id="geobloqueo_postal_code" name="geobloqueo_postal_code" value="<?php echo esc_attr($postal_code); ?>" class="regular-text" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="geobloqueo_latitude">Latitude</label>
                    </th>
                    <td>
                        <input type="text" id="geobloqueo_latitude" name="geobloqueo_latitude" value="<?php echo esc_attr($latitude); ?>" class="regular-text" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="geobloqueo_longitude">Longitude</label>
                    </th>
                    <td>
                        <input type="text" id="geobloqueo_longitude" name="geobloqueo_longitude" value="<?php echo esc_attr($longitude); ?>" class="regular-text" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="geobloqueo_max_distance">Maximum Distance (meters)</label>
                    </th>
                    <td>
                        <input type="number" id="geobloqueo_max_distance" name="geobloqueo_max_distance" value="<?php echo esc_attr($max_distance); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div>