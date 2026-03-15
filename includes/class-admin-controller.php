<?php
/**
* Admin Panel Controller
*
* Manages the WordPress admin interface for Pulseem integration including:
* - Plugin settings page setup and initialization
* - Groups management interface
* - API key validation and configuration
* - Settings form rendering and processing
* - Custom select field handlers for single and multiple selections
* Provides the primary administrative interface for the plugin.
*
* @since      1.0.0
* @version    1.4.2
*/

namespace pulseem;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use pulseem\PulseemGroups;

class WooPulseemAdminController {

	/**
	 * WooPulseemAdmin constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array($this,'plugin_settings_init') );
		add_action( 'admin_menu', array($this,'add_plugin_settings_page') );

		// AJAX handlers for logs
		add_action( 'wp_ajax_pulseem_export_logs_csv', array($this, 'ajax_export_logs_csv') );
		add_action( 'wp_ajax_pulseem_export_logs_json', array($this, 'ajax_export_logs_json') );
		add_action( 'wp_ajax_pulseem_bulk_delete_logs', array($this, 'ajax_bulk_delete_logs') );
		add_action( 'wp_ajax_pulseem_save_log_settings', array($this, 'ajax_save_log_settings') );
	}

	public function set_default_lang_list($lang_list){
	    return [
            'default' => 'Default language'
        ];
    }

	/**
	 * Settings sanitization callback
	 */
	public function sanitize_settings($input) {
		$sanitized = array();

		if (is_array($input)) {
			foreach ($input as $key => $value) {
				if (is_array($value)) {
					$sanitized[sanitize_key($key)] = array_map('sanitize_text_field', $value);
				} else {
					$sanitized[sanitize_key($key)] = sanitize_text_field($value);
				}
			}
		}

		\pulseem\PulseemLogger::info(
			\pulseem\PulseemLogger::CONTEXT_SETTINGS,
			'Settings updated',
			['keys' => array_keys($sanitized)]
		);

		return $sanitized;
	}

	/**
	 *
	 */
	public function plugin_settings_init(){
		register_setting( 'pulseem_settings', 'pulseem_settings', array(
			'sanitize_callback' => array($this, 'sanitize_settings')
		));
	}

	/**
	 *
	 */
	public function add_plugin_settings_page() {
		add_menu_page(
			__( 'Pulseem', 'pulseem' ),
			__( 'Pulseem', 'pulseem' ),
			'manage_options',
			'pulseem_settings',
			array($this,'plugin_settings_template'),
			PULSEEM_ASSETS_URI.'pulseem.png',
			80);

		// Add Logs submenu
		add_submenu_page(
			'pulseem_settings',
			__( 'Logs', 'pulseem' ),
			__( 'Logs', 'pulseem' ),
			'manage_options',
			'pulseem_logs',
			array($this, 'plugin_logs_template')
		);
	}

	/**
	 * Render logs page template
	 */
	public function plugin_logs_template() {
		// Ensure the logs table exists
		\pulseem\PulseemLogger::create_table();

		wp_enqueue_style(
			'pulseem-logs-css',
			PULSEEM_ASSETS_URI . 'style/pulseem-logs.css',
			array(),
			'1.4.2'
		);

		require_once PULSEEM_DIR . '/admin/logs.php';
	}

	/**
	 *
	 */
	public function plugin_settings_template() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'pulseem' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading $_GET['settings-updated'] for admin notice display, no data processing.
		$settings_updated = isset( $_GET['settings-updated'] ) ? sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) : false;
	
		$pulseem_admin_model = new WooPulseemAdminModel();
		$groups_list = [];
		
		// Get API key and API URL dynamically
		$apikey = $pulseem_admin_model->getApiKey();
		$url = $pulseem_admin_model->getEnvironmentUrl() . "/api/v1/GroupsApi/GetAllGroups";
	
		if (!empty($apikey)) {
			// Use WordPress HTTP API instead of cURL
			$body = wp_json_encode(["groupName" => "", "groupType" => "Groups"]);
			$args = array(
				'body'        => $body,
				'headers'     => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json-patch+json',
					'apiKey'       => $apikey
				),
				'method'      => 'POST',
				'timeout'     => 60,
				'redirection' => 5,
			);

			$response = wp_remote_post($url, $args);

			// Handle WordPress HTTP API errors
			if (is_wp_error($response)) {
				\pulseem\PulseemLogger::error(
					\pulseem\PulseemLogger::CONTEXT_SETTINGS,
					'HTTP error fetching groups: ' . $response->get_error_message()
				);
				add_settings_error(
					'pulseem_settings',
					'http_error',
					/* translators: %s: error message from HTTP request */
					sprintf( __( 'HTTP Error: %s', 'pulseem' ), $response->get_error_message() ),
					'error'
				);
			} else {
				$http_code = wp_remote_retrieve_response_code($response);
				$body = wp_remote_retrieve_body($response);

				// Handle API key authentication errors
				if ($http_code == 403) {
					\pulseem\PulseemLogger::error(
						\pulseem\PulseemLogger::CONTEXT_SETTINGS,
						'Invalid API key (403) when fetching groups'
					);
					add_settings_error(
						'pulseem_settings',
						'api_key_invalid',
						__( 'Invalid API key! Please check your credentials and try again.', 'pulseem' ),
						'error'
					);
				}
				// Handle unexpected HTTP status codes
				elseif ($http_code !== 200) {
					add_settings_error(
						'pulseem_settings',
						'unexpected_http_code',
						/* translators: %s: HTTP status code */
						sprintf( __( 'Unexpected HTTP Code: %s', 'pulseem' ), $http_code ),
						'error'
					);
				} 
				else {
					$groups_list1 = json_decode($body, true);
		
					// Validate JSON response
					if (!is_array($groups_list1) || !isset($groups_list1['groups']) || !is_array($groups_list1['groups'])) {
						add_settings_error(
							'pulseem_settings',
							'invalid_json',
							__( 'Failed to fetch groups. The API response might be invalid.', 'pulseem' ),
							'error'
						);
					} else {
						// Process group list
						foreach ($groups_list1['groups'] as $group) {
							$groups_list[] = [
								'name' => sanitize_text_field($group['groupName']),
								'id'   => sanitize_text_field($group['groupId'])
							];
						}
		
						\pulseem\PulseemLogger::debug(
							\pulseem\PulseemLogger::CONTEXT_SETTINGS,
							'Groups fetched',
							['count' => count($groups_list)]
						);

						// Handle case when no groups are found
						if (empty($groups_list)) {
							add_settings_error(
								'pulseem_settings',
								'no_groups_found',
								__( 'No groups found for the provided API key. Please verify your account settings.', 'pulseem' ),
								'error'
							);
						}
					}
				}
			}
		} else {
			add_settings_error(
				'pulseem_settings',
				'missing_api_key',
				__( 'API key is missing. Please enter your API key in the settings.', 'pulseem' ),
				'error'
			);
		}
	
		// Load the settings template
		require_once PULSEEM_DIR . '/admin/settings.php'; 
	}
	
	/**
	 * AJAX: Export logs as CSV
	 */
	public function ajax_export_logs_csv() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) ), 'pulseem_logs_nonce' ) ) {
			wp_die( 'Unauthorized', 403 );
		}

		$args = $this->get_export_filter_args();
		\pulseem\PulseemLogger::export_csv( $args );
		exit;
	}

	/**
	 * AJAX: Export logs as JSON
	 */
	public function ajax_export_logs_json() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) ), 'pulseem_logs_nonce' ) ) {
			wp_die( 'Unauthorized', 403 );
		}

		$args = $this->get_export_filter_args();
		\pulseem\PulseemLogger::export_json( $args );
		exit;
	}

	/**
	 * AJAX: Bulk delete logs
	 */
	public function ajax_bulk_delete_logs() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'pulseem_logs_nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['ids'] ) ) ) : [];
		$deleted = \pulseem\PulseemLogger::delete_logs_by_ids( $ids );
		wp_send_json_success( [ 'deleted' => $deleted ] );
	}

	/**
	 * AJAX: Save log settings
	 */
	public function ajax_save_log_settings() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'pulseem_logs_nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$options = get_option( 'pulseem_settings', [] );
		if ( isset( $_POST['log_level'] ) ) {
			$allowed_levels = [ 'debug', 'info', 'warning', 'error' ];
			$level = sanitize_text_field( wp_unslash( $_POST['log_level'] ) );
			if ( in_array( $level, $allowed_levels, true ) ) {
				$options['log_level'] = $level;
			}
		}
		if ( isset( $_POST['log_retention_days'] ) ) {
			$days = absint( wp_unslash( $_POST['log_retention_days'] ) );
			$options['log_retention_days'] = $days;
		}

		update_option( 'pulseem_settings', $options );
		wp_send_json_success( [ 'message' => 'Settings saved' ] );
	}

	/**
	 * Get filter args from GET params for export
	 *
	 * @return array
	 */
	private function get_export_filter_args() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading $_GET filter params for log export; nonce is verified in the calling export handler.
		return [
			'level'     => isset( $_GET['level'] ) ? sanitize_text_field( wp_unslash( $_GET['level'] ) ) : '',
			'context'   => isset( $_GET['context'] ) ? sanitize_text_field( wp_unslash( $_GET['context'] ) ) : '',
			'email'     => isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '',
			'search'    => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
		];
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * @param $select_array
	 * @param $value
	 * @param $name
	 * @param $key_opt_val
	 * @param $key_opt_html
	 * @param array $class
	 */
	public function select($select_array, $value, $name, $key_opt_val, $key_opt_html, $class=[]){
	    $id = esc_attr($name) . "-id";
    ?>
        <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr(implode(' ', $class)); ?>">
            <option value="-1"><?php esc_html_e('Not selected', 'pulseem'); ?></option>
            <?php if($select_array): ?>
                <?php foreach ($select_array as $item): ?>
                    <option value="<?php echo esc_attr($item[$key_opt_val]); ?>" <?php selected($item[$key_opt_val], $value) ?>>
                        <?php echo esc_html($item[$key_opt_html]); ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    <?php
    }

	
	/**
	 * @param $select_array
	 * @param $value
	 * @param $name
	 * @param $key_opt_val
	 * @param $key_opt_html
	 * @param array $class
	 */
	public function selectmultiple($select_array, $value, $name, $key_opt_val, $key_opt_html, $class=[]){
    ?>
        <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name . "-id"); ?>" class="<?php echo esc_attr(implode(' ', $class)); ?>" multiple="multiple">
            <option value="-1"><?php esc_html_e('Not selected', 'pulseem'); ?></option>
			
            <?php if($select_array): ?>
                <?php foreach ($select_array as $item): ?>
			     	
					<?php 
			
							foreach($value as $group_id){			
							if ($item[$key_opt_val] == $group_id) {		
								$selected = 'selected';
								?><option value="<?php echo esc_attr($item[$key_opt_val]); ?>" <?php echo esc_attr($selected); ?>><?php echo esc_html($item[$key_opt_html]); ?></option><?php
							}
						}	
					?>
                  <option value="<?php echo esc_attr($item[$key_opt_val]); ?>"><?php echo esc_html($item[$key_opt_html]); ?></option> 
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    <?php
    }
}