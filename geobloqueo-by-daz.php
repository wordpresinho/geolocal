<?php
/**
 * Plugin Name: GeoBloqueo By Daz
 * Plugin URI: https://daz.cl
 * Description: Bloquea contenido dependiendo de la distancia.
 * Version: 1.0
 * Author: DazTheLine
 * Author URI: mailto:Hola@Daz.cl
 * Text Domain: geobloqueo-by-daz
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin class
class GeoBloqueo_By_Daz {
    
    private $target_lat;
    private $target_lng;
    private $max_distance;
    private $is_active;
    private $address;
    private $postal_code;
    private $custom_messages;
    private $email_notifications;
    private $rate_limit;
    private $access_hours;
    private $debug_mode;
    
    public function __construct() {
        // Get settings from options
        $this->load_settings();
        
        // Initialize the plugin
        add_action('init', array($this, 'init'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Register scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'register_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        
        // Add AJAX handlers
        add_action('wp_ajax_check_location', array($this, 'check_location'));
        add_action('wp_ajax_nopriv_check_location', array($this, 'check_location'));
        add_action('wp_ajax_download_logs', array($this, 'download_logs'));
        
        // Add content filter
        add_filter('the_content', array($this, 'filter_content'));
        
        // Create logs table on activation
        register_activation_hook(__FILE__, array($this, 'create_logs_table'));
        
        // Add settings link on plugin page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        
        // Schedule cleanup task
        if (!wp_next_scheduled('geobloqueo_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'geobloqueo_cleanup_logs');
        }
        add_action('geobloqueo_cleanup_logs', array($this, 'cleanup_old_logs'));
        
        // Add debug mode handler
        add_action('template_redirect', array($this, 'handle_debug_mode'));
    }
    
    private function load_settings() {
        $this->is_active = get_option('geobloqueo_is_active', true);
        $this->target_lat = get_option('geobloqueo_latitude', -33.5209);
        $this->target_lng = get_option('geobloqueo_longitude', -70.5855);
        $this->max_distance = get_option('geobloqueo_max_distance', 200);
        $this->address = get_option('geobloqueo_address', 'Vicuña mackenna poniente 6482, la Florida, Santiago de chile');
        $this->postal_code = get_option('geobloqueo_postal_code', '8260032');
        $this->custom_messages = get_option('geobloqueo_messages', array(
            'title' => 'Sistema de Geolocalización<br>por Daz The Line',
            'description' => 'Para acceder a este contenido, debe estar físicamente presente en el estudio.',
            'button' => 'Activar Geolocalización',
            'retry_button' => 'Reintentar Geolocalización',
            'error_vpn' => 'Se ha detectado el uso de una VPN. Por favor, desactive la VPN para acceder al contenido.',
            'error_distance' => 'Estás muy lejos del local, exactamente a {distance} de Nuestro Estudio.'
        ));
        $this->email_notifications = get_option('geobloqueo_notifications', array(
            'enabled' => true,
            'email' => get_option('admin_email'),
            'notify_vpn' => true,
            'notify_suspicious' => true
        ));
        $this->rate_limit = get_option('geobloqueo_rate_limit', array(
            'enabled' => true,
            'max_attempts' => 5,
            'time_window' => 300 // 5 minutes
        ));
        $this->access_hours = get_option('geobloqueo_access_hours', array(
            'enabled' => false,
            'start' => '09:00',
            'end' => '18:00',
            'days' => array(1,2,3,4,5) // Monday to Friday
        ));
        $this->debug_mode = isset($_GET['RunDebug']);
    }

    public function handle_debug_mode() {
        if ($this->debug_mode && current_user_can('manage_options')) {
            add_action('wp_footer', array($this, 'display_debug_info'));
        }
    }

    public function display_debug_info() {
        if (!$this->debug_mode) return;
        
        echo '<div id="geobloqueo-debug" style="position:fixed;bottom:0;right:0;background:rgba(0,0,0,0.8);color:white;padding:20px;max-width:400px;max-height:400px;overflow:auto;z-index:999999;">';
        echo '<h3>GeoBloqueo Debug Info</h3>';
        echo '<pre>';
        echo "Plugin Active: " . ($this->is_active ? 'Yes' : 'No') . "\n";
        echo "Target Location: " . $this->target_lat . ", " . $this->target_lng . "\n";
        echo "Max Distance: " . $this->max_distance . "m\n";
        echo "Rate Limiting: " . ($this->rate_limit['enabled'] ? 'Enabled' : 'Disabled') . "\n";
        echo "Access Hours: " . ($this->access_hours['enabled'] ? $this->access_hours['start'] . " - " . $this->access_hours['end'] : 'Disabled') . "\n";
        echo '</pre>';
        echo '<div id="geobloqueo-debug-log"></div>';
        echo '</div>';
        
        // Add JavaScript to update debug info
        ?>
        <script>
        window.geobloqueoDebug = function(message) {
            const debugLog = document.getElementById('geobloqueo-debug-log');
            const time = new Date().toLocaleTimeString();
            debugLog.innerHTML = `<div>[${time}] ${message}</div>` + debugLog.innerHTML;
        };
        </script>
        <?php
    }

    private function format_distance($meters) {
        if ($meters >= 1000) {
            return number_format($meters / 1000, 2) . ' kilómetros';
        }
        return number_format($meters) . ' metros';
    }
    
    public function download_logs() {
        if (!current_user_can('manage_options')) {
            wp_die('Acceso denegado');
        }
        
        check_admin_referer('download_logs', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'geobloqueo_logs';
        
        $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY access_time DESC", ARRAY_A);
        
        if (!$logs) {
            wp_die('No hay registros para exportar');
        }
        
        $filename = 'geobloqueo-logs-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fputs($output, "\xEF\xBB\xBF");
        
        // Headers
        fputcsv($output, array(
            'ID',
            'IP (Encriptada)',
            'Latitud',
            'Longitud',
            'Distancia',
            'Dispositivo',
            'Navegador',
            'Sistema Operativo',
            'País',
            'Ciudad',
            'Resultado',
            'Fecha'
        ));
        
        // Data
        foreach ($logs as $row) {
            $ip = $this->decrypt_ip($row['ip']);
            fputcsv($output, array(
                $row['id'],
                $ip,
                $row['latitude'],
                $row['longitude'],
                $row['distance'],
                $row['device_info'],
                $row['browser'],
                $row['os'],
                $row['country'],
                $row['city'],
                $row['result'],
                $row['access_time']
            ));
        }
        
        fclose($output);
        exit;
    }
    
    private function encrypt_ip($ip) {
        $key = wp_salt('auth');
        $encrypted = openssl_encrypt($ip, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
        return base64_encode($encrypted);
    }
    
    private function decrypt_ip($encrypted_ip) {
        $key = wp_salt('auth');
        $encrypted = base64_decode($encrypted_ip);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
    }
    
    private function get_location_from_ip($ip) {
        $response = wp_remote_get("http://ip-api.com/json/{$ip}");
        
        if (is_wp_error($response)) {
            return array(
                'country' => 'Unknown',
                'city' => 'Unknown'
            );
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        return array(
            'country' => isset($data['country']) ? $data['country'] : 'Unknown',
            'city' => isset($data['city']) ? $data['city'] : 'Unknown'
        );
    }
    
    public function check_location() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'geobloqueo-nonce')) {
            wp_send_json_error(array('message' => 'Error de seguridad'));
        }
        
        if (!$this->check_rate_limit()) {
            wp_send_json_error(array('message' => 'Demasiados intentos. Por favor, espere unos minutos.'));
        }
        
        $lat = isset($_POST['lat']) ? floatval($_POST['lat']) : 0;
        $lng = isset($_POST['lng']) ? floatval($_POST['lng']) : 0;
        $device_info = isset($_POST['device_info']) ? sanitize_text_field($_POST['device_info']) : '';
        $device_name = isset($_POST['device_name']) ? sanitize_text_field($_POST['device_name']) : '';
        $accuracy = isset($_POST['accuracy']) ? floatval($_POST['accuracy']) : 0;
        
        if ($this->debug_mode && current_user_can('manage_options')) {
            error_log("GeoBloqueo Debug: Checking location - Lat: $lat, Lng: $lng");
        }
        
        if ($accuracy > 100) {
            wp_send_json_error(array(
                'message' => 'La precisión de la ubicación es muy baja. Por favor, asegúrese de tener el GPS activado y estar en un área con buena señal.',
                'accuracy' => $accuracy
            ));
        }
        
        $distance = $this->calculate_distance($lat, $lng, $this->target_lat, $this->target_lng);
        $formatted_distance = $this->format_distance($distance);
        
        // Get browser and OS info
        $browser = $this->get_browser_info();
        $os = $this->get_os_info();
        
        // Get location from IP
        $ip = $_SERVER['REMOTE_ADDR'];
        $location = $this->get_location_from_ip($ip);
        
        $this->log_access_attempt($lat, $lng, $distance, $device_info, $device_name, $browser, $os, $location);
        
        $using_vpn = $this->detect_vpn();
        
        if ($using_vpn) {
            if ($this->email_notifications['notify_vpn']) {
                $this->send_notification_email('vpn_detected', array(
                    'ip' => $ip,
                    'device_info' => $device_info,
                    'device_name' => $device_name,
                    'browser' => $browser,
                    'os' => $os,
                    'location' => $location
                ));
            }
            
            wp_send_json_error(array(
                'message' => $this->custom_messages['error_vpn'],
                'distance' => $distance,
                'using_vpn' => true
            ));
        } elseif ($distance <= $this->max_distance) {
            wp_send_json_success(array(
                'message' => 'Acceso permitido',
                'distance' => $distance
            ));
        } else {
            $error_message = str_replace('{distance}', $formatted_distance, $this->custom_messages['error_distance']);
            wp_send_json_error(array(
                'message' => $error_message,
                'distance' => $distance
            ));
        }
    }
    
    private function get_browser_info() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $browser = "Unknown";
        
        if (preg_match('/MSIE/i', $user_agent)) $browser = "Internet Explorer";
        elseif (preg_match('/Firefox/i', $user_agent)) $browser = "Firefox";
        elseif (preg_match('/Chrome/i', $user_agent)) $browser = "Chrome";
        elseif (preg_match('/Safari/i', $user_agent)) $browser = "Safari";
        elseif (preg_match('/Opera/i', $user_agent)) $browser = "Opera";
        
        return $browser;
    }
    
    private function get_os_info() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $os = "Unknown";
        
        if (preg_match('/windows/i', $user_agent)) $os = "Windows";
        elseif (preg_match('/mac/i', $user_agent)) $os = "MacOS";
        elseif (preg_match('/linux/i', $user_agent)) $os = "Linux";
        elseif (preg_match('/android/i', $user_agent)) $os = "Android";
        elseif (preg_match('/iphone/i', $user_agent)) $os = "iOS";
        
        return $os;
    }

    private function log_access_attempt($lat, $lng, $distance, $device_info, $device_name, $browser, $os, $location) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'geobloqueo_logs';
        
        $ip = $this->encrypt_ip($_SERVER['REMOTE_ADDR']);
        
        $wpdb->insert(
            $table_name,
            array(
                'ip' => $ip,
                'latitude' => $lat,
                'longitude' => $lng,
                'distance' => $distance,
                'device_info' => $device_info,
                'device_name' => $device_name,
                'browser' => $browser,
                'os' => $os,
                'country' => $location['country'],
                'city' => $location['city'],
                'result' => $distance <= $this->max_distance ? 'allowed' : 'denied',
                'access_time' => current_time('mysql')
            ),
            array('%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($this->debug_mode && current_user_can('manage_options')) {
            error_log("GeoBloqueo Debug: Access attempt logged - Distance: $distance, Result: " . ($distance <= $this->max_distance ? 'allowed' : 'denied'));
        }
    }

    public function create_logs_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'geobloqueo_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ip varchar(255) NOT NULL,
            latitude decimal(10,8) NOT NULL,
            longitude decimal(11,8) NOT NULL,
            distance decimal(10,2) NOT NULL,
            device_info text NOT NULL,
            device_name varchar(255) NOT NULL,
            browser varchar(50) NOT NULL,
            os varchar(50) NOT NULL,
            country varchar(100) NOT NULL,
            city varchar(100) NOT NULL,
            result varchar(20) NOT NULL,
            access_time datetime NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function filter_content($content) {
        if (!$this->is_active || !$this->check_access_hours()) {
            return $content;
        }
        
        global $post;
        
        $is_consent_page = false;
        if ($post && $post->post_name === 'consentimiento-tatuaje') {
            $is_consent_page = true;
        }
        
        $has_gravity_form = (has_shortcode($content, 'gravityform') || has_shortcode($content, 'gravityforms'));
        
        if ($is_consent_page || $has_gravity_form) {
            $overlay = sprintf(
                '<div id="geobloqueo-overlay">
                    <div class="geobloqueo-container">
                        <h2>%s</h2>
                        <p>%s</p>
                        <button id="geobloqueo-activate">%s</button>
                        <button id="geobloqueo-retry" style="display:none;">%s</button>
                        <div id="geobloqueo-error" style="display: none;"></div>
                    </div>
                </div>',
                wp_kses_post($this->custom_messages['title']),
                esc_html($this->custom_messages['description']),
                esc_html($this->custom_messages['button']),
                esc_html($this->custom_messages['retry_button'])
            );
            
            return $overlay . $content;
        }
        
        return $content;
    }

    public function register_scripts() {
        wp_enqueue_style(
            'geobloqueo-style',
            plugins_url('assets/css/style.css', __FILE__),
            array(),
            '1.0'
        );
        
        wp_enqueue_script(
            'geobloqueo-script',
            plugins_url('assets/js/geobloqueo.js', __FILE__),
            array('jquery'),
            '1.0',
            true
        );
        
        wp_localize_script('geobloqueo-script', 'geobloqueo_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'home_url' => home_url(),
            'nonce' => wp_create_nonce('geobloqueo-nonce'),
            'messages' => $this->custom_messages,
            'debug_mode' => $this->debug_mode && current_user_can('manage_options')
        ));
    }

    public function admin_scripts($hook) {
        if ('toplevel_page_geobloqueo-settings' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'geobloqueo-admin-style',
            plugins_url('assets/css/admin.css', __FILE__),
            array(),
            '1.0'
        );
        
        wp_enqueue_script(
            'geobloqueo-admin-script',
            plugins_url('assets/js/admin.js', __FILE__),
            array('jquery'),
            '1.0',
            true
        );
        
        wp_enqueue_script(
            'google-maps',
            'https://maps.googleapis.com/maps/api/js?key=' . get_option('geobloqueo_google_maps_key'),
            array(),
            null,
            true
        );
    }

    public function add_admin_menu() {
        add_menu_page(
            'GeoBloqueo Settings',
            'GeoBloqueo',
            'manage_options',
            'geobloqueo-settings',
            array($this, 'render_settings_page'),
            'dashicons-location',
            30
        );
        
        add_submenu_page(
            'geobloqueo-settings',
            'Access Logs',
            'Access Logs',
            'manage_options',
            'geobloqueo-logs',
            array($this, 'render_logs_page')
        );
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        include(plugin_dir_path(__FILE__) . 'admin/settings-page.php');
    }

    public function render_logs_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        include(plugin_dir_path(__FILE__) . 'admin/logs-page.php');
    }

    private function calculate_distance($lat1, $lon1, $lat2, $lon2) {
        $r = 6371000; // Earth's radius in meters
        
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);
        
        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;
        
        $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $r * $c;
    }

    private function detect_vpn() {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Check against known VPN providers
        $response = wp_remote_get("https://vpnapi.io/api/{$ip}?key=" . get_option('geobloqueo_vpn_api_key'));
        
        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['security']['vpn']) && $data['security']['vpn']) {
                return true;
            }
        }
        
        return false;
    }

    private function check_rate_limit() {
        if (!$this->rate_limit['enabled']) {
            return true;
        }
        
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = 'geobloqueo_rate_limit_' . md5($ip);
        
        $attempts = get_transient($key);
        
        if ($attempts === false) {
            set_transient($key, 1, $this->rate_limit['time_window']);
            return true;
        }
        
        if ($attempts >= $this->rate_limit['max_attempts']) {
            return false;
        }
        
        set_transient($key, $attempts + 1, $this->rate_limit['time_window']);
        return true;
    }

    private function check_access_hours() {
        if (!$this->access_hours['enabled']) {
            return true;
        }
        
        $current_time = current_time('H:i');
        $current_day = current_time('N'); // 1 (Monday) to 7 (Sunday)
        
        if (!in_array($current_day, $this->access_hours['days'])) {
            return false;
        }
        
        return $current_time >= $this->access_hours['start'] && $current_time <= $this->access_hours['end'];
    }

    public function cleanup_old_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'geobloqueo_logs';
        
        // Delete logs older than 30 days
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE access_time < %s",
                date('Y-m-d H:i:s', strtotime('-30 days'))
            )
        );
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=geobloqueo-settings">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Initialize the plugin
new GeoBloqueo_By_Daz();