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
    /**
     * Upper bound for an unbounded (non-chunked) feed response. Above this the
     * endpoint refuses the request with 413 and tells the caller to chunk.
     */
    const FULL_FEED_HARD_LIMIT = 20000;

    private $products;

    // Per-request memoization of parent-level lookups so all variations of the
    // same parent only pay the DB cost once. Reset at the top of get_products().
    private static $parent_terms_cache     = [];
    private static $parent_permalink_cache = [];

    /**
     * Raise memory + time limits for heavy feed/zip responses.
     *
     * @return void
     */
    public static function raise_runtime_limits() {
        if ( function_exists( 'wp_raise_memory_limit' ) ) {
            wp_raise_memory_limit( 'admin' );
        }
        // phpcs:ignore WordPress.PHP.IniSet.Risky -- Raising memory limit for a single feed/zip request; scoped to this script execution only.
        @ini_set( 'memory_limit', '1024M' );
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 300 );
        }
    }

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
     * Generate the products feed (JSON by default, XML when ?format=xml).
     * Accepts optional ?chunk=N&chunk_size=M query params for paginated exports.
     *
     * @param \WP_REST_Request|null $request
     * @return \WP_REST_Response
     */
    public function generate_products_json( $request = null ) {
        $chunk      = null;
        $chunk_size = null;
        $format     = 'json';
        $download   = false;

        if ( $request instanceof \WP_REST_Request ) {
            if ( null !== $request->get_param( 'chunk_size' ) ) {
                $chunk_size = absint( $request->get_param( 'chunk_size' ) );
                if ( $chunk_size < 100 ) {
                    $chunk_size = 100;
                } elseif ( $chunk_size > 100000 ) {
                    $chunk_size = 100000;
                }
            }
            if ( null !== $request->get_param( 'chunk' ) ) {
                $chunk = absint( $request->get_param( 'chunk' ) );
            }
            $fmt = strtolower( (string) $request->get_param( 'format' ) );
            if ( 'xml' === $fmt ) {
                $format = 'xml';
            }
            $download = ! empty( $request->get_param( 'download' ) );
        }

        $use_chunks = ( null !== $chunk && null !== $chunk_size );

        // Safety: refuse an unbounded full-feed request on large catalogs
        // so the request doesn't blow through the PHP memory limit.
        if ( ! $use_chunks ) {
            $total = self::get_total_count();
            if ( $total > self::FULL_FEED_HARD_LIMIT ) {
                return new \WP_Error(
                    'pulseem_feed_too_large',
                    sprintf(
                        /* translators: 1: total products, 2: hard limit */
                        __( 'The product catalog has %1$s items, which exceeds the full-feed safety limit of %2$s. Please request the feed in chunks using ?chunk=N&chunk_size=M, or download the ZIP.', 'pulseem' ),
                        number_format_i18n( $total ),
                        number_format_i18n( self::FULL_FEED_HARD_LIMIT )
                    ),
                    [ 'status' => 413 ]
                );
            }
        }

        // Expensive responses: raise memory + time limits just for this request.
        self::raise_runtime_limits();

        $this->get_products( $use_chunks ? $chunk : null, $use_chunks ? $chunk_size : null );

        $filename = self::build_filename( $format, $use_chunks ? $chunk : null );

        // XML must bypass WP_REST_Response since that always JSON-encodes the body.
        // Download also needs a Content-Disposition header that the REST server does not set
        // for plain JSON responses, so handle it the same way.
        if ( 'xml' === $format || $download ) {
            $body = ( 'xml' === $format )
                ? $this->build_products_xml( $this->products )
                : wp_json_encode( $this->products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

            nocache_headers();
            status_header( 200 );
            header( 'Content-Type: ' . ( 'xml' === $format ? 'application/xml' : 'application/json' ) . '; charset=utf-8' );
            if ( $download ) {
                header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            }
            // Output and halt before the REST server wraps the response.
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $body is either JSON from wp_json_encode or XML with htmlspecialchars applied per-field in build_products_xml.
            echo $body;
            exit;
        }

        return new \WP_REST_Response( $this->products, 200 );
    }

    /**
     * Build a suggested download filename for a feed response.
     *
     * @param string   $format json|xml
     * @param int|null $chunk  zero-based chunk index, or null for full feed
     * @return string
     */
    public static function build_filename( $format, $chunk = null ) {
        $slug = sanitize_title( wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'pulseem' );
        $ext  = ( 'xml' === $format ) ? 'xml' : 'json';
        if ( null === $chunk ) {
            return sprintf( 'pulseem-products-%s-full.%s', $slug, $ext );
        }
        return sprintf( 'pulseem-products-%s-chunk-%d.%s', $slug, (int) $chunk + 1, $ext );
    }

    /**
     * Fetch WooCommerce products and variations, optionally windowed by chunk.
     *
     * @param int|null $chunk      Zero-based chunk index.
     * @param int|null $chunk_size Rows per chunk; omit (with $chunk) for "all".
     * @return void
     */
    public function get_products( $chunk = null, $chunk_size = null ) {
        $this->products = [];
        self::$parent_terms_cache     = [];
        self::$parent_permalink_cache = [];

        global $wpdb;

        $limit_sql = '';
        if ( null !== $chunk && null !== $chunk_size && $chunk_size > 0 ) {
            $limit_sql = $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $chunk_size, (int) $chunk * (int) $chunk_size );
        }

        // Pull ONLY IDs in one cheap query — avoid WP_Query's bulk meta prime,
        // which on catalogs of 100K+ rows balloons memory before we even begin.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $limit_sql is built above via $wpdb->prepare().
        $ids = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type IN ('product','product_variation')
               AND post_status = 'publish'
             ORDER BY ID ASC" . $limit_sql
        );

        if ( empty( $ids ) ) {
            return;
        }

        // Iterate one product at a time; after formatting, drop its caches so
        // that post/term/meta caches don't accumulate across the loop.
        foreach ( $ids as $product_id ) {
            $product = wc_get_product( (int) $product_id );
            if ( ! $product ) {
                continue;
            }

            $this->products[] = $this->format_product( $product );

            // Free caches for this product so memory stays bounded across large chunks.
            wp_cache_delete( $product_id, 'posts' );
            wp_cache_delete( $product_id, 'post_meta' );
            unset( $product );
        }
    }

    /**
     * Total count of published products + variations. 1-hour transient cache.
     * Variations count as products (Pulseem treats each variation as its own product).
     *
     * @return int
     */
    public static function get_total_count() {
        $cached = get_transient( 'pulseem_product_total_count' );
        if ( false !== $cached ) {
            return (int) $cached;
        }

        $product_counts   = wp_count_posts( 'product' );
        $variation_counts = wp_count_posts( 'product_variation' );
        $total = (int) ( $product_counts->publish ?? 0 ) + (int) ( $variation_counts->publish ?? 0 );

        set_transient( 'pulseem_product_total_count', $total, HOUR_IN_SECONDS );
        return $total;
    }

    /**
     * Invalidate the cached product count.
     *
     * @return void
     */
    public static function invalidate_total_count() {
        delete_transient( 'pulseem_product_total_count' );
    }

    /**
     * Return the current product-feed token, generating one if it is missing.
     * The token is stored inside the pulseem_settings option.
     *
     * @return string
     */
    public static function get_or_create_token() {
        $options = get_option( 'pulseem_settings', [] );
        if ( empty( $options['product_sync_token'] ) ) {
            $options['product_sync_token'] = self::generate_token();
            update_option( 'pulseem_settings', $options );
        }
        return (string) $options['product_sync_token'];
    }

    /**
     * Rotate the product-feed token to a fresh random value.
     *
     * @return string The new token.
     */
    public static function regenerate_token() {
        $options = get_option( 'pulseem_settings', [] );
        $options['product_sync_token'] = self::generate_token();
        update_option( 'pulseem_settings', $options );
        return $options['product_sync_token'];
    }

    /**
     * Produce a random, URL-safe token.
     *
     * @return string
     */
    private static function generate_token() {
        // 32 hex chars = 128 bits of entropy — plenty for a public feed.
        return wp_generate_password( 32, false, false );
    }

    /**
     * Return the last fetched products buffer (populated by get_products()).
     *
     * @return array
     */
    public function get_buffer() {
        return $this->products;
    }

    /**
     * Public wrapper around build_products_xml for callers that already hold an array.
     *
     * @param array $products
     * @return string
     */
    public function build_products_xml_public( array $products ) {
        return $this->build_products_xml( $products );
    }

    /**
     * Build an XML document matching the JSON field shape.
     *
     * @param array $products
     * @return string
     */
    private function build_products_xml( array $products ) {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<products>\n";
        foreach ( $products as $p ) {
            $out .= "  <product>\n";
            foreach ( $p as $key => $value ) {
                if ( is_bool( $value ) ) {
                    $value = $value ? 'true' : 'false';
                } elseif ( null === $value ) {
                    $value = '';
                }
                $tag = preg_replace( '/[^a-zA-Z0-9_\-]/', '_', (string) $key );
                $out .= '    <' . $tag . '>'
                    . htmlspecialchars( (string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' )
                    . '</' . $tag . ">\n";
            }
            $out .= "  </product>\n";
        }
        $out .= '</products>';
        return $out;
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
        $is_variation = $product->is_type('variation');
        $owner_id     = $is_variation ? $product->get_parent_id() : $product->get_id();

        // Memoize parent-level lookups so all variations of the same parent
        // only pay these DB costs once per request.
        if ( ! isset( self::$parent_terms_cache[ $owner_id ] ) ) {
            $names = wp_get_post_terms( $owner_id, 'product_cat', [ 'fields' => 'names' ] );
            $ids   = wp_get_post_terms( $owner_id, 'product_cat', [ 'fields' => 'ids' ] );
            self::$parent_terms_cache[ $owner_id ] = [
                'names' => is_wp_error( $names ) ? '' : implode( ', ', $names ),
                'ids'   => is_wp_error( $ids )   ? '' : implode( ', ', $ids ),
            ];
        }
        if ( ! isset( self::$parent_permalink_cache[ $owner_id ] ) ) {
            self::$parent_permalink_cache[ $owner_id ] = (string) get_permalink( $owner_id );
        }

        $image_id   = $product->get_image_id();
        $image_url  = $image_id ? (string) wp_get_attachment_url( $image_id ) : '';

        // Note: the push-sync path (pulseem_send_product_data) skips products
        // with empty price; the feed intentionally does NOT skip — this is a
        // listing endpoint, callers reconcile on their side.
        $formatted_product = [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'description' => $product->get_short_description(),
            'hrefUrl' => self::$parent_permalink_cache[ $owner_id ],
            'productCategoryNames' => self::$parent_terms_cache[ $owner_id ]['names'],
            'productCategoryIDs' => self::$parent_terms_cache[ $owner_id ]['ids'],
            'imagesURL' => $image_url,
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'is_variation' => $is_variation,
            'last_modified' => get_post_modified_time('Y-m-d H:i:s', false, $product->get_id(), true),
            'stock_management' => $product->managing_stock(),
            'stock_quantity' => $product->managing_stock() ? $product->get_stock_quantity() : 'N/A'
        ];

        // Include parent product ID if it's a variation
        if ($is_variation) {
            $formatted_product['parent_id'] = $owner_id;
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
        'permission_callback' => 'pulseem_product_sync_permission_check',
        'args' => [
            'chunk' => [
                'required'          => false,
                'sanitize_callback' => 'absint',
            ],
            'chunk_size' => [
                'required'          => false,
                'sanitize_callback' => 'absint',
            ],
            'format' => [
                'required'          => false,
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => function( $v ) {
                    return in_array( $v, [ 'json', 'xml' ], true );
                },
            ],
            'token' => [
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'download' => [
                'required'          => false,
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);

    register_rest_route('pulseem/v1', '/get-products-zip', [
        'methods' => 'GET',
        'callback' => 'pulseem_generate_products_zip_callback',
        'permission_callback' => 'pulseem_product_sync_permission_check',
        'args' => [
            'chunk_size' => [
                'required'          => false,
                'sanitize_callback' => 'absint',
            ],
            'format' => [
                'required'          => false,
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => function( $v ) {
                    return in_array( $v, [ 'json', 'xml' ], true );
                },
            ],
            'token' => [
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
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
function pulseem_generate_products_json_callback( $request = null ) {
    $product_sync = new PulseemProductSync();
    return $product_sync->generate_products_json( $request );
}

/**
 * Build a ZIP containing one file per chunk (in the requested format) and stream it.
 * Chunk size falls back to the value saved in pulseem_settings, then to 10,000.
 *
 * @param \WP_REST_Request $request
 * @return \WP_Error|void  Echoes + exits on success.
 */
function pulseem_generate_products_zip_callback( $request ) {
    if ( ! class_exists( 'ZipArchive' ) ) {
        return new \WP_Error(
            'pulseem_no_zip_extension',
            __( 'ZipArchive PHP extension is not installed. Please ask your hosting provider to enable the php-zip extension.', 'pulseem' ),
            [ 'status' => 501 ]
        );
    }

    $format = strtolower( (string) $request->get_param( 'format' ) );
    if ( 'xml' !== $format ) {
        $format = 'json';
    }

    $options    = get_option( 'pulseem_settings', [] );
    $chunk_size = null !== $request->get_param( 'chunk_size' )
        ? absint( $request->get_param( 'chunk_size' ) )
        : ( isset( $options['product_sync_chunk_size'] ) ? (int) $options['product_sync_chunk_size'] : 10000 );
    if ( $chunk_size < 100 ) {
        $chunk_size = 10000;
    } elseif ( $chunk_size > 100000 ) {
        $chunk_size = 100000;
    }

    PulseemProductSync::raise_runtime_limits();

    $total_chunks = max( 1, (int) ceil( PulseemProductSync::get_total_count() / $chunk_size ) );

    $tmp = wp_tempnam( 'pulseem-products-' );
    if ( ! $tmp ) {
        return new \WP_Error( 'pulseem_tmp_failed', __( 'Could not create temp file for ZIP.', 'pulseem' ), [ 'status' => 500 ] );
    }

    $zip = new \ZipArchive();
    if ( true !== $zip->open( $tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
        @unlink( $tmp );
        return new \WP_Error( 'pulseem_zip_open_failed', __( 'Could not open ZIP archive for writing.', 'pulseem' ), [ 'status' => 500 ] );
    }

    $sync = new PulseemProductSync();
    for ( $i = 0; $i < $total_chunks; $i++ ) {
        $sync->get_products( $i, $chunk_size );
        $body = ( 'xml' === $format )
            ? $sync->build_products_xml_public( $sync->get_buffer() )
            : wp_json_encode( $sync->get_buffer(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        $entry = PulseemProductSync::build_filename( $format, $i );
        $zip->addFromString( $entry, $body );
    }
    $zip->close();

    $slug     = sanitize_title( wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'pulseem' );
    $filename = sprintf( 'pulseem-products-%s-%s.zip', $slug, $format );

    nocache_headers();
    status_header( 200 );
    header( 'Content-Type: application/zip' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . filesize( $tmp ) );
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile -- Streaming a temp file we just created; WP_Filesystem would buffer the entire content in memory.
    readfile( $tmp );
    @unlink( $tmp );
    exit;
}

/**
 * Permission gate for the product feed endpoint.
 * Allows either a logged-in user with `manage_woocommerce`, or a request
 * carrying the stored product-sync token so Pulseem can fetch the feed
 * from outside WordPress without exposing it to the general public.
 *
 * @param \WP_REST_Request $request
 * @return bool
 */
function pulseem_product_sync_permission_check( $request ) {
    if ( current_user_can( 'manage_woocommerce' ) ) {
        return true;
    }

    $provided = '';
    if ( $request instanceof \WP_REST_Request ) {
        $provided = (string) $request->get_param( 'token' );
    }
    if ( '' === $provided ) {
        return false;
    }

    $options = get_option( 'pulseem_settings', [] );
    $stored  = isset( $options['product_sync_token'] ) ? (string) $options['product_sync_token'] : '';
    if ( '' === $stored ) {
        return false;
    }

    return hash_equals( $stored, $provided );
}

// AJAX: rotate the product-sync token from the admin UI.
add_action( 'wp_ajax_pulseem_regenerate_product_sync_token', function() {
    if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'pulseem_product_sync_nonce', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
    }
    $token = PulseemProductSync::regenerate_token();
    wp_send_json_success( [ 'token' => $token ] );
} );

// Invalidate cached product count when products/variations change.
add_action( 'save_post_product',           [ 'PulseemProductSync', 'invalidate_total_count' ] );
add_action( 'save_post_product_variation', [ 'PulseemProductSync', 'invalidate_total_count' ] );
add_action( 'deleted_post',                [ 'PulseemProductSync', 'invalidate_total_count' ] );
add_action( 'trashed_post',                [ 'PulseemProductSync', 'invalidate_total_count' ] );
add_action( 'untrashed_post',              [ 'PulseemProductSync', 'invalidate_total_count' ] );

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