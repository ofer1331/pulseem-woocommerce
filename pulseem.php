<?php

/**
 * Pulseem WooCommerce Integration
 * 
 * Main plugin file that handles core plugin functionality including:
 * - Plugin initialization and setup 
 * - Required file loading and dependencies
 * - Class instantiation and hooks registration
 * - WooCommerce compatibility checks
 * - Thank you page customization
 * - Purchase tracking and data collection
 * Serves as the main entry point and orchestrator for the plugin.
 *
 * Plugin Name: Pulseem
 * Plugin URI: https://www.pulseem.co.il/
 * Description: WooCommerce integration with Pulseem API.
 * Version: 1.4.2
 * Author: Pulseem
 * Author URI: https://www.pulseem.co.il/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pulseem
 * Domain Path: /languages
 * Requires at least: 6.6
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 *
 * @since      1.0.0
 * @version    1.4.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use pulseem\ScriptsController;
use pulseem\WooCheckoutForm;
use pulseem\WooPulseemAbandonedController;
use pulseem\WooPulseemAbandonedCron;
use pulseem\WooPulseemAbDbModel;
use pulseem\WooPulseemActions;
use pulseem\WooPulseemAdminController;
use pulseem\WooRegistrationForm;
use pulseem\WpRegistrationForm;

// ===== LOAD TEXTDOMAIN =====
add_action('init', 'pulseem_load_textdomain');
function pulseem_load_textdomain() {
    load_plugin_textdomain(
        'pulseem',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}

// ===== CORE INCLUDES =====
// Include abandoned cart functionality
require_once __DIR__ . '/includes/abandoned/class-abandoned-db-model.php';
require_once __DIR__ . '/includes/abandoned/class-abandoned-model.php';
require_once __DIR__ . '/includes/class-pulseem-logger.php';

// ===== PLUGIN CONSTANTS =====
// Define constants only if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    define('PULSEEM_DIR', __DIR__);
    define('PULSEEM_URI', plugin_dir_url(__FILE__));
    define('PULSEEM_ASSETS_URI', PULSEEM_URI . 'assets/');
    define('PULSEEM_INCLUDES', PULSEEM_DIR . '/inc');
    define('PULSEEM_PUBLIC_URI', PULSEEM_URI . 'public/');
    define('PULSEEM_WSDL_URL', "https://www.pulseem.com/Pulseem/PulseemServices.asmx?WSDL");
}

// ===== ACTIVATION HOOKS =====
/**
 * Plugin activation hook
 * Creates database tables and sets default options
 */
register_activation_hook(__FILE__, "pulseem_register_activation_hook");
function pulseem_register_activation_hook() {
    $pulseem_ab_db_model = new WooPulseemAbDbModel();
    $pulseem_ab_db_model->createTable();

    // Create logs table
    \pulseem\PulseemLogger::create_table();

    \pulseem\PulseemLogger::info(
        \pulseem\PulseemLogger::CONTEXT_ACTIVATION,
        'Plugin activated',
        ['version' => '1.4.2']
    );

    add_option('pulseem_needs_api_setup', true);
    $default_settings = get_option('pulseem_settings', []);
    $default_settings['is_woocommerce_page_tracking'] = 1;
    update_option('pulseem_settings', $default_settings);
}

// ===== WOOCOMMERCE INTEGRATION =====
/**
 * Initialize plugin if WooCommerce is active
 * Load dependencies and instantiate required classes
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    // Load dependencies
    require_once __DIR__ . '/includes/class-user-model.php';
    require_once __DIR__ . '/includes/class-admin-model.php';
    require_once __DIR__ . '/includes/class-product-model.php';
    require_once __DIR__ . '/includes/class-admin-controller.php';
    require_once __DIR__ . '/includes/class-scripts-controller.php';
    require_once __DIR__ . '/includes/agreement/class-wp-registration-form.php';
    require_once __DIR__ . '/includes/agreement/class-woo-registration-form.php';
    require_once __DIR__ . '/includes/agreement/class-woo-checkout-form.php';
    require_once __DIR__ . '/includes/pulseem/class-pulseem-groups.php';
    require_once __DIR__ . '/includes/pulseem/class-pulseem-general.php';
    require_once __DIR__ . '/includes/class-pulseem-actions.php';
    require_once __DIR__ . '/includes/abandoned/class-abandoned-controller.php';
    require_once __DIR__ . '/includes/abandoned/class-abandoned-cron.php';
    require_once __DIR__ . '/includes/class-page-tracking.php';

    // Initialize classes
    new ScriptsController();
    new WooPulseemAbandonedController();
    new WooPulseemAdminController();
    new WpRegistrationForm();
    new WooRegistrationForm();
    new WooCheckoutForm();
    new WooPulseemActions();
    new WooPulseemAbandonedCron();
    if (class_exists('pulseem\WooPulseemPageTracking')) {
        new pulseem\WooPulseemPageTracking();
    }

    // Register logs cleanup cron
    add_action('pulseem_logs_cleanup', ['\pulseem\PulseemLogger', 'run_cleanup']);
    \pulseem\PulseemLogger::schedule_cleanup();

    // Run DB migration on admin_init if needed (handles plugin updates without reactivation)
    add_action('admin_init', function() {
        $current_version = get_option(\pulseem\PulseemLogger::DB_VERSION_OPTION, '0');
        if (version_compare($current_version, \pulseem\PulseemLogger::DB_VERSION, '<')) {
            \pulseem\PulseemLogger::create_table();
        }
    });

    /**
     * Add settings link to plugins page
     * 
     * @param array $links Default plugin links
     * @return array Modified plugin links
     */
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pulseem_add_action_links');
    function pulseem_add_action_links($links) {
        $mylinks = [
            '<a href="' . esc_url(admin_url('admin.php?page=pulseem_settings')) . '">' . __('Settings', 'pulseem') . '</a>',
        ];
        return array_merge($links, $mylinks);
    }
}

// ===== CONTACT FORM 7 INTEGRATION =====
/**
 * Include Contact Form 7 integration if CF7 is active
 */
if (defined('WPCF7_VERSION')) {
    require_once __DIR__ . '/includes/class-cf7-pulseem-integration.php';
}

// ===== ELEMENTOR INTEGRATION =====
/**
 * Register custom Elementor form action
 * 
 * @param object $actions_manager Elementor actions manager
 */
add_action('elementor_pro/forms/actions/register', function($actions_manager) {
    require_once __DIR__ . '/includes/class-elementor-custom-action.php';
    $actions_manager->register(new Pulseem_Elementor_Custom_Action());
});

// ===== TRACKING SCRIPTS =====
/**
 * Add Pulseem tracking script to the head
 */
add_action('wp_enqueue_scripts', 'pulseem_head_script');
function pulseem_head_script() {
    wp_enqueue_script(
        'pulseem-tracking-main',
        'https://webscript.prd.services.pulseem.com/main.js',
        array(),
        null,
        array( 'strategy' => 'defer' )
    );
}

// ===== ADMIN ASSETS =====
/**
 * Enqueue admin scripts and styles
 * 
 * @param string $hook Current admin page
 */
add_action('admin_enqueue_scripts', 'pulseem_enqueue_admin_assets');
function pulseem_enqueue_admin_assets($hook) {
    $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    if ($current_page === 'pulseem_settings' || $current_page === 'pulseem_logs') {
       // Enqueue Tailwind CSS (local)
       wp_enqueue_script(
        'pulseem-tailwind',
        PULSEEM_ASSETS_URI . 'js/tailwindcss.min.js',
        [],
        '1.0.0'
        );
        
        // Enqueue Alpine.js (local)
        wp_enqueue_script(
            'pulseem-alpine-js',
            PULSEEM_ASSETS_URI . 'js/alpinejs.min.js',
            [],
            '1.0.0',
            false
        );

        // Enqueue Confetti (local)
        wp_enqueue_script(
            'pulseem-confetti-js',
            PULSEEM_ASSETS_URI . 'js/confetti.browser.min.js',
            [],
            '1.0.0',
            true
        );

        // Enqueue local styles
        wp_enqueue_style(
            'pulseem-select2-css',
            PULSEEM_ASSETS_URI . 'style/select2/css/select2.min.css',
            [],
            '1.0.0'
        );

        wp_enqueue_style(
            'pulseem-plugin-css',
            PULSEEM_ASSETS_URI . 'style/css/plugin.css',
            [],
            '1.0.0'
        );

        // Enqueue local scripts
        wp_enqueue_script(
            'pulseem-select2-js',
            PULSEEM_ASSETS_URI . 'style/select2/js/select2.full.min.js',
            ['jquery'],
            '1.0.0',
            false
        );

        // Enqueue admin settings JS
        wp_enqueue_script(
            'pulseem-admin-js',
            PULSEEM_ASSETS_URI . 'js/pulseem-admin.js',
            ['jquery', 'pulseem-select2-js'],
            '1.0.0',
            true
        );

        wp_localize_script('pulseem-admin-js', 'pulseem_admin_i18n', [
            'sync_success' => __('Products synchronized successfully!', 'pulseem'),
            'sync_error'   => __('Error occurred during synchronization.', 'pulseem'),
        ]);
    }
}

// ===== ORDER PROCESSING =====
/**
 * Customize thank you page text and clean up abandoned cart data
 * 
 * @param string $thank_you_title Original thank you message
 * @param object $order WooCommerce order object
 * @return string Empty string to override default message
 */
add_action('woocommerce_thankyou', 'pulseem_clear_abandoned', 10, 1);
function pulseem_clear_abandoned($order_id) {
    if ( ! $order_id ) return;

    $order = wc_get_order($order_id);
    if ( ! $order ) return;

    global $wpdb;
    $billing_email = $order->get_billing_email();
    $customer_id   = $order->get_customer_id();
    $table_name    = $wpdb->prefix . "pulseem_abandoned";

    if ( ! empty($customer_id) ) {
        $wpdb->delete(
            $table_name,
            ['email' => sanitize_email($billing_email)]
        );
    }
}
/**
 * Track purchase data for Pulseem
 * Gets order details and sends them to Pulseem tracking script
 * 
 * @param int $order_id WooCommerce order ID
 */
add_action('woocommerce_thankyou', 'pulseem_get_order_data');
function pulseem_get_order_data($order_id) {
    $order = wc_get_order($order_id);
    if ( ! $order ) {
        return;
    }
    $total_amount = $order->get_total();
    $total_tax_amount = number_format((float)$order->get_total_tax(), 2, '.', '');
    $total_shipping_amount = $order->get_shipping_total();
    $items = $order->get_items();
    $product_js = [];

    foreach ($items as $item_data) {
        $product = wc_get_product($item_data->get_product_id());
        if ( ! $product ) {
            continue;
        }
        $product_js[] = [
            'name'     => $item_data->get_name(),
            'quantity' => $item_data->get_quantity(),
            'itemCode' => $product->get_sku(),
            'price'    => number_format((float)$item_data->get_total(), 2, '.', ''),
        ];
    }

    wp_enqueue_script(
        'pulseem-purchase-tracking',
        PULSEEM_PUBLIC_URI . 'pulseem-purchase-tracking.js',
        array(),
        '1.4.2',
        true
    );

    wp_localize_script('pulseem-purchase-tracking', 'pulseem_purchase_data', [
        'orderId'    => absint($order_id),
        'grandTotal' => $total_amount,
        'shipping'   => $total_shipping_amount,
        'tax'        => $total_tax_amount,
        'orderItems' => $product_js,
    ]);
}

// ===== DEACTIVATION HOOK =====
register_deactivation_hook( __FILE__, 'pulseem_deactivation' );
function pulseem_deactivation() {
    \pulseem\PulseemLogger::info(
        \pulseem\PulseemLogger::CONTEXT_ACTIVATION,
        'Plugin deactivated'
    );
    wp_clear_scheduled_hook( 'pulseem_abandoned_cron_hook' );
    wp_clear_scheduled_hook( 'pulseem_logs_cleanup' );
}

// ===== PRIVACY / GDPR =====
add_action( 'admin_init', 'pulseem_add_privacy_policy_content' );
function pulseem_add_privacy_policy_content() {
    if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
        return;
    }

    $content = '<h2>' . esc_html__( 'Pulseem Marketing Integration', 'pulseem' ) . '</h2>' .
        '<p>' . esc_html__( 'This plugin sends certain personal data to the Pulseem marketing platform when specific features are enabled:', 'pulseem' ) . '</p>' .
        '<ul>' .
        '<li>' . esc_html__( 'Customer registration: email, name, and phone number are synced to Pulseem groups.', 'pulseem' ) . '</li>' .
        '<li>' . esc_html__( 'Purchase tracking: order details including billing info, products, and amounts are sent to Pulseem.', 'pulseem' ) . '</li>' .
        '<li>' . esc_html__( 'Abandoned cart recovery: checkout form data (email, phone, name, cart contents, shipping address) is stored locally and synced to Pulseem.', 'pulseem' ) . '</li>' .
        '<li>' . esc_html__( 'Page tracking: page view data (URL, product ID, timestamp) is sent to Pulseem for logged-in users.', 'pulseem' ) . '</li>' .
        '</ul>' .
        '<p>' . sprintf(
            /* translators: %1$s: link to terms of use, %2$s: link to privacy policy */
            esc_html__( 'For more information, see Pulseem\'s %1$s and %2$s.', 'pulseem' ),
            '<a href="https://www.pulseem.co.il/terms" target="_blank">' . esc_html__( 'Terms of Use', 'pulseem' ) . '</a>',
            '<a href="https://www.pulseem.co.il/privacy" target="_blank">' . esc_html__( 'Privacy Policy', 'pulseem' ) . '</a>'
        ) . '</p>';

    wp_add_privacy_policy_content( 'Pulseem', $content );
}

// Personal data exporter
add_filter( 'wp_privacy_personal_data_exporters', 'pulseem_register_data_exporter' );
function pulseem_register_data_exporter( $exporters ) {
    $exporters['pulseem'] = [
        'exporter_friendly_name' => __( 'Pulseem Abandoned Cart Data', 'pulseem' ),
        'callback'               => 'pulseem_personal_data_exporter',
    ];
    return $exporters;
}

function pulseem_personal_data_exporter( $email_address, $page = 1 ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pulseem_abandoned';
    $data_to_export = [];

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM `" . esc_sql( $table_name ) . "` WHERE customer_data LIKE %s",
            '%' . $wpdb->esc_like( $email_address ) . '%'
        )
    );

    if ( $rows ) {
        foreach ( $rows as $row ) {
            $customer_data = json_decode( $row->customer_data, true );
            if ( is_array( $customer_data ) && isset( $customer_data['email'] ) && $customer_data['email'] === $email_address ) {
                $data_to_export[] = [
                    'group_id'    => 'pulseem_abandoned_cart',
                    'group_label' => __( 'Pulseem Abandoned Cart', 'pulseem' ),
                    'item_id'     => 'pulseem-abandoned-' . $row->id,
                    'data'        => [
                        [ 'name' => __( 'Email', 'pulseem' ), 'value' => $customer_data['email'] ?? '' ],
                        [ 'name' => __( 'First Name', 'pulseem' ), 'value' => $customer_data['first_name'] ?? '' ],
                        [ 'name' => __( 'Last Name', 'pulseem' ), 'value' => $customer_data['last_name'] ?? '' ],
                        [ 'name' => __( 'Phone', 'pulseem' ), 'value' => $customer_data['phone'] ?? '' ],
                        [ 'name' => __( 'Date', 'pulseem' ), 'value' => date_i18n( get_option( 'date_format' ), $row->time ) ],
                    ],
                ];
            }
        }
    }

    return [
        'data' => $data_to_export,
        'done'  => true,
    ];
}

// Personal data eraser
add_filter( 'wp_privacy_personal_data_erasers', 'pulseem_register_data_eraser' );
function pulseem_register_data_eraser( $erasers ) {
    $erasers['pulseem'] = [
        'eraser_friendly_name' => __( 'Pulseem Abandoned Cart Data', 'pulseem' ),
        'callback'             => 'pulseem_personal_data_eraser',
    ];
    return $erasers;
}

function pulseem_personal_data_eraser( $email_address, $page = 1 ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pulseem_abandoned';
    $items_removed = 0;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM `" . esc_sql( $table_name ) . "` WHERE customer_data LIKE %s",
            '%' . $wpdb->esc_like( $email_address ) . '%'
        )
    );

    if ( $rows ) {
        foreach ( $rows as $row ) {
            $customer_data = json_decode( $row->customer_data, true );
            if ( is_array( $customer_data ) && isset( $customer_data['email'] ) && $customer_data['email'] === $email_address ) {
                $wpdb->delete( $table_name, [ 'id' => $row->id ] );
                $items_removed++;
            }
        }
    }

    return [
        'items_removed'  => $items_removed,
        'items_retained' => false,
        'messages'       => [],
        'done'           => true,
    ];
}