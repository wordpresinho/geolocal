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
            'error_vpn' => 'Se ha detectado el uso de una VPN. Por favor, desactive la VPN para acceder al contenido.',
            'error_distance' => 'Estás muy lejos del local, exactamente a %s de Nuestro Estudio.'
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
    }

    private function format_distance($meters) {
        if ($meters >= 1000) {
            return number_format($meters / 1000, 2) . ' kilómetros';
        }
        return number_format($meters) . ' metros';
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
        
        if ($accuracy > 100) {
            wp_send_json_error(array(
                'message' => 'La precisión de la ubicación es muy baja. Por favor, asegúrese de tener el GPS activado y estar en un área con buena señal.',
                'accuracy' => $accuracy
            ));
        }
        
        $distance = $this->calculate_distance($lat, $lng, $this->target_lat, $this->target_lng);
        $formatted_distance = $this->format_distance($distance);
        
        $this->log_access_attempt($lat, $lng, $distance, $device_info, $device_name);
        
        $using_vpn = $this->detect_vpn();
        
        if ($using_vpn) {
            if ($this->email_notifications['notify_vpn']) {
                $this->send_notification_email('vpn_detected', array(
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'device_info' => $device_info,
                    'device_name' => $device_name
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
            wp_send_json_error(array(
                'message' => sprintf($this->custom_messages['error_distance'], $formatted_distance),
                'distance' => $distance
            ));
        }
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
                        <div id="geobloqueo-error" style="display: none;"></div>
                    </div>
                </div>',
                wp_kses_post($this->custom_messages['title']),
                esc_html($this->custom_messages['description']),
                esc_html($this->custom_messages['button'])
            );
            
            return $overlay . $content;
        }
        
        return $content;
    }

    // [El resto del código permanece igual...]
    
    // Incluir aquí el resto de las funciones de la clase que no han cambiado
    
}

// Initialize the plugin
new GeoBloqueo_By_Daz();