<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'geobloqueo_logs';

// Get statistics
$total_attempts = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
$allowed_attempts = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE result = 'allowed'");
$denied_attempts = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE result = 'denied'");
$unique_devices = $wpdb->get_var("SELECT COUNT(DISTINCT device_info) FROM $table_name");

// Get the latest logs
$logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY access_time DESC LIMIT 100");
?>

<div class="wrap">
    <h1>GeoBloqueo Access Logs</h1>
    
    <div class="statistics-card">
        <div class="stat-box">
            <h3>Total Attempts</h3>
            <div class="number"><?php echo esc_html($total_attempts); ?></div>
        </div>
        
        <div class="stat-box">
            <h3>Allowed Access</h3>
            <div class="number"><?php echo esc_html($allowed_attempts); ?></div>
        </div>
        
        <div class="stat-box">
            <h3>Denied Access</h3>
            <div class="number"><?php echo esc_html($denied_attempts); ?></div>
        </div>
        
        <div class="stat-box">
            <h3>Unique Devices</h3>
            <div class="number"><?php echo esc_html($unique_devices); ?></div>
        </div>
    </div>
    
    <div class="export-button">
        <form method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
            <input type="hidden" name="action" value="download_logs" />
            <?php wp_nonce_field('download_logs', 'nonce'); ?>
            <button type="submit" class="button button-primary">Export to CSV</button>
        </form>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Time</th>
                <th>Device</th>
                <th>Browser</th>
                <th>OS</th>
                <th>Location</th>
                <th>Distance</th>
                <th>Result</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo esc_html($log->access_time); ?></td>
                    <td><?php echo esc_html($log->device_name); ?></td>
                    <td><?php echo esc_html($log->browser); ?></td>
                    <td><?php echo esc_html($log->os); ?></td>
                    <td><?php echo esc_html($log->city . ', ' . $log->country); ?></td>
                    <td><?php echo esc_html(number_format($log->distance, 2) . ' m'); ?></td>
                    <td>
                        <span class="status-<?php echo $log->result === 'allowed' ? 'success' : 'error'; ?>">
                            <?php echo esc_html(ucfirst($log->result)); ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>