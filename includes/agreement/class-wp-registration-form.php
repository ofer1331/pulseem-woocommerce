<?php

/**
* WordPress Registration Form Handler
* 
* Manages the core WordPress registration form integration including:
* - Adding consent checkbox to the default WordPress registration form
* - Registration error validation handling
* - Saving user agreement status for WordPress registrations
* Specifically handles native WordPress registration as opposed to WooCommerce registration.
*
* @since      1.0.0
* @version    1.0.0
*/

namespace pulseem;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WpRegistrationForm {

	private $pulseem_admin_model;

	/**
	 * WpRegistrationForm constructor.
	 */
	public function __construct() {
		$this->pulseem_admin_model = new WooPulseemAdminModel();
		if($this->pulseem_admin_model->getIsEnableUserRegisterAgreement()){
			add_action( 'register_form', [$this, 'add_agreement_field'] );
			add_filter( 'registration_errors', [$this, 'registration_error'], 10, 3 );
			add_action( 'user_register', [$this, 'save_agreement_field'], 10, 1 );
		}
	}


	public function add_agreement_field(){
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress verifies the nonce before rendering the registration form.
		$registration_agreement = ! empty( $_POST['pulseem_user_registration_agreement'] ) ? 1 : 0;
	?>
		<p>
			<label for="user_registration_agreement_id">
				<input type="checkbox" name="pulseem_user_registration_agreement" id="user_registration_agreement_id" <?php checked(1, $registration_agreement)?> value="1">
				<?php echo esc_html($this->pulseem_admin_model->getUserRegisterAgreementText()); ?>
			</label>
		</p>
	<?php
	}


	public function registration_error($errors, $sanitized_user_login, $user_email){
		/**
		 * Body of error method
		 */
		return $errors;
	}


	public function save_agreement_field($user_id){
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress verifies the nonce before triggering the user_register hook.
		$registration_agreement = ! empty( $_POST['pulseem_user_registration_agreement'] ) ? 1 : 0;
		do_action('pulseem-wp-registration-form-save', $registration_agreement);
		UserModel::update_user_pulseem_registration_agreement($user_id, $registration_agreement);
	}
}
