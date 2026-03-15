<?php
/**
 * Pulseem Logs View Template
 *
 * This file renders the logs viewer interface for the Pulseem plugin.
 * Displays log entries with filtering, pagination, export, bulk actions,
 * stats dashboard, and log settings configuration.
 *
 * @since      1.3.6
 * @version    1.4.0
 */

// Check if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

use pulseem\PulseemLogger;

// Handle actions
if (isset($_POST['action']) && isset($_POST['pulseem_logs_nonce'])) {
    if (wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pulseem_logs_nonce'])), 'pulseem_logs_action')) {
        $action = sanitize_text_field(wp_unslash($_POST['action']));

        if ($action === 'clear_all_logs' && current_user_can('manage_options')) {
            PulseemLogger::clear_all_logs();
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('All logs have been cleared.', 'pulseem') . '</p></div>';
        }

        if ($action === 'delete_old_logs' && current_user_can('manage_options')) {
            $days = isset($_POST['days']) ? absint(wp_unslash($_POST['days'])) : 30;
            $deleted = PulseemLogger::delete_old_logs($days);
            /* translators: %1$d: number of deleted entries, %2$d: number of days */
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('%1$d log entries older than %2$d days have been deleted.', 'pulseem'), intval($deleted), intval($days)) . '</p></div>';
        }

        if ($action === 'bulk_delete' && current_user_can('manage_options')) {
            $ids = isset($_POST['log_ids']) ? array_map('absint', array_map('sanitize_text_field', wp_unslash((array) $_POST['log_ids']))) : [];
            if (!empty($ids)) {
                $deleted = PulseemLogger::delete_logs_by_ids($ids);
                /* translators: %d: number of deleted entries */
                echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('%d log entries have been deleted.', 'pulseem'), intval($deleted)) . '</p></div>';
            }
        }
    }
}

// Get filter parameters
$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
$per_page = isset($_GET['per_page']) ? absint($_GET['per_page']) : 50;
if (!in_array($per_page, [25, 50, 100, 200])) {
    $per_page = 50;
}

// Sort parameters
$orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'timestamp';
$order = isset($_GET['order']) ? sanitize_text_field(wp_unslash($_GET['order'])) : 'DESC';

$filter_args = [
    'level' => isset($_GET['level']) ? sanitize_text_field(wp_unslash($_GET['level'])) : '',
    'context' => isset($_GET['context']) ? sanitize_text_field(wp_unslash($_GET['context'])) : '',
    'email' => isset($_GET['email']) ? sanitize_email(wp_unslash($_GET['email'])) : '',
    'search' => isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '',
    'date_from' => isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '',
    'date_to' => isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '',
    'request_id' => isset($_GET['request_id']) ? sanitize_text_field(wp_unslash($_GET['request_id'])) : '',
    'per_page' => $per_page,
    'page' => $current_page,
    'orderby' => $orderby,
    'order' => $order,
];

// Get logs and total count
$logs = PulseemLogger::get_logs($filter_args);
$total_logs = PulseemLogger::get_logs_count($filter_args);
$total_pages = ceil($total_logs / $per_page);

$levels = PulseemLogger::get_levels();
$contexts = PulseemLogger::get_contexts();

// Get stats
$stats = PulseemLogger::get_stats();

// Get current settings
$options = get_option('pulseem_settings', []);
$current_log_level = isset($options['log_level']) ? $options['log_level'] : 'debug';
$current_retention = isset($options['log_retention_days']) ? (int) $options['log_retention_days'] : 30;

// Helper for sort links
function pulseem_sort_link($column, $current_orderby, $current_order) {
    $new_order = ($current_orderby === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
    $allowed_keys = ['page', 'level', 'context', 'email', 'search', 'date_from', 'date_to', 'request_id', 'paged', 'per_page', 'order', 'orderby'];
    $params = [];
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading $_GET params to build sort/filter URLs for the logs table, no data processing.
    foreach ($allowed_keys as $key) {
        if (isset($_GET[$key])) {
            $params[$key] = sanitize_text_field(wp_unslash($_GET[$key]));
        }
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    $params['orderby'] = $column;
    $params['order'] = $new_order;
    return add_query_arg($params, admin_url('admin.php'));
}

function pulseem_sort_indicator($column, $current_orderby, $current_order) {
    if ($current_orderby !== $column) return '';
    return $current_order === 'ASC' ? ' <span class="pulseem-sort-arrow">&#9650;</span>' : ' <span class="pulseem-sort-arrow">&#9660;</span>';
}

// Build export URL base
$export_base_params = array_filter([
    'level' => $filter_args['level'],
    'context' => $filter_args['context'],
    'email' => $filter_args['email'],
    'search' => $filter_args['search'],
    'date_from' => $filter_args['date_from'],
    'date_to' => $filter_args['date_to'],
]);
$export_nonce = wp_create_nonce('pulseem_logs_nonce');

?>

<div class="wrap pulseemplugin-wrapper bg-gray-50 min-h-screen <?php echo esc_attr( is_rtl() ? 'pulseem-rtl' : '' ); ?>" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>"
     x-data="pulseemLogs()">
    <!-- Header Section -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                    <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'public/logo.webp'); ?>" alt="Pulseem Logo" class="w-8 h-8" />
                    <span><?php echo esc_html__('Pulseem Logs', 'pulseem'); ?></span>
                </h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=pulseem_settings')); ?>" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <?php esc_html_e('Back to Settings', 'pulseem'); ?>
                </a>
            </div>
        </div>
    </div>

    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Stats Dashboard -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white shadow-sm rounded-lg border p-4">
                <div class="text-sm font-medium text-gray-500"><?php esc_html_e('Total Logs', 'pulseem'); ?></div>
                <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo esc_html(number_format($stats['total'])); ?></div>
            </div>
            <div class="bg-white shadow-sm rounded-lg border p-4">
                <div class="text-sm font-medium text-gray-500"><?php esc_html_e('Errors (24h)', 'pulseem'); ?></div>
                <div class="text-2xl font-bold text-red-600 mt-1"><?php echo esc_html(number_format($stats['errors_24h'])); ?></div>
            </div>
            <div class="bg-white shadow-sm rounded-lg border p-4">
                <div class="text-sm font-medium text-gray-500"><?php esc_html_e('Warnings (24h)', 'pulseem'); ?></div>
                <div class="text-2xl font-bold text-yellow-600 mt-1"><?php echo esc_html(number_format($stats['warnings_24h'])); ?></div>
            </div>
            <div class="bg-white shadow-sm rounded-lg border p-4">
                <div class="text-sm font-medium text-gray-500"><?php esc_html_e('DB Table Size', 'pulseem'); ?></div>
                <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo esc_html(PulseemLogger::format_bytes($stats['table_size'])); ?></div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="bg-white shadow-sm rounded-lg border mb-6">
            <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                <h2 class="text-lg font-medium text-gray-900"><?php esc_html_e('Filters', 'pulseem'); ?></h2>
            </div>
            <div class="p-6">
                <form method="get" action="">
                    <input type="hidden" name="page" value="pulseem_logs" />
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Level Filter -->
                        <div>
                            <label for="level" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('Level', 'pulseem'); ?></label>
                            <select name="level" id="level" class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm">
                                <option value=""><?php esc_html_e('All Levels', 'pulseem'); ?></option>
                                <?php foreach ($levels as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($filter_args['level'], $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Context Filter -->
                        <div>
                            <label for="context" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('Context', 'pulseem'); ?></label>
                            <select name="context" id="context" class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm">
                                <option value=""><?php esc_html_e('All Contexts', 'pulseem'); ?></option>
                                <?php foreach ($contexts as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($filter_args['context'], $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Email Filter -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('Email', 'pulseem'); ?></label>
                            <input type="text" name="email" id="email" value="<?php echo esc_attr($filter_args['email']); ?>" placeholder="<?php esc_attr_e('Search by email...', 'pulseem'); ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm" />
                        </div>

                        <!-- Search Filter -->
                        <div>
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('Search', 'pulseem'); ?></label>
                            <input type="text" name="search" id="search" value="<?php echo esc_attr($filter_args['search']); ?>" placeholder="<?php esc_attr_e('Search in message...', 'pulseem'); ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm" />
                        </div>

                        <!-- Date From -->
                        <div>
                            <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('Date From', 'pulseem'); ?></label>
                            <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($filter_args['date_from']); ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm" />
                        </div>

                        <!-- Date To -->
                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('Date To', 'pulseem'); ?></label>
                            <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($filter_args['date_to']); ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm" />
                        </div>

                        <!-- Request ID Filter -->
                        <div>
                            <label for="request_id" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('Request ID', 'pulseem'); ?></label>
                            <input type="text" name="request_id" id="request_id" value="<?php echo esc_attr($filter_args['request_id']); ?>" placeholder="<?php esc_attr_e('Filter by request ID...', 'pulseem'); ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm" />
                        </div>

                        <!-- Filter Button -->
                        <div class="flex items-end gap-2">
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-pink-600 hover:bg-pink-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pink-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                                </svg>
                                <?php esc_html_e('Filter', 'pulseem'); ?>
                            </button>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=pulseem_logs')); ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pink-500">
                                <?php esc_html_e('Reset', 'pulseem'); ?>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Actions Card -->
        <div class="bg-white shadow-sm rounded-lg border mb-6">
            <div class="p-4 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="text-sm text-gray-600">
                        <?php
                    /* translators: %1$d: number of logs shown, %2$d: total number of logs */
                    printf(esc_html__('Showing %1$d of %2$d logs', 'pulseem'), intval(count($logs)), intval($total_logs));
                    ?>
                    </div>
                    <!-- Per-page selector -->
                    <form method="get" class="inline-flex items-center gap-1">
                        <input type="hidden" name="page" value="pulseem_logs" />
                        <?php foreach ($filter_args as $key => $val) : ?>
                            <?php if ($key !== 'per_page' && $key !== 'page' && !empty($val)) : ?>
                                <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($val); ?>" />
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <label for="per_page" class="text-sm text-gray-500"><?php esc_html_e('Per page:', 'pulseem'); ?></label>
                        <select name="per_page" id="per_page" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm">
                            <?php foreach ([25, 50, 100, 200] as $pp) : ?>
                                <option value="<?php echo esc_attr($pp); ?>" <?php selected($per_page, $pp); ?>><?php echo esc_html($pp); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <!-- Export buttons -->
                    <a href="<?php echo esc_url(add_query_arg(array_merge(['action' => 'pulseem_export_logs_csv', 'nonce' => $export_nonce], $export_base_params), admin_url('admin-ajax.php'))); ?>" class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <?php esc_html_e('Export CSV', 'pulseem'); ?>
                    </a>
                    <a href="<?php echo esc_url(add_query_arg(array_merge(['action' => 'pulseem_export_logs_json', 'nonce' => $export_nonce], $export_base_params), admin_url('admin-ajax.php'))); ?>" class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <?php esc_html_e('Export JSON', 'pulseem'); ?>
                    </a>

                    <form method="post" class="inline-flex items-center gap-2" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete old logs?', 'pulseem'); ?>');">
                        <?php wp_nonce_field('pulseem_logs_action', 'pulseem_logs_nonce'); ?>
                        <input type="hidden" name="action" value="delete_old_logs" />
                        <select name="days" class="rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm">
                            <option value="7"><?php esc_html_e('7 days', 'pulseem'); ?></option>
                            <option value="14"><?php esc_html_e('14 days', 'pulseem'); ?></option>
                            <option value="30" selected><?php esc_html_e('30 days', 'pulseem'); ?></option>
                            <option value="60"><?php esc_html_e('60 days', 'pulseem'); ?></option>
                            <option value="90"><?php esc_html_e('90 days', 'pulseem'); ?></option>
                        </select>
                        <button type="submit" class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            <?php esc_html_e('Delete Old Logs', 'pulseem'); ?>
                        </button>
                    </form>
                    <form method="post" class="inline" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to clear ALL logs? This action cannot be undone.', 'pulseem'); ?>');">
                        <?php wp_nonce_field('pulseem_logs_action', 'pulseem_logs_nonce'); ?>
                        <input type="hidden" name="action" value="clear_all_logs" />
                        <button type="submit" class="inline-flex items-center px-3 py-2 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            <?php esc_html_e('Clear All Logs', 'pulseem'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Log Settings Section (collapsible) -->
        <div class="bg-white shadow-sm rounded-lg border mb-6" x-data="{ open: false }">
            <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between text-left bg-gray-50 border-b border-gray-200 rounded-t-lg">
                <h2 class="text-lg font-medium text-gray-900"><?php esc_html_e('Log Settings', 'pulseem'); ?></h2>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" x-cloak class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label for="setting_log_level" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('Minimum Log Level', 'pulseem'); ?></label>
                        <select id="setting_log_level" class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm">
                            <option value="debug" <?php selected($current_log_level, 'debug'); ?>><?php esc_html_e('Debug', 'pulseem'); ?></option>
                            <option value="info" <?php selected($current_log_level, 'info'); ?>><?php esc_html_e('Info', 'pulseem'); ?></option>
                            <option value="warning" <?php selected($current_log_level, 'warning'); ?>><?php esc_html_e('Warning', 'pulseem'); ?></option>
                            <option value="error" <?php selected($current_log_level, 'error'); ?>><?php esc_html_e('Error', 'pulseem'); ?></option>
                        </select>
                    </div>
                    <div>
                        <label for="setting_retention" class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e('Retention Period', 'pulseem'); ?></label>
                        <select id="setting_retention" class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm">
                            <option value="7" <?php selected($current_retention, 7); ?>><?php esc_html_e('7 days', 'pulseem'); ?></option>
                            <option value="14" <?php selected($current_retention, 14); ?>><?php esc_html_e('14 days', 'pulseem'); ?></option>
                            <option value="30" <?php selected($current_retention, 30); ?>><?php esc_html_e('30 days', 'pulseem'); ?></option>
                            <option value="60" <?php selected($current_retention, 60); ?>><?php esc_html_e('60 days', 'pulseem'); ?></option>
                            <option value="90" <?php selected($current_retention, 90); ?>><?php esc_html_e('90 days', 'pulseem'); ?></option>
                            <option value="0" <?php selected($current_retention, 0); ?>><?php esc_html_e('Never', 'pulseem'); ?></option>
                        </select>
                    </div>
                    <div>
                        <button type="button" @click="saveLogSettings()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-pink-600 hover:bg-pink-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pink-500">
                            <?php esc_html_e('Save Settings', 'pulseem'); ?>
                        </button>
                        <span x-show="settingsSaved" x-transition class="text-sm text-green-600 ml-2"><?php esc_html_e('Saved!', 'pulseem'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Actions + Logs Table -->
        <form method="post" id="pulseem-logs-bulk-form">
            <?php wp_nonce_field('pulseem_logs_action', 'pulseem_logs_nonce'); ?>
            <input type="hidden" name="action" value="bulk_delete" />

            <!-- Bulk action bar -->
            <div class="bg-white shadow-sm rounded-t-lg border border-b-0 p-3 flex items-center gap-3" x-show="selectedIds.length > 0" x-cloak>
                <span class="text-sm text-gray-600" x-text="selectedIds.length + ' <?php echo esc_js(__('selected', 'pulseem')); ?>'"></span>
                <button type="submit" onclick="return confirm('<?php esc_attr_e('Delete selected logs?', 'pulseem'); ?>')" class="inline-flex items-center px-3 py-1.5 border border-red-300 text-xs font-medium rounded-md text-red-700 bg-white hover:bg-red-50">
                    <?php esc_html_e('Delete Selected', 'pulseem'); ?>
                </button>
            </div>

            <div class="bg-white shadow-sm rounded-lg border overflow-hidden" :class="{ 'rounded-t-none border-t-0': selectedIds.length > 0 }">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-3 py-3 text-left">
                                    <input type="checkbox" @change="toggleAll($event)" class="rounded border-gray-300 text-pink-600 focus:ring-pink-500" />
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <a href="<?php echo esc_url(pulseem_sort_link('timestamp', $orderby, $order)); ?>" class="pulseem-sort-link"><?php esc_html_e('Time', 'pulseem'); ?><?php echo wp_kses_post(pulseem_sort_indicator('timestamp', $orderby, $order)); ?></a>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <a href="<?php echo esc_url(pulseem_sort_link('level', $orderby, $order)); ?>" class="pulseem-sort-link"><?php esc_html_e('Level', 'pulseem'); ?><?php echo wp_kses_post(pulseem_sort_indicator('level', $orderby, $order)); ?></a>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <a href="<?php echo esc_url(pulseem_sort_link('context', $orderby, $order)); ?>" class="pulseem-sort-link"><?php esc_html_e('Context', 'pulseem'); ?><?php echo wp_kses_post(pulseem_sort_indicator('context', $orderby, $order)); ?></a>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php esc_html_e('Message', 'pulseem'); ?></th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <a href="<?php echo esc_url(pulseem_sort_link('email', $orderby, $order)); ?>" class="pulseem-sort-link"><?php esc_html_e('Email', 'pulseem'); ?><?php echo wp_kses_post(pulseem_sort_indicator('email', $orderby, $order)); ?></a>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php esc_html_e('Request ID', 'pulseem'); ?></th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?php esc_html_e('Details', 'pulseem'); ?></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($logs)) : ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <p class="mt-2 text-sm"><?php esc_html_e('No logs found.', 'pulseem'); ?></p>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($logs as $log) : ?>
                                    <?php
                                    $level_colors = [
                                        'debug' => 'bg-gray-100 text-gray-800',
                                        'info' => 'bg-blue-100 text-blue-800',
                                        'warning' => 'bg-yellow-100 text-yellow-800',
                                        'error' => 'bg-red-100 text-red-800',
                                    ];
                                    $level_color = isset($level_colors[$log->level]) ? $level_colors[$log->level] : $level_colors['info'];
                                    $context_label = isset($contexts[$log->context]) ? $contexts[$log->context] : $log->context;
                                    $request_id_val = isset($log->request_id) ? $log->request_id : '';
                                    $request_id_short = $request_id_val ? substr($request_id_val, 0, 8) : '-';

                                    // Prepare modal data
                                    $modal_data = [
                                        'id' => $log->id,
                                        'timestamp' => date_i18n('Y-m-d H:i:s', strtotime($log->timestamp)),
                                        'level' => ucfirst($log->level),
                                        'context' => $context_label,
                                        'message' => $log->message,
                                        'data' => $log->data ? json_decode($log->data, true) : null,
                                        'email' => $log->email ?: '-',
                                        'user_id' => $log->user_id ?: '-',
                                        'ip_address' => $log->ip_address ?: '-',
                                        'request_id' => $request_id_val ?: '-',
                                    ];
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-4">
                                            <input type="checkbox" name="log_ids[]" value="<?php echo esc_attr($log->id); ?>" @change="toggleId(<?php echo esc_attr($log->id); ?>, $event)" class="rounded border-gray-300 text-pink-600 focus:ring-pink-500" />
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($log->timestamp))); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo esc_attr($level_color); ?>">
                                                <?php echo esc_html(ucfirst($log->level)); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo esc_html($context_label); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900 max-w-md truncate" title="<?php echo esc_attr($log->message); ?>">
                                            <?php echo esc_html($log->message); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $log->email ? esc_html($log->email) : '-'; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php if ($request_id_val) : ?>
                                                <a href="<?php echo esc_url(add_query_arg(['page' => 'pulseem_logs', 'request_id' => $request_id_val], admin_url('admin.php'))); ?>" class="text-pink-600 hover:text-pink-800" title="<?php echo esc_attr($request_id_val); ?>">
                                                    <?php echo esc_html($request_id_short); ?>...
                                                </a>
                                            <?php else : ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <button type="button" @click="openModal(JSON.parse(decodeURIComponent('<?php echo esc_attr(rawurlencode(wp_json_encode($modal_data))); ?>')))" class="text-pink-600 hover:text-pink-800 text-xs">
                                                <?php esc_html_e('View', 'pulseem'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1) : ?>
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <?php if ($current_page > 1) : ?>
                                <a href="<?php echo esc_url(add_query_arg('paged', $current_page - 1)); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    <?php esc_html_e('Previous', 'pulseem'); ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($current_page < $total_pages) : ?>
                                <a href="<?php echo esc_url(add_query_arg('paged', $current_page + 1)); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    <?php esc_html_e('Next', 'pulseem'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    <?php
                                    printf(
                                        /* translators: %1$d: current page number, %2$d: total pages */
                                        esc_html__('Page %1$d of %2$d', 'pulseem'),
                                        intval($current_page),
                                        intval($total_pages)
                                    ); ?>
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <?php if ($current_page > 1) : ?>
                                        <a href="<?php echo esc_url(add_query_arg('paged', $current_page - 1)); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <span class="sr-only"><?php esc_html_e('Previous', 'pulseem'); ?></span>
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </a>
                                    <?php endif; ?>

                                    <?php
                                    $start_page = max(1, $current_page - 2);
                                    $end_page = min($total_pages, $current_page + 2);

                                    for ($i = $start_page; $i <= $end_page; $i++) :
                                    ?>
                                        <a href="<?php echo esc_url(add_query_arg('paged', $i)); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium <?php echo $i === $current_page ? 'z-10 bg-pink-50 border-pink-500 text-pink-600' : 'bg-white text-gray-500 hover:bg-gray-50'; ?>">
                                            <?php echo esc_html($i); ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($current_page < $total_pages) : ?>
                                        <a href="<?php echo esc_url(add_query_arg('paged', $current_page + 1)); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <span class="sr-only"><?php esc_html_e('Next', 'pulseem'); ?></span>
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Detail Modal -->
    <div x-show="modalOpen" x-cloak class="pulseem-modal-overlay fixed inset-0 z-50 flex items-center justify-center" @click.self="modalOpen = false">
        <div class="pulseem-modal-backdrop fixed inset-0 bg-black bg-opacity-50"></div>
        <div class="pulseem-modal-content relative bg-white rounded-lg shadow-xl max-w-5xl w-full mx-4 max-h-[90vh] overflow-y-auto z-10">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between sticky top-0 bg-white z-10">
                <h3 class="text-lg font-medium text-gray-900"><?php esc_html_e('Log Details', 'pulseem'); ?></h3>
                <button @click="modalOpen = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="px-6 py-5 space-y-4">
                <template x-if="modalData">
                    <div>
                        <!-- Meta info grid -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm bg-gray-50 rounded-lg p-4">
                            <div><span class="block font-medium text-gray-400 text-xs uppercase"><?php esc_html_e('ID', 'pulseem'); ?></span> <span x-text="modalData.id" class="font-mono"></span></div>
                            <div><span class="block font-medium text-gray-400 text-xs uppercase"><?php esc_html_e('Timestamp', 'pulseem'); ?></span> <span x-text="modalData.timestamp"></span></div>
                            <div><span class="block font-medium text-gray-400 text-xs uppercase"><?php esc_html_e('Level', 'pulseem'); ?></span> <span x-text="modalData.level"></span></div>
                            <div><span class="block font-medium text-gray-400 text-xs uppercase"><?php esc_html_e('Context', 'pulseem'); ?></span> <span x-text="modalData.context"></span></div>
                            <div><span class="block font-medium text-gray-400 text-xs uppercase"><?php esc_html_e('Email', 'pulseem'); ?></span> <span x-text="modalData.email"></span></div>
                            <div><span class="block font-medium text-gray-400 text-xs uppercase"><?php esc_html_e('User ID', 'pulseem'); ?></span> <span x-text="modalData.user_id"></span></div>
                            <div><span class="block font-medium text-gray-400 text-xs uppercase"><?php esc_html_e('IP Address', 'pulseem'); ?></span> <span x-text="modalData.ip_address"></span></div>
                            <div><span class="block font-medium text-gray-400 text-xs uppercase"><?php esc_html_e('Request ID', 'pulseem'); ?></span> <span x-text="modalData.request_id" class="text-xs font-mono break-all"></span></div>
                        </div>

                        <!-- Message -->
                        <div class="mt-4">
                            <h4 class="font-semibold text-gray-700 text-sm mb-1"><?php esc_html_e('Message', 'pulseem'); ?></h4>
                            <p class="text-sm text-gray-900 bg-gray-50 rounded p-3" x-text="modalData.message"></p>
                        </div>

                        <!-- API URL + Method (if present in data) -->
                        <template x-if="modalData.data && modalData.data.api_url">
                            <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <h4 class="font-semibold text-blue-800 text-sm mb-2"><?php esc_html_e('API Call', 'pulseem'); ?></h4>
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="inline-block bg-blue-600 text-white px-2 py-0.5 rounded text-xs font-bold" x-text="modalData.data.method || 'POST'"></span>
                                    <code class="text-blue-900 break-all" x-text="modalData.data.api_url"></code>
                                </div>
                                <template x-if="modalData.data.http_code">
                                    <div class="mt-1 text-sm">
                                        <span class="text-gray-500"><?php esc_html_e('HTTP Status:', 'pulseem'); ?></span>
                                        <span class="font-bold" :class="modalData.data.http_code === 200 ? 'text-green-600' : 'text-red-600'" x-text="modalData.data.http_code"></span>
                                    </div>
                                </template>
                                <template x-if="modalData.data.error">
                                    <div class="mt-1 text-sm text-red-700">
                                        <span class="font-medium"><?php esc_html_e('Error:', 'pulseem'); ?></span> <span x-text="modalData.data.error"></span>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Request Body (separate section) -->
                        <template x-if="modalData.data && modalData.data.request_body">
                            <div class="mt-4">
                                <h4 class="font-semibold text-gray-700 text-sm mb-1 flex items-center gap-2">
                                    <span class="inline-block w-2 h-2 bg-green-500 rounded-full"></span>
                                    <?php esc_html_e('Request Body (sent to Pulseem)', 'pulseem'); ?>
                                </h4>
                                <pre class="p-3 bg-gray-900 text-green-400 rounded-lg text-xs overflow-auto max-h-72 font-mono" x-text="JSON.stringify(modalData.data.request_body, null, 2)"></pre>
                            </div>
                        </template>

                        <!-- Response Body (separate section) -->
                        <template x-if="modalData.data && modalData.data.response_body">
                            <div class="mt-4">
                                <h4 class="font-semibold text-gray-700 text-sm mb-1 flex items-center gap-2">
                                    <span class="inline-block w-2 h-2 bg-blue-500 rounded-full"></span>
                                    <?php esc_html_e('Response Body (from Pulseem)', 'pulseem'); ?>
                                </h4>
                                <pre class="p-3 bg-gray-900 text-blue-400 rounded-lg text-xs overflow-auto max-h-72 font-mono" x-text="JSON.stringify(modalData.data.response_body, null, 2)"></pre>
                            </div>
                        </template>

                        <!-- Generic Data (for non-API logs that don't have request_body/response_body) -->
                        <template x-if="modalData.data && !modalData.data.request_body && !modalData.data.response_body && !modalData.data.api_url">
                            <div class="mt-4">
                                <h4 class="font-semibold text-gray-700 text-sm mb-1"><?php esc_html_e('Data', 'pulseem'); ?></h4>
                                <pre class="p-3 bg-gray-100 rounded-lg text-xs overflow-auto max-h-72 font-mono" x-text="JSON.stringify(modalData.data, null, 2)"></pre>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<?php
// Scripts and styles are enqueued by the admin controller
?>
