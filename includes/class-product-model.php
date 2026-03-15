<?php
/**
* Product Synchronization Manager
* 
* Manages WooCommerce product synchronization with Pulseem including:
* - JSON feed generation for products and variations
* - Real-time product updates via webhooks
* - Bulk product synchronization
* - REST API endpoint for product data
* - Stock management integration
* Handles all aspects of product data synchronization between WooCommerce and Pulseem.
*
* @since      1.0.0
* @version    1.0.0
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use pulseem\WooPulseemAdminModel;


class PulseemProductSync {
    private $products;

    /**
     * Constructor for the ProductSync class.
     * Initializes an empty array for products.
     * 
     * Create Version: 1.0
     * Last Update Version: 1.0
     */
    public function __construct() {
        $this->products = [];
    }

    /**
     * Generate the products JSON file containing all WooCommerce products and variations.
     * This function collects all products, formats them, and outputs a JSON file.
     * 
     * Create Version: 1.0
     * Last Update Version: 1.0
     * 
     * @return void
     */
    public function generate_products_json() {
        $this->get_products();
        return new \WP_REST_Response( $this->products, 200 );
    }

    /**
     * Fetch all WooCommerce products and variations.
     * This function retrieves all products and variations using WP_Query and adds them to the products array.
     * 
     * Create Version: 1.0
     * Last Update Version: 1.0
     * 
     * @return void
     */
    private function get_products() {
        $args = [
            'post_type' => ['product', 'product_variation'],
            'posts_per_page' => -1,
        ];
        $products_query = new WP_Query($args);

        if ($products_query->have_posts()) {
            while ($products_query->have_posts()) {
                $products_query->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);

                // Add each product or variation to the products array
                $this->products[] = $this->format_product($product);
            }
            wp_reset_postdata();
        }
    }

    /**
     * Format product data into an array structure suitable for JSON output.
     * The function includes basic product info like ID, name, SKU, prices, and stock status. 
     * For variations, it includes the parent product ID.
     * 
     * Create Version: 1.0
     * Last Update Version: 1.0
     * 
     * @param WC_Product $product WooCommerce product object.
     * @return array Formatted product data.
     */
    public function format_product($product) { // changed to public
        $formatted_product = [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'is_variation' => $product->is_type('variation'),
            'last_modified' => get_post_modified_time('Y-m-d H:i:s', false, $product->get_id(), true),
            'stock_management' => $product->managing_stock(),
            'stock_quantity' => $product->managing_stock() ? $product->get_stock_quantity() : 'N/A'
        ];

        // Include parent product ID if it's a variation
        if ($product->is_type('variation')) {
            $parent_id = wp_get_post_parent_id($product->get_id());
            $formatted_product['parent_id'] = $parent_id;
        }

        return $formatted_product;
    }
}

/**
 * Register a REST API endpoint for generating the products JSON.
 * 
 * Create Version: 1.0
 * Last Update Version: 1.0
 * 
 * @return void
 */
function pulseem_register_product_sync_endpoint() {
    register_rest_route('pulseem/v1', '/get-products-json', [
        'methods' => 'GET',
        'callback' => 'pulseem_generate_products_json_callback',
        'permission_callback' => function() {
            return current_user_can('manage_woocommerce');
        },
    ]);
}

/**
 * Callback function that creates the JSON for the products and variations.
 * This function uses the ProductSync class to generate the JSON output.
 * 
 * Create Version: 1.0
 * Last Update Version: 1.0
 * 
 * @return void
 */
function pulseem_generate_products_json_callback() {
    $product_sync = new PulseemProductSync();
    return $product_sync->generate_products_json();
}

/**
 * Hook into WooCommerce product updates (including stock changes) and send product data to the webhook.
 * 
 * Create Version: 1.0
 * Last Update Version: 1.0
 * 
 * @param int $post_id The ID of the product or variation being updated.
 * @return void
 */
/**
 * Send product update to Pulseem on save, create, or update.
 *
 * Hooks into the `save_post` action and sends the updated product data to Pulseem.
 *
 * @since 1.0.0
 * @version 1.0.0
 * @param int $post_id The ID of the product or variation being updated.
 * @return void
 */
/**
 * Send product update to Pulseem on save, create, or update.
 *
 * Hooks into the `save_post` action and sends the updated product data to Pulseem
 * only if "Enable Product Synchronization" is enabled.
 *
 * @since 1.0.0
 * @version 1.0.0
 * @param int $post_id The ID of the product or variation being updated.
 * @return void
 */
function pulseem_send_product_update_sync($post_id) {
    // Ensure this only runs for products and variations
    if (get_post_type($post_id) !== 'product' && get_post_type($post_id) !== 'product_variation') {
        return;
    }

    // Check if "Enable Product Synchronization" is enabled
    $is_product_sync_enabled = get_option('pulseem_settings')['is_product_sync'] ?? false;
    if (!$is_product_sync_enabled) {
        return; // Exit if synchronization is disabled
    }

    // Get the product object
    $product = wc_get_product($post_id);
    if (!$product) {
        return; // Exit if product is not valid
    }

    // Prepare the product data for synchronization
    $product_ids = [$post_id];

    // Use the sendProductDataToPulseem function to send the product data
    $response = pulseem_send_product_data($product_ids);

    \pulseem\PulseemLogger::debug(
        \pulseem\PulseemLogger::CONTEXT_PRODUCT_SYNC,
        'Product update triggered for ID: ' . $post_id,
        ['product_name' => $product->get_name()]
    );
}

// Hook into `save_post` to trigger synchronization on product save or update
add_action('save_post', 'pulseem_send_product_update_sync', 10, 1);


// Register the REST API endpoint
add_action('rest_api_init', 'pulseem_register_product_sync_endpoint');


// רישום הפונקציה שתופעל כאשר התוסף מופעל
function pulseem_send_all_products_on_activation() {
    $product_ids = pulseem_get_all_product_and_variation_ids();
    pulseem_send_product_data($product_ids);
}

// Register AJAX action for product synchronization
add_action('wp_ajax_pulseem_sync_products', function() {
    check_ajax_referer('pulseem_product_sync_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }

    pulseem_send_all_products_on_activation();
    wp_send_json_success('Synchronization completed');
});

// Add server-side handling for AJAX request
add_action('wp_ajax_pulseem_update_product_sync_status', function() {
    // Verify nonce for security
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'pulseem_product_sync_nonce')) {
        wp_send_json_error(['message' => 'Security check failed'], 403);
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    if (!isset($_POST['is_product_sync'])) {
        wp_send_json_error(['message' => 'Missing parameter: is_product_sync'], 400);
    }

    $is_product_sync = intval(sanitize_text_field(wp_unslash($_POST['is_product_sync'])));

    // קבלת ההגדרות הנוכחיות
    $options = get_option('pulseem_settings', []);
    $options['is_product_sync'] = $is_product_sync;

    // עדכון הערך במסד הנתונים
    $updated = update_option('pulseem_settings', $options);

    // בדיקה אם העדכון הצליח
    if ($updated || $options['is_product_sync'] === $is_product_sync) {
        wp_send_json_success(['message' => 'Option updated successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to update option'], 500);
    }
});


function pulseem_get_all_product_and_variation_ids() {
    $args = [
        'post_type' => ['product', 'product_variation'],
        'posts_per_page' => -1,
        'fields' => 'ids',
    ];
    return get_posts($args);
}

function pulseem_send_product_data($product_ids) {
    $data = [
        "products" => [],
        "eventSource" => "WOOCOMMERCE",
    ];
    
    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            $price = $product->get_price();
            // if price is empty, skip this product
            if (empty($price)) {
                continue;
            }

            $data["products"][] = [
                "productID" => $product_id,
                "name" => $product->get_name(),
                "description" => $product->get_short_description(),
                "hrefUrl" => get_permalink($product->get_parent_id() ?: $product_id),
                "productCategoryNames" => implode(", ", wp_get_post_terms($product->get_parent_id() ?: $product_id, 'product_cat', ['fields' => 'names'])),
                "productCategoryIDs" => implode(", ", wp_get_post_terms($product->get_parent_id() ?: $product_id, 'product_cat', ['fields' => 'ids'])),
                "imagesURL" => wp_get_attachment_url($product->get_image_id()),
                "code" => $product->get_sku(),
                "price" => $price,
            ];
        }
    }

    $pulseem_admin_model = new WooPulseemAdminModel();
    $apikey = $pulseem_admin_model->getApiKey();

    $url = "https://ui-api.pulseem.com/api/v1/ProductsApi/AddProduct";

    // Use wp_remote_post instead of cURL
    $response = wp_remote_post($url, [
        'method' => 'POST',
        'timeout' => 30,
        'headers' => [
            'Content-Type' => 'application/json',
            'apiKey' => $apikey
        ],
        'body' => wp_json_encode($data)
    ]);

    // Check for errors
    if (is_wp_error($response)) {
        \pulseem\PulseemLogger::error(
            \pulseem\PulseemLogger::CONTEXT_PRODUCT_SYNC,
            'Product sync API failed: ' . $response->get_error_message(),
            [
                'api_url' => $url,
                'method' => 'POST',
                'products_count' => count($data['products']),
                'request_body' => $data,
                'error' => $response->get_error_message(),
            ]
        );
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $http_code = wp_remote_retrieve_response_code($response);

    \pulseem\PulseemLogger::info(
        \pulseem\PulseemLogger::CONTEXT_PRODUCT_SYNC,
        'Product sync completed',
        [
            'api_url' => $url,
            'method' => 'POST',
            'http_code' => $http_code,
            'products_count' => count($data['products']),
            'request_body' => $data,
            'response_body' => json_decode($body, true),
        ]
    );

    return $body;
}