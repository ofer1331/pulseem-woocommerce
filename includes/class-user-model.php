<?php
/**
* User Data Model
*
* Manages user-related data and preferences including:
* - User registration agreement status
* - Checkout agreement tracking
* - User meta data storage and retrieval
* Provides centralized user data management functionality.
*
* @since      1.0.0
* @version    1.0.0
*/

namespace pulseem;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


class UserModel {

	const USER_REGISTRATION_AGREEMENT_FIELD = 'pulseem-registration-agreement';
	const USER_CHECKOUT_AGREEMENT_FIELD = 'pulseem-registration-agreement';

	public static function get_user_pulseem_registration_agreement($user_id){
		return get_user_meta($user_id, self::USER_REGISTRATION_AGREEMENT_FIELD, true);
	}

	public static function update_user_pulseem_registration_agreement($user_id, $value){
		return update_user_meta(
			$user_id,
			self::USER_REGISTRATION_AGREEMENT_FIELD,
			$value
		);
	}

	public static function get_user_pulseem_checkout_agreement($user_id){
		return get_user_meta($user_id, self::USER_CHECKOUT_AGREEMENT_FIELD, true);
	}

	public static function update_user_pulseem_checkout_agreement($user_id, $value){
		return update_user_meta(
			$user_id,
			self::USER_CHECKOUT_AGREEMENT_FIELD,
			$value
		);
	}
}
