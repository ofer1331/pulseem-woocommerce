<?php
/**
 * Pulseem Logger
 *
 * Handles logging for the Pulseem plugin with database storage.
 * Provides methods for adding, retrieving, and managing log entries.
 * Features: request_id correlation, buffered logging, configurable log level,
 * auto-cleanup cron, export (CSV/JSON), statistics, and bulk operations.
 *
 * @since      1.3.6
 * @version    1.4.0
 */

namespace pulseem;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PulseemLogger {

    /**
     * Database table name (without prefix)
     */
    const TABLE_NAME = 'pulseem_logs';

    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';

    /**
     * Log contexts/sources
     */
    const CONTEXT_API = 'api';
    const CONTEXT_USER_REGISTER = 'user_register';
    const CONTEXT_PURCHASE = 'purchase';
    const CONTEXT_ABANDONED_CART = 'abandoned_cart';
    const CONTEXT_ELEMENTOR = 'elementor';
    const CONTEXT_CF7 = 'cf7';
    const CONTEXT_PRODUCT_SYNC = 'product_sync';
    const CONTEXT_GENERAL = 'general';
    const CONTEXT_SETTINGS = 'settings';
    const CONTEXT_ACTIVATION = 'activation';
    const CONTEXT_PAGE_TRACKING = 'page_tracking';
    const CONTEXT_CRON = 'cron';
    const CONTEXT_AGREEMENT = 'agreement';

    /**
     * Level priority map
     */
    const LEVEL_PRIORITY = [
        'debug'   => 0,
        'info'    => 1,
        'warning' => 2,
        'error'   => 3,
    ];

    /**
     * Request ID for log correlation
     *
     * @var string|null
     */
    private static $request_id = null;

    /**
     * Log buffer for batch inserts
     *
     * @var array
     */
    private static $buffer = [];

    /**
     * Whether the shutdown flush has been registered
     *
     * @var bool
     */
    private static $shutdown_registered = false;

    /**
     * Max buffer entries per request
     */
    const MAX_BUFFER_SIZE = 100;

    /**
     * Get the full table name with prefix
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Get or generate the request ID for log correlation
     *
     * @return string
     */
    public static function get_request_id() {
        if (self::$request_id === null) {
            self::$request_id = wp_generate_uuid4();
        }
        return self::$request_id;
    }

    /**
     * Create the logs database table
     *
     * @return void
     */
    /**
     * DB schema version - increment when schema changes
     */
    const DB_VERSION = '2.0.0';
    const DB_VERSION_OPTION = 'pulseem_logs_db_version';

    public static function create_table() {
        global $wpdb;

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            level varchar(20) NOT NULL DEFAULT 'info',
            context varchar(50) NOT NULL DEFAULT 'general',
            message text NOT NULL,
            data longtext,
            user_id bigint(20) DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            request_id varchar(36) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY level (level),
            KEY context (context),
            KEY timestamp (timestamp),
            KEY email (email),
            KEY idx_request_id (request_id),
            KEY idx_level_timestamp (level, timestamp),
            KEY idx_context_timestamp (context, timestamp)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Only run migration if DB version is outdated (avoids SHOW COLUMNS on every page load)
        $current_version = get_option(self::DB_VERSION_OPTION, '0');
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            self::maybe_upgrade_table();
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        }
    }

    /**
     * Upgrade existing table schema if needed
     *
     * @return void
     */
    public static function maybe_upgrade_table() {
        global $wpdb;

        $table_name = self::get_table_name();

        // Check if table exists at all before upgrading
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            $wpdb->dbname,
            $table_name
        ));

        if (!$table_exists) {
            return;
        }

        // Check if request_id column exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `" . esc_sql($table_name) . "` LIKE 'request_id'");

        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE `" . esc_sql($table_name) . "` ADD COLUMN request_id varchar(36) DEFAULT NULL");
        }

        // Add indexes safely - check each before adding
        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM `" . esc_sql($table_name) . "`", ARRAY_A);
        $index_names = array_column($existing_indexes, 'Key_name');

        if (!in_array('idx_request_id', $index_names)) {
            $wpdb->query("ALTER TABLE `" . esc_sql($table_name) . "` ADD INDEX idx_request_id (request_id)");
        }
        if (!in_array('idx_level_timestamp', $index_names)) {
            $wpdb->query("ALTER TABLE `" . esc_sql($table_name) . "` ADD INDEX idx_level_timestamp (level, timestamp)");
        }
        if (!in_array('idx_context_timestamp', $index_names)) {
            $wpdb->query("ALTER TABLE `" . esc_sql($table_name) . "` ADD INDEX idx_context_timestamp (context, timestamp)");
        }
    }

    /**
     * Check if the request_id column exists in the table
     *
     * @return bool
     */
    private static function has_request_id_column() {
        global $wpdb;
        static $has_column = null;

        if ($has_column === null) {
            $table_name = self::get_table_name();
            $result = $wpdb->get_results("SHOW COLUMNS FROM `" . esc_sql($table_name) . "` LIKE 'request_id'");
            $has_column = !empty($result);
        }

        return $has_column;
    }

    /**
     * Check if a message at the given level should be logged
     *
     * @param string $level Log level
     * @return bool
     */
    private static function should_log($level) {
        $options = get_option('pulseem_settings', []);
        $min_level = isset($options['log_level']) ? $options['log_level'] : 'debug';

        $level_priority = isset(self::LEVEL_PRIORITY[$level]) ? self::LEVEL_PRIORITY[$level] : 0;
        $min_priority = isset(self::LEVEL_PRIORITY[$min_level]) ? self::LEVEL_PRIORITY[$min_level] : 0;

        return $level_priority >= $min_priority;
    }

    /**
     * Add a log entry
     *
     * @param string $level Log level (debug, info, warning, error)
     * @param string $context Log context/source
     * @param string $message Log message
     * @param array|null $data Additional data to log
     * @param string|null $email Related email address
     * @param int|null $user_id Related user ID
     * @return int|false The inserted log ID or false on failure
     */
    public static function log($level, $context, $message, $data = null, $email = null, $user_id = null) {
        if (!self::should_log($level)) {
            return false;
        }

        // Get user IP address
        $ip_address = self::get_client_ip();

        // Prepare data for insertion
        $insert_data = [
            'timestamp' => current_time('mysql'),
            'level' => sanitize_text_field($level),
            'context' => sanitize_text_field($context),
            'message' => sanitize_text_field($message),
            'data' => $data ? wp_json_encode($data) : null,
            'user_id' => $user_id ? absint($user_id) : null,
            'email' => $email ? sanitize_email($email) : null,
            'ip_address' => $ip_address,
        ];

        // Only include request_id if column exists (safe for pre-migration tables)
        if (self::has_request_id_column()) {
            $insert_data['request_id'] = self::get_request_id();
        }

        // Error-level logs flush immediately for safety
        if ($level === self::LEVEL_ERROR) {
            return self::insert_log($insert_data);
        }

        // Buffer non-error logs
        if (count(self::$buffer) < self::MAX_BUFFER_SIZE) {
            self::$buffer[] = $insert_data;
        }

        // Register shutdown flush if not done yet
        if (!self::$shutdown_registered) {
            self::$shutdown_registered = true;
            add_action('shutdown', [__CLASS__, 'flush']);
        }

        return true;
    }

    /**
     * Insert a single log entry into the database
     *
     * @param array $insert_data Log data
     * @return int|false
     */
    private static function insert_log($insert_data) {
        global $wpdb;

        try {
            $table_name = self::get_table_name();
            $result = $wpdb->insert($table_name, $insert_data);

            if ($result === false) {
                return false;
            }

            self::invalidate_stats_cache();
            return $wpdb->insert_id;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Flush buffered log entries to the database
     *
     * @return void
     */
    public static function flush() {
        if (empty(self::$buffer)) {
            return;
        }

        global $wpdb;
        $table_name = self::get_table_name();

        try {
            foreach (self::$buffer as $entry) {
                $wpdb->insert($table_name, $entry);
            }
        } catch (\Exception $e) {
            // Prevent logging from crashing the plugin
        }

        self::$buffer = [];
    }

    /**
     * Log a debug message
     *
     * @param string $context Log context
     * @param string $message Log message
     * @param array|null $data Additional data
     * @param string|null $email Related email
     * @param int|null $user_id Related user ID
     * @return int|false
     */
    public static function debug($context, $message, $data = null, $email = null, $user_id = null) {
        return self::log(self::LEVEL_DEBUG, $context, $message, $data, $email, $user_id);
    }

    /**
     * Log an info message
     *
     * @param string $context Log context
     * @param string $message Log message
     * @param array|null $data Additional data
     * @param string|null $email Related email
     * @param int|null $user_id Related user ID
     * @return int|false
     */
    public static function info($context, $message, $data = null, $email = null, $user_id = null) {
        return self::log(self::LEVEL_INFO, $context, $message, $data, $email, $user_id);
    }

    /**
     * Log a warning message
     *
     * @param string $context Log context
     * @param string $message Log message
     * @param array|null $data Additional data
     * @param string|null $email Related email
     * @param int|null $user_id Related user ID
     * @return int|false
     */
    public static function warning($context, $message, $data = null, $email = null, $user_id = null) {
        return self::log(self::LEVEL_WARNING, $context, $message, $data, $email, $user_id);
    }

    /**
     * Log an error message
     *
     * @param string $context Log context
     * @param string $message Log message
     * @param array|null $data Additional data
     * @param string|null $email Related email
     * @param int|null $user_id Related user ID
     * @return int|false
     */
    public static function error($context, $message, $data = null, $email = null, $user_id = null) {
        return self::log(self::LEVEL_ERROR, $context, $message, $data, $email, $user_id);
    }

    /**
     * Get logs with optional filtering and pagination
     *
     * @param array $args Query arguments
     * @return array Array of log entries
     */
    public static function get_logs($args = []) {
        global $wpdb;

        $defaults = [
            'level' => '',
            'context' => '',
            'email' => '',
            'search' => '',
            'date_from' => '',
            'date_to' => '',
            'request_id' => '',
            'per_page' => 50,
            'page' => 1,
            'orderby' => 'timestamp',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);
        $table_name = self::get_table_name();

        // Build WHERE clause
        $where = ['1=1'];
        $prepare_values = [];

        if (!empty($args['level'])) {
            $where[] = 'level = %s';
            $prepare_values[] = $args['level'];
        }

        if (!empty($args['context'])) {
            $where[] = 'context = %s';
            $prepare_values[] = $args['context'];
        }

        if (!empty($args['email'])) {
            $where[] = 'email LIKE %s';
            $prepare_values[] = '%' . $wpdb->esc_like($args['email']) . '%';
        }

        if (!empty($args['search'])) {
            $where[] = '(message LIKE %s OR data LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $prepare_values[] = $search_term;
            $prepare_values[] = $search_term;
        }

        if (!empty($args['date_from'])) {
            $where[] = 'timestamp >= %s';
            $prepare_values[] = $args['date_from'] . ' 00:00:00';
        }

        if (!empty($args['date_to'])) {
            $where[] = 'timestamp <= %s';
            $prepare_values[] = $args['date_to'] . ' 23:59:59';
        }

        if (!empty($args['request_id']) && self::has_request_id_column()) {
            $where[] = 'request_id = %s';
            $prepare_values[] = $args['request_id'];
        }

        $where_clause = implode(' AND ', $where);

        // Sanitize orderby and order
        $allowed_orderby = ['id', 'timestamp', 'level', 'context', 'email'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'timestamp';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Calculate offset
        $offset = ($args['page'] - 1) * $args['per_page'];

        // Build query — $table_name from $wpdb->prefix (safe), $orderby/$order validated above
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is from $wpdb->prefix, $orderby is validated against allowlist, $order is validated to ASC/DESC
        $query = "SELECT * FROM `$table_name` WHERE $where_clause ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $prepare_values[] = $args['per_page'];
        $prepare_values[] = $offset;

        if (!empty($prepare_values)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query uses prepare() with placeholders
            $query = $wpdb->prepare($query, $prepare_values);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom log table; query built with prepare() above. Table name derived from $wpdb->prefix.
        return $wpdb->get_results($query);
    }

    /**
     * Get total count of logs with optional filtering
     *
     * @param array $args Query arguments
     * @return int Total count
     */
    public static function get_logs_count($args = []) {
        global $wpdb;

        $defaults = [
            'level' => '',
            'context' => '',
            'email' => '',
            'search' => '',
            'date_from' => '',
            'date_to' => '',
            'request_id' => '',
        ];

        $args = wp_parse_args($args, $defaults);
        $table_name = self::get_table_name();

        // Build WHERE clause
        $where = ['1=1'];
        $prepare_values = [];

        if (!empty($args['level'])) {
            $where[] = 'level = %s';
            $prepare_values[] = $args['level'];
        }

        if (!empty($args['context'])) {
            $where[] = 'context = %s';
            $prepare_values[] = $args['context'];
        }

        if (!empty($args['email'])) {
            $where[] = 'email LIKE %s';
            $prepare_values[] = '%' . $wpdb->esc_like($args['email']) . '%';
        }

        if (!empty($args['search'])) {
            $where[] = '(message LIKE %s OR data LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $prepare_values[] = $search_term;
            $prepare_values[] = $search_term;
        }

        if (!empty($args['date_from'])) {
            $where[] = 'timestamp >= %s';
            $prepare_values[] = $args['date_from'] . ' 00:00:00';
        }

        if (!empty($args['date_to'])) {
            $where[] = 'timestamp <= %s';
            $prepare_values[] = $args['date_to'] . ' 23:59:59';
        }

        if (!empty($args['request_id']) && self::has_request_id_column()) {
            $where[] = 'request_id = %s';
            $prepare_values[] = $args['request_id'];
        }

        $where_clause = implode(' AND ', $where);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is from $wpdb->prefix
        $query = "SELECT COUNT(*) FROM `$table_name` WHERE $where_clause";

        if (!empty($prepare_values)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $query = $wpdb->prepare($query, $prepare_values);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom log table; query built with prepare() above. Table name derived from $wpdb->prefix.
        return (int) $wpdb->get_var($query);
    }

    /**
     * Get logs by request ID
     *
     * @param string $request_id Request ID
     * @return array
     */
    public static function get_logs_by_request_id($request_id) {
        return self::get_logs([
            'request_id' => $request_id,
            'per_page' => 1000,
            'page' => 1,
        ]);
    }

    /**
     * Delete a log entry by ID
     *
     * @param int $log_id Log ID
     * @return bool
     */
    public static function delete_log($log_id) {
        global $wpdb;
        $table_name = self::get_table_name();

        $result = $wpdb->delete($table_name, ['id' => absint($log_id)]) !== false;
        if ( $result ) {
            self::invalidate_stats_cache();
        }
        return $result;
    }

    /**
     * Bulk delete logs by IDs
     *
     * @param array $ids Array of log IDs
     * @return int Number of deleted rows
     */
    public static function delete_logs_by_ids($ids) {
        global $wpdb;
        $table_name = self::get_table_name();

        if (empty($ids) || !is_array($ids)) {
            return 0;
        }

        $ids = array_map('absint', $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $table_name from $wpdb->prefix, $placeholders are %d generated
        $query = $wpdb->prepare("DELETE FROM `$table_name` WHERE id IN ($placeholders)", $ids);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom log table; query built with prepare() and %d placeholders above.
        $deleted = (int) $wpdb->query($query);
        if ( $deleted > 0 ) {
            self::invalidate_stats_cache();
        }
        return $deleted;
    }

    /**
     * Delete logs older than specified days
     *
     * @param int $days Number of days
     * @return int Number of deleted rows
     */
    public static function delete_old_logs($days = 30) {
        global $wpdb;
        $table_name = self::get_table_name();

        $date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name derived from $wpdb->prefix; date value uses prepare().
        $deleted = $wpdb->query(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->prepare("DELETE FROM `$table_name` WHERE timestamp < %s", $date)
        );
        self::invalidate_stats_cache();
        return $deleted;
    }

    /**
     * Clear all logs
     *
     * @return bool
     */
    public static function clear_all_logs() {
        global $wpdb;
        $table_name = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name derived from $wpdb->prefix with fixed suffix; TRUNCATE has no parameterizable values.
        $result = $wpdb->query("TRUNCATE TABLE `$table_name`") !== false;
        self::invalidate_stats_cache();
        return $result;
    }

    /**
     * Get log statistics
     *
     * @return array
     */
    public static function get_stats() {
        $cache_key = 'pulseem_log_stats';
        $cached = wp_cache_get( $cache_key, 'pulseem' );

        if ( false !== $cached ) {
            return $cached;
        }

        global $wpdb;
        $table_name = self::get_table_name();

        $stats = [
            'total' => 0,
            'by_level' => [],
            'by_context' => [],
            'last_24h' => 0,
            'errors_24h' => 0,
            'warnings_24h' => 0,
            'table_size' => 0,
        ];

        // Total count
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name derived from $wpdb->prefix with fixed suffix; no user input.
        $stats['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table_name`");

        // Counts by level
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name derived from $wpdb->prefix with fixed suffix; no user input.
        $level_counts = $wpdb->get_results("SELECT level, COUNT(*) as count FROM `$table_name` GROUP BY level", ARRAY_A);
        foreach ($level_counts as $row) {
            $stats['by_level'][$row['level']] = (int) $row['count'];
        }

        // Counts by context
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name derived from $wpdb->prefix with fixed suffix; no user input.
        $context_counts = $wpdb->get_results("SELECT context, COUNT(*) as count FROM `$table_name` GROUP BY context", ARRAY_A);
        foreach ($context_counts as $row) {
            $stats['by_context'][$row['context']] = (int) $row['count'];
        }

        // Last 24h
        $since_24h = gmdate('Y-m-d H:i:s', strtotime('-24 hours'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name derived from $wpdb->prefix; dynamic values use prepare().
        $stats['last_24h'] = (int) $wpdb->get_var($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT COUNT(*) FROM `$table_name` WHERE timestamp >= %s",
            $since_24h
        ));

        // Errors in last 24h
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name derived from $wpdb->prefix; dynamic values use prepare().
        $stats['errors_24h'] = (int) $wpdb->get_var($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT COUNT(*) FROM `$table_name` WHERE timestamp >= %s AND level = 'error'",
            $since_24h
        ));

        // Warnings in last 24h
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name derived from $wpdb->prefix; dynamic values use prepare().
        $stats['warnings_24h'] = (int) $wpdb->get_var($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT COUNT(*) FROM `$table_name` WHERE timestamp >= %s AND level = 'warning'",
            $since_24h
        ));

        // Table size in bytes — force InnoDB to update statistics for accurate size
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name derived from $wpdb->prefix; ANALYZE is non-cacheable by nature.
        $wpdb->query("ANALYZE TABLE `$table_name`");
        $db_name = $wpdb->dbname;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- information_schema query for table size, not cacheable.
        $size_result = $wpdb->get_row($wpdb->prepare(
            "SELECT DATA_LENGTH + INDEX_LENGTH as size FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
            $db_name,
            $table_name
        ));
        $stats['table_size'] = $size_result ? (int) $size_result->size : 0;

        wp_cache_set( $cache_key, $stats, 'pulseem', 60 );

        return $stats;
    }

    /**
     * Invalidate the cached log statistics
     *
     * @return void
     */
    public static function invalidate_stats_cache() {
        wp_cache_delete( 'pulseem_log_stats', 'pulseem' );
    }

    /**
     * Export logs as CSV
     *
     * @param array $args Filter arguments
     * @return void
     */
    public static function export_csv($args = []) {
        $args['per_page'] = 10000;
        $args['page'] = 1;
        $logs = self::get_logs($args);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=pulseem-logs-' . gmdate('Y-m-d-His') . '.csv');

        $output = fopen('php://output', 'w');
        // BOM for Excel UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, ['ID', 'Timestamp', 'Level', 'Context', 'Message', 'Data', 'Email', 'User ID', 'IP Address', 'Request ID']);

        foreach ($logs as $log) {
            fputcsv($output, [
                $log->id,
                $log->timestamp,
                $log->level,
                $log->context,
                $log->message,
                $log->data,
                $log->email,
                $log->user_id,
                $log->ip_address,
                isset($log->request_id) ? $log->request_id : '',
            ]);
        }

        fclose($output); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- writing to php://output stream for CSV export
    }

    /**
     * Export logs as JSON
     *
     * @param array $args Filter arguments
     * @return void
     */
    public static function export_json($args = []) {
        $args['per_page'] = 10000;
        $args['page'] = 1;
        $logs = self::get_logs($args);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=pulseem-logs-' . gmdate('Y-m-d-His') . '.json');

        $export = [];
        foreach ($logs as $log) {
            $entry = (array) $log;
            if (!empty($entry['data'])) {
                $entry['data'] = json_decode($entry['data'], true);
            }
            $export[] = $entry;
        }

        echo wp_json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Schedule the auto-cleanup cron event
     *
     * @return void
     */
    public static function schedule_cleanup() {
        if (!wp_next_scheduled('pulseem_logs_cleanup')) {
            wp_schedule_event(time(), 'daily', 'pulseem_logs_cleanup');
        }
    }

    /**
     * Run the cleanup cron job
     *
     * @return void
     */
    public static function run_cleanup() {
        $options = get_option('pulseem_settings', []);
        $retention_days = isset($options['log_retention_days']) ? (int) $options['log_retention_days'] : 30;

        if ($retention_days > 0) {
            self::delete_old_logs($retention_days);
        }
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private static function get_client_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        // Handle multiple IPs in X-Forwarded-For
        if (strpos($ip, ',') !== false) {
            $ips = explode(',', $ip);
            $ip = trim($ips[0]);
        }

        return $ip;
    }

    /**
     * Get available log levels
     *
     * @return array
     */
    public static function get_levels() {
        return [
            self::LEVEL_DEBUG => __('Debug', 'pulseem'),
            self::LEVEL_INFO => __('Info', 'pulseem'),
            self::LEVEL_WARNING => __('Warning', 'pulseem'),
            self::LEVEL_ERROR => __('Error', 'pulseem'),
        ];
    }

    /**
     * Get available log contexts
     *
     * @return array
     */
    public static function get_contexts() {
        return [
            self::CONTEXT_API => __('API', 'pulseem'),
            self::CONTEXT_USER_REGISTER => __('User Registration', 'pulseem'),
            self::CONTEXT_PURCHASE => __('Purchase', 'pulseem'),
            self::CONTEXT_ABANDONED_CART => __('Abandoned Cart', 'pulseem'),
            self::CONTEXT_ELEMENTOR => __('Elementor', 'pulseem'),
            self::CONTEXT_CF7 => __('Contact Form 7', 'pulseem'),
            self::CONTEXT_PRODUCT_SYNC => __('Product Sync', 'pulseem'),
            self::CONTEXT_GENERAL => __('General', 'pulseem'),
            self::CONTEXT_SETTINGS => __('Settings', 'pulseem'),
            self::CONTEXT_ACTIVATION => __('Activation', 'pulseem'),
            self::CONTEXT_PAGE_TRACKING => __('Page Tracking', 'pulseem'),
            self::CONTEXT_CRON => __('Cron', 'pulseem'),
            self::CONTEXT_AGREEMENT => __('Agreement', 'pulseem'),
        ];
    }

    /**
     * Format bytes to human-readable size
     *
     * @param int $bytes
     * @return string
     */
    public static function format_bytes($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
}
