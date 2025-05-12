<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'geobloqueo_logs';

// Get filter values
$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
$result_filter = isset($_GET['result']) ? sanitize_text_field($_GET['result']) : '';
$device_filter = isset($_GET['device']) ? sanitize_text_field($_GET['device']) : '';

// Build query
$where_clauses = array();
$where_values = array();

if ($start_date) {
    $where_clauses[] = "access_time >= %s";
    $where_values[] = $start_date . ' 00:00:00';
}

if ($end_date) {
    $where_clauses[] = "access_time <= %s";
    $where_values[] = $end_date . ' 23:59:59';
}

if ($result_filter) {
    $where_clauses[] = "result = %s";
    $where_values[] = $result_filter;
}

if ($device_filter) {
    $where_clauses[] = "device_info LIKE %s";
    $where_values[] = '%' . $wpdb->esc_like($device_filter) . '%';
}

// Get statistics
$where_sql = $where_clauses ? "WHERE " . implode(" AND ", $where_clauses) : "";
$query = $wpdb->prepare(
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN result = 'allowed' THEN 1 ELSE 0 END) as allowed,
        SUM(CASE WHEN result = 'denied' THEN 1 ELSE 0 END) as denied,
        COUNT(DISTINCT device_info) as unique_devices
    FROM $table_name " . $where_sql,
    $where_values
);

$stats = $wpdb->get_row($query);

// Get the latest logs with pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

$logs_query = "SELECT * FROM $table_name " . $where_sql . " ORDER BY access_time DESC LIMIT %d OFFSET %d";
$logs = $wpdb->get_results($wpdb->prepare($logs_query, array_merge($where_values, array($per_page, $offset))));

// Get total pages for pagination
$total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name " . $where_sql, $where_values);
$total_pages = ceil($total_items / $per_page);
?>

<div class="wrap">
    <h1>GeoBloqueo Access Logs</h1>
    
    <div class="statistics-card">
        <div class="stat-box">
            <h3>Total Attempts</h3>
            <div class="number"><?php echo esc_html($stats->total); ?></div>
        </div>
        
        <div class="stat-box">
            <h3>Allowed Access</h3>
            <div class="number"><?php echo esc_html($stats->allowed); ?></div>
        </div>
        
        <div class="stat-box">
            <h3>Denied Access</h3>
            <div class="number"><?php echo esc_html($stats->denied); ?></div>
        </div>
        
        <div class="stat-box">
            <h3>Unique Devices</h3>
            <div class="number"><?php echo esc_html($stats->unique_devices); ?></div>
        </div>
    </div>
    
    <div class="filters">
        <form method="get">
            <input type="hidden" name="page" value="geobloqueo-logs" />
            
            <label for="start_date">Start Date:</label>
            <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>" />
            
            <label for="end_date">End Date:</label>
            <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>" />
            
            <label for="result">Result:</label>
            <select id="result" name="result">
                <option value="">All</option>
                <option value="allowed" <?php selected($result_filter, 'allowed'); ?>>Allowed</option>
                <option value="denied" <?php selected($result_filter, 'denied'); ?>>Denied</option>
            </select>
            
            <label for="device">Device:</label>
            <input type="text" id="device" name="device" value="<?php echo esc_attr($device_filter); ?>" placeholder="Filter by device..." />
            
            <button type="submit" class="button">Apply Filters</button>
            <a href="?page=geobloqueo-logs" class="button">Reset Filters</a>
        </form>
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
    
    <?php if ($total_pages > 1): ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $total_pages,
                    'current' => $current_page
                ));
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>