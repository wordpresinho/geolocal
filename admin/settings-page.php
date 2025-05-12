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

// Get additional settings
$rate_limit = get_option('geobloqueo_rate_limit', array(
    'enabled' => true,
    'max_attempts' => 5,
    'time_window' => 300
));

$access_hours = get_option('geobloqueo_access_hours', array(
    'enabled' => false,
    'start' => '09:00',
    'end' => '18:00',
    'days' => array(1,2,3,4,5)
));

$notifications = get_option('geobloqueo_notifications', array(
    'enabled' => true,
    'email' => get_option('admin_email'),
    'notify_vpn' => true,
    'notify_suspicious' => true
));
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

        <div class="card">
            <h2>Rate Limiting</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="geobloqueo_rate_limit_enabled">Enable Rate Limiting</label>
                    </th>
                    <td>
                        <input type="checkbox" id="geobloqueo_rate_limit_enabled" name="geobloqueo_rate_limit[enabled]" value="1" <?php checked(1, $rate_limit['enabled']); ?> />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="geobloqueo_rate_limit_max">Maximum Attempts</label>
                    </th>
                    <td>
                        <input type="number" id="geobloqueo_rate_limit_max" name="geobloqueo_rate_limit[max_attempts]" value="<?php echo esc_attr($rate_limit['max_attempts']); ?>" min="1" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="geobloqueo_rate_limit_window">Time Window (seconds)</label>
                    </th>
                    <td>
                        <input type="number" id="geobloqueo_rate_limit_window" name="geobloqueo_rate_limit[time_window]" value="<?php echo esc_attr($rate_limit['time_window']); ?>" min="60" />
                    </td>
                </tr>
            </table>
        </div>

        <div class="card">
            <h2>Access Hours</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="geobloqueo_access_hours_enabled">Enable Access Hours</label>
                    </th>
                    <td>
                        <input type="checkbox" id="geobloqueo_access_hours_enabled" name="geobloqueo_access_hours[enabled]" value="1" <?php checked(1, $access_hours['enabled']); ?> />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="geobloqueo_access_hours_start">Start Time</label>
                    </th>
                    <td>
                        <input type="time" id="geobloqueo_access_hours_start" name="geobloqueo_access_hours[start]" value="<?php echo esc_attr($access_hours['start']); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="geobloqueo_access_hours_end">End Time</label>
                    </th>
                    <td>
                        <input type="time" id="geobloqueo_access_hours_end" name="geobloqueo_access_hours[end]" value="<?php echo esc_attr($access_hours['end']); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Working Days</th>
                    <td>
                        <?php
                        $days = array(
                            1 => 'Monday',
                            2 => 'Tuesday',
                            3 => 'Wednesday',
                            4 => 'Thursday',
                            5 => 'Friday',
                            6 => 'Saturday',
                            7 => 'Sunday'
                        );
                        foreach ($days as $value => $label): ?>
                            <label style="margin-right: 15px;">
                                <input type="checkbox" name="geobloqueo_access_hours[days][]" value="<?php echo $value; ?>"
                                    <?php checked(in_array($value, $access_hours['days'])); ?> />
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="card">
            <h2>Email Notifications</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="geobloqueo_notifications_enabled">Enable Notifications</label>
                    </th>
                    <td>
                        <input type="checkbox" id="geobloqueo_notifications_enabled" name="geobloqueo_notifications[enabled]" value="1" <?php checked(1, $notifications['enabled']); ?> />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="geobloqueo_notifications_email">Notification Email</label>
                    </th>
                    <td>
                        <input type="email" id="geobloqueo_notifications_email" name="geobloqueo_notifications[email]" value="<?php echo esc_attr($notifications['email']); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="geobloqueo_notifications_vpn">Notify on VPN Detection</label>
                    </th>
                    <td>
                        <input type="checkbox" id="geobloqueo_notifications_vpn" name="geobloqueo_notifications[notify_vpn]" value="1" <?php checked(1, $notifications['notify_vpn']); ?> />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="geobloqueo_notifications_suspicious">Notify on Suspicious Activity</label>
                    </th>
                    <td>
                        <input type="checkbox" id="geobloqueo_notifications_suspicious" name="geobloqueo_notifications[notify_suspicious]" value="1" <?php checked(1, $notifications['notify_suspicious']); ?> />
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div>