<?php
/**
 * WooPulseemPageTracking Class
 *
 * Tracks WooCommerce and regular pages using AJAX after the page has loaded.
 *
 * @since      1.3.6
 * @version    1.4.2
 */

namespace pulseem;

if (!defined('ABSPATH')) {
    exit;
}

use pulseem\PulseemLogger;

class WooPulseemPageTracking {

    private $api_key;

    public function __construct() {
        $options = get_option('pulseem_settings');
        $this->api_key = isset($options['api_key']) ? $options['api_key'] : '';

        add_action('wp_enqueue_scripts', [$this, 'enqueueTrackingScript']);
        add_action('wp_ajax_pulseem_track_page', [$this, 'handleTrackingAjax']);
        add_action('wp_ajax_nopriv_pulseem_track_page', [$this, 'handleTrackingAjax']);
    }

    /**
     * Enqueue the inline JavaScript tracking script.
     */
    public function enqueueTrackingScript() {

         // Only enqueue if user is logged in
         if (!is_user_logged_in()) {
            return;
        }

        wp_enqueue_script(
            'pulseem-page-tracking',
            PULSEEM_PUBLIC_URI . 'pulseem-page-tracking.js',
            array('jquery'),
            '1.4.2',
            true
        );

        wp_localize_script('pulseem-page-tracking', 'pulseem_tracking', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pulseem_tracking_nonce'),
            'post_id'  => absint( get_queried_object_id() ),
        ));
    }

    /**
     * Handle AJAX request to track page view.
     */
    public function handleTrackingAjax() {
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'pulseem_tracking_nonce' ) ) {
            PulseemLogger::warning(PulseemLogger::CONTEXT_PAGE_TRACKING, 'Invalid nonce');
            wp_send_json_error( 'Invalid nonce', 403 );
        }

        if (empty($this->api_key)) {
            wp_send_json_error(['message' => 'API key missing'], 400);
        }
    
        // Get tracking settings
        $options = get_option('pulseem_settings');
        $is_page_tracking = isset($options['is_page_tracking']) ? $options['is_page_tracking'] : false;
        $is_woocommerce_tracking = isset($options['is_woocommerce_page_tracking']) ? $options['is_woocommerce_page_tracking'] : false;
    
        // If both tracking options are disabled, don't track anything
        if (!$is_page_tracking && !$is_woocommerce_tracking) {
            wp_send_json_error(['message' => 'Tracking is disabled'], 400);
            return;
        }
    
        $post_id = absint($_POST['post_id'] ?? 0);
        
        // Get the current URL from the referrer
        $current_url = wp_get_referer() ?: home_url();
        $parsed_url = wp_parse_url($current_url);
        $path = trim($parsed_url['path'], '/');
        
        // Default values
        $arrivalSource = 'WOOCOMMERCE';
        $externalProductID = null;
        $title = $post_id ? get_the_title($post_id) : 'Unknown';
        
        // Check if WooCommerce is active
        if (class_exists('WooCommerce')) {
            // Get WooCommerce pages IDs
            $cart_page_id = wc_get_page_id('cart');
            $checkout_page_id = wc_get_page_id('checkout');
            $shop_page_id = wc_get_page_id('shop');
            
            $is_woo_page = false;
            
            // Check for specific WooCommerce pages
            if ($post_id == $cart_page_id || strpos($path, 'cart') !== false) {
                $arrivalSource = 'WOOCOMMERCE';
                $is_woo_page = true;
            } 
            elseif ($post_id == $checkout_page_id || 
                    strpos($path, 'checkout') !== false || 
                    strpos($path, 'order-received') !== false) {
                $arrivalSource = 'WOOCOMMERCE';
                $is_woo_page = true;
            }
            elseif ($post_id && get_post_type($post_id) === 'product') {
                $arrivalSource = 'WOOCOMMERCE';
                $externalProductID = $post_id;
                $is_woo_page = true;
            }
            elseif (
                $post_id == $shop_page_id || // Main shop page
                strpos($path, 'product-category') !== false || // Category pages
                strpos($path, 'product-tag') !== false || // Tag pages
                (strpos($path, $this->get_shop_page_slug()) !== false) // Shop page with custom slug
            ) {
                $arrivalSource = 'WOOCOMMERCE';
                $is_woo_page = true;
            }
    
            // Check if we should track this page based on settings
            if ($is_woo_page && !$is_woocommerce_tracking) {
                wp_send_json_error(['message' => 'WooCommerce tracking is disabled'], 400);
                return;
            } elseif (!$is_woo_page && !$is_page_tracking) {
                wp_send_json_error(['message' => 'Regular page tracking is disabled'], 400);
                return;
            }
        }
        // If it's a regular page and regular page tracking is disabled
        elseif (!$is_page_tracking) {
            wp_send_json_error(['message' => 'Regular page tracking is disabled'], 400);
            return;
        }
    
        // Get client data
        $clientData = $this->getClientData();
        
        if (!$clientData) {
            wp_send_json_error(['message' => 'Client data is missing'], 400);
            return;
        }
    
        // Build request body for logging
        $timestamp = current_time('mysql');
        $api_url = 'https://ui-api.pulseem.com/api/v1/ClientsApi/AddClientPageView';
        $request_body = [
            'clientData' => $clientData,
            'pageView' => [
                'url' => $current_url,
                'externalProductID' => $externalProductID,
                'timestamp' => $timestamp,
            ],
            'arrivalSource' => $arrivalSource,
        ];

        // Send tracking data
        $response = $this->sendTrackingData(
            $clientData,
            $current_url,
            $title,
            $timestamp,
            $arrivalSource,
            $externalProductID
        );

        if (is_wp_error($response)) {
            PulseemLogger::error(
                PulseemLogger::CONTEXT_PAGE_TRACKING,
                'API request failed',
                [
                    'api_url' => $api_url,
                    'method' => 'POST',
                    'request_body' => $request_body,
                    'error' => $response->get_error_message(),
                ]
            );
            wp_send_json_error(['message' => $response->get_error_message()], 500);
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        PulseemLogger::debug(
            PulseemLogger::CONTEXT_PAGE_TRACKING,
            'Page tracked',
            [
                'api_url' => $api_url,
                'method' => 'POST',
                'http_code' => $http_code,
                'request_body' => $request_body,
                'response_body' => json_decode($body, true),
            ]
        );
        wp_send_json_success(['message' => 'Tracking data sent successfully']);
    }

    /**
     * Get client data (email, phone, ID).
     */
    private function getClientData() {
        $user = wp_get_current_user();
        if (!$user->ID) {
            return null;
        }

        $email = $user->user_email;
        $phone = get_user_meta($user->ID, 'billing_phone', true);

        if (!$email || !$phone) {
            return null;
        }

        return [
            'email' => $email,
            'cellphone' => preg_replace('/\D/', '', $phone),
            'externalCustomerID' => $user->ID,
        ];
    }

    /**
     * Send data to Pulseem API.
     */
    private function sendTrackingData($clientData, $url, $title, $timestamp, $arrivalSource, $externalProductID = null) {
        $endpoint = 'https://ui-api.pulseem.com/api/v1/ClientsApi/AddClientPageView';
        $body = [
            'clientData' => $clientData,
            'pageView' => [
                'url' => $url,
                'externalProductID' => $externalProductID,
                'timestamp' => $timestamp,
            ],
            'arrivalSource' => $arrivalSource,
        ];

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json-patch+json',
                'accept' => 'application/json',
                'apiKey' => $this->api_key,
            ],
            'body' => wp_json_encode($body),
            'timeout' => 60,
        ]);

        return $response;
    }

    // Helper function to get shop page slug
    private function get_shop_page_slug() {
        $shop_page_id = wc_get_page_id('shop');
        $shop_page = get_post($shop_page_id);
        return $shop_page ? $shop_page->post_name : 'shop';
    }
}
