<?php
/**
 * Abandoned Cart Database Model
 *
 * Handles the database structure and operations for abandoned cart tracking.
 * Manages the creation and updates of the abandoned cart table including:
 * - Database table creation with proper collation
 * - Database version control and upgrades
 * - Schema management for user data, email, timestamps and customer info
 *
 * @since      1.0.0
 * @version    1.3.3
 */

namespace pulseem;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WooPulseemAbDbModel {

    /**
     * The full table name including prefix
     *
     * @var string
     */
    private $table_name;

    /**
     * WooPulseemAbDbModel constructor.
     */
    public function __construct() {
        global $wpdb;
        global $pulseem_ab_db_version;

        $pulseem_ab_db_version = "1.4";
        $this->table_name = $wpdb->prefix . "pulseem_abandoned";
    }

    /**
     * Creates (or updates) the abandoned carts table if it does not exist.
     * Always runs dbDelta with the latest schema to ensure columns are up to date.
     */
    public function createTable() {
        global $wpdb;
        global $pulseem_ab_db_version;

        $sql = sprintf(
            "CREATE TABLE %s (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                user_id bigint(11) DEFAULT '0' NOT NULL,
                email varchar(255) DEFAULT '' NOT NULL,
                time bigint(11) DEFAULT '0' NOT NULL,
                customer_id varchar(255) DEFAULT '' NOT NULL,
                customer_data text DEFAULT '' NOT NULL,
                user_agree tinyint(1) DEFAULT '0' NOT NULL,
                UNIQUE KEY id (id)
            ) COLLATE=utf8_general_ci;",
            esc_sql($this->table_name)
        );

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option('pulseem_ab_db_version', $pulseem_ab_db_version);
        $this->checkDb();
    }

    /**
     * Checks and updates the database schema if needed, based on pulseem_ab_db_version.
     */
    private function checkDb() {
        global $wpdb;
        global $pulseem_ab_db_version;

        $current_version = get_option('pulseem_ab_db_version', false);

        if ($current_version !== false && (float)$current_version < (float)$pulseem_ab_db_version) {
            
            $customer_id_exists = $wpdb->get_results(
                "SHOW COLUMNS FROM {$this->table_name} LIKE 'customer_id'"
            );
            if (empty($customer_id_exists)) {
                $wpdb->query(
                    sprintf(
                        "ALTER TABLE %s ADD COLUMN customer_id varchar(255) DEFAULT '' NOT NULL",
                        esc_sql($this->table_name)
                    )
                );
            }

            $customer_data_exists = $wpdb->get_results(
                "SHOW COLUMNS FROM {$this->table_name} LIKE 'customer_data'"
            );
            if (empty($customer_data_exists)) {
                $wpdb->query(
                    sprintf(
                        "ALTER TABLE %s ADD COLUMN customer_data text DEFAULT '' NOT NULL",
                        esc_sql($this->table_name)
                    )
                );
            }

            $user_agree_exists = $wpdb->get_results(
                "SHOW COLUMNS FROM {$this->table_name} LIKE 'user_agree'"
            );
            if (empty($user_agree_exists)) {
                $wpdb->query(
                    sprintf(
                        "ALTER TABLE %s ADD COLUMN user_agree tinyint(1) DEFAULT '0' NOT NULL",
                        esc_sql($this->table_name)
                    )
                );
            }

            update_option('pulseem_ab_db_db_version', $pulseem_ab_db_version);
        }
    }
}