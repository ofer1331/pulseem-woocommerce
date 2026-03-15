<?php
/**
* Scripts and Styles Manager
* 
* Handles all frontend and admin script loading including:
* - Frontend AJAX script registration
* - Admin panel script management
* - Script localization and dependencies
* - Asset versioning and caching
* Controls all JavaScript and CSS asset loading for the plugin.
*
* @since      1.0.0
* @version    1.4.0
*/

namespace pulseem;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScriptsController {
	/**
	 * ScriptsController constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', [$this, 'initScripts'] );
		add_action( 'admin_enqueue_scripts', [$this, 'initAdminScripts'] );
	}

	/**
	 * Initialize frontend scripts
	 */
	public function initScripts(){
		wp_enqueue_script( 'pulseem-ajax', PULSEEM_PUBLIC_URI . 'pulseem-ajax.js', array('jquery'), filemtime(PULSEEM_DIR . '/public/pulseem-ajax.js'), true );
		wp_localize_script('pulseem-ajax', 'pulseem_ajax_obj', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('pulseem_checkout_nonce'),
		));
	}

	/**
	 * Initialize admin scripts with nonce for security
	 */
	public function initAdminScripts($hook) {
		// Only load on Pulseem settings page
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: checking admin page slug for conditional asset loading. No form processing or state change.
		if (isset($_GET['page']) && sanitize_text_field(wp_unslash($_GET['page'])) === 'pulseem_settings') {
			// Add nonce for admin AJAX calls
			wp_localize_script('pulseem-select2-js', 'pulseem_ajax', [
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('pulseem_product_sync_nonce')
			]);
		}
	}
}