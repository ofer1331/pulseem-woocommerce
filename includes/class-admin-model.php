<?php
namespace pulseem;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
* Admin Settings Model
* 
* Manages the plugin's administrative settings and configuration including:
* - API credentials and environment settings
* - User registration and purchase group mappings
* - Cart abandonment configuration
* - Agreement text management
* - Product synchronization settings
* Acts as the central data model for all plugin settings.
*
* @since      1.0.0
* @version    1.4.0
*/


class WooPulseemAdminModel {

	private $login;
	private $password;
	private $api_key;
	private $environment_mode;
	private $user_register_group_id;
	private $is_user_register;
	private $user_purchase_group_id;
	private $is_user_purchased;
	private $user_cart_abandoned_group_id;
	private $is_cart_abandoned;
	private $cart_abandoned_interval;

	private $is_enable_user_register_agreement;
	private $user_register_agreement_text;
	private $is_checkout_agreement;
	private $checkout_agreement_text;

	private $cart_abandoned_immidiately;
	private $cart_abandoned_aftertime;
	private $cart_abandoned_aftertime_duration;
	private $cart_abandoned_aftertime_types;

	private $is_product_sync;
	private $product_sync_json_url;
	private $include_pending_payment;

	private $is_page_tracking;
	private $is_woocommerce_page_tracking;

	private $is_purchase_pending;
	private $is_cart_abandoned_pending;
	private $is_user_register_pending;
	private $log_level;
	private $log_retention_days;

	/**
	 * WooPulseemAdminModel constructor.
	 */
	public function __construct() {
		$options = get_option( 'pulseem_settings' );
		$this->login = isset($options['pulseem_login'])?$options['pulseem_login']:'';
		$this->password = isset($options['pulseem_password'])?$options['pulseem_password']:'';
		$this->api_key = isset($options['api_key'])?$options['api_key']:'';
		$this->environment_mode = isset($options['environment_mode'])?$options['environment_mode']:'';
		$this->user_register_group_id = isset($options['user_register_group_id'])?$options['user_register_group_id']:-1;
		$this->user_purchase_group_id = isset($options['user_purchase_group_id'])?$options['user_purchase_group_id']:-1;
		$this->user_cart_abandoned_group_id = isset($options['user_cart_abandoned_group_id'])?$options['user_cart_abandoned_group_id']:-1;
		$this->is_user_register = isset($options['is_user_register'])?$options['is_user_register']:0;
		$this->is_user_purchased = isset($options['is_user_purchased'])?$options['is_user_purchased']:0;
		$this->is_cart_abandoned = isset($options['is_cart_abandoned'])?$options['is_cart_abandoned']:0;
		$this->cart_abandoned_interval = isset($options['cart_abandoned_interval'])?$options['cart_abandoned_interval']:2;
		$this->is_enable_user_register_agreement = isset($options['is_enable_user_register_agreement'])?$options['is_enable_user_register_agreement']:0;
		$this->user_register_agreement_text = isset($options['user_register_agreement_text'])?$options['user_register_agreement_text']:[];
		$this->is_checkout_agreement = isset($options['is_checkout_agreement'])?$options['is_checkout_agreement']:0;
		$this->checkout_agreement_text = isset($options['checkout_agreement_text'])?$options['checkout_agreement_text']:[];
		$this->cart_abandoned_immidiately = isset($options['cart_abandoned_immidiately'])?$options['cart_abandoned_immidiately']:[];
		$this->cart_abandoned_aftertime = isset($options['cart_abandoned_aftertime'])?$options['cart_abandoned_aftertime']:[];
		$this->cart_abandoned_aftertime_duration = isset($options['cart_abandoned_aftertime_duration'])?$options['cart_abandoned_aftertime_duration']:[];
		$this->cart_abandoned_aftertime_types = isset($options['cart_abandoned_aftertime_types'])?$options['cart_abandoned_aftertime_types']:[];
		$this->is_product_sync = isset($options['is_product_sync']) ? $options['is_product_sync'] : 0;
		$this->product_sync_json_url = isset($options['product_sync_json_url']) ? $options['product_sync_json_url'] : '';
		$this->is_page_tracking = isset($options['is_page_tracking']) ? $options['is_page_tracking'] : 0;
		$this->is_woocommerce_page_tracking = isset($options['is_woocommerce_page_tracking']) ? $options['is_woocommerce_page_tracking'] : 0;
		$this->is_purchase_pending = isset($options['is_purchase_pending']) ? $options['is_purchase_pending'] : 0;
		$this->is_cart_abandoned_pending = isset($options['is_cart_abandoned_pending']) ? $options['is_cart_abandoned_pending'] : 0;
	    $this->is_user_register_pending = isset($options['is_user_register_pending']) ? $options['is_user_register_pending'] : 0;	
		$this->include_pending_payment = isset($options['include_pending_payment']) ? $options['include_pending_payment'] : 0;
		$this->log_level = isset($options['log_level']) ? $options['log_level'] : 'debug';
		$this->log_retention_days = isset($options['log_retention_days']) ? (int) $options['log_retention_days'] : 30;
	}

	/**
	 * @return mixed
	 */
	public function getLogin() {
		return $this->login;
	}

	/**
	 * @return mixed
	 */
	public function getPassword() {
		return $this->password;
	}

	/**
	 * @return string
	 */
	public function getApiKey() {
		return $this->api_key;
	}

	public function getEnvironmentMode() {
		return $this->environment_mode;
	}

	public function getEnvironmentUrl() {
		/*
		if($this->environment_mode == 0){	
			$url = 'https://ui-api.pulseemdev.co.il';
		}
		else{
			$url = 'https://ui-api.pulseem.com';
		}
		*/
		$url = 'https://ui-api.pulseem.com';
		return $url;
	}

	/**
	 * @return string
	 */
	public function getUserRegisterGroupId() {
		return $this->user_register_group_id;
	}

	/**
	 * @return int
	 */
	public function getIsUserRegister() {
		return $this->is_user_register;
	}

	/**
	 * @return int
	 */
	public function getIsUserPurchased() {
		return $this->is_user_purchased;
	}

	/**
	 * @return int
	 */
	public function getUserPurchaseGroupId() {
		return $this->user_purchase_group_id;
	}

	/**
	 * @return int
	 */
	public function getUserCartAbandonedGroupId() {
		return $this->user_cart_abandoned_group_id;
	}

	/**
	 * @return int
	 */
	public function getIsCartAbandoned() {
		return $this->is_cart_abandoned;
	}

	/**
	 * @return mixed
	 */
	public function getCartAbandonedInterval() {
		return $this->cart_abandoned_interval;
	}

	
	/**
	 * @return mixed
	 */
	public function getCheckoutAgreementText() {
		$default_text = 'I consent to receiving promotional updates through Email and SMS.';
		return !empty($this->checkout_agreement_text) ? $this->checkout_agreement_text : $default_text;
	}

	/**
	 * @return mixed
	 */
	public function getIsEnableCheckoutAgreement() {
		return $this->is_checkout_agreement;
	}

	/**
	 * @return mixed
	 */
	public function getUserRegisterAgreementText() {
		$default_text = 'I consent to receiving promotional updates through Email and SMS.';
		return !empty($this->user_register_agreement_text) ? $this->user_register_agreement_text : $default_text;
	}

	/**
	 * @return mixed
	 */
	public function getIsEnableUserRegisterAgreement() {
		return $this->is_enable_user_register_agreement;
	}

	/**
	 * @return mixed
	 */
	public function getIsCartAbandonedImmidiately() {
		return $this->cart_abandoned_immidiately;
	}

	/**
	 * @return mixed
	 */
	public function getIsCartAbandonedAftertime() {
		return !empty($this->cart_abandoned_aftertime) && $this->cart_abandoned_aftertime == 1 ? 1 : 0;
	}

	/**
	 * @return mixed
	 */
	public function getUserCartAbandonedAftertimeDuration() {
		return $this->cart_abandoned_aftertime_duration;
	}

	/**
	 * @return mixed
	 */
	public function getUserCartAbandonedAftertimeTypes() {
		return $this->cart_abandoned_aftertime_types;
	}

	public function getIsProductSync() {
		return $this->is_product_sync;
	}

	/**
	 * @return string
	 */
	public function getProductSyncJsonUrl() {
		return $this->product_sync_json_url;
	}

	/**
	 * Getters for page tracking settings
	*/
	public function getIsPageTracking() {
		return $this->is_page_tracking;
	}

	public function getIsWooCommercePageTracking() {
		return $this->is_woocommerce_page_tracking;
	}

	public function getIsPurchasePending() {
		return $this->is_purchase_pending;
	}
	
	public function getIsCartAbandonedPending() {
		return $this->is_cart_abandoned_pending;
	}

	public function getIsUserRegisterPending() {
		return $this->is_user_register_pending;
	}

	public function getIncludePendingPayment() {
		return $this->include_pending_payment;
	}

	public function getLogLevel() {
		return $this->log_level;
	}

	public function getLogRetentionDays() {
		return $this->log_retention_days;
	}
}