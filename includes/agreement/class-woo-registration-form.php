<?php
/**
* Registration Form Handler
* 
* Manages user registration form functionality including:
* - Adding consent checkbox to registration forms
* - Handling consent on both standard registration and checkout registration
* - Saving user agreement status
* - Dynamic form field visibility based on account creation choice
* - JavaScript integration for form interactivity
*
* @since      1.0.0
* @version    1.0.0
*/

namespace pulseem;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WooRegistrationForm {

	private $pulseem_admin_model;

	/**
	 * WooRegistrationForm constructor.
	 */
	public function __construct() {
		$this->pulseem_admin_model = new WooPulseemAdminModel();
		if($this->pulseem_admin_model->getIsEnableUserRegisterAgreement()){

			add_action('woocommerce_register_form', [$this, 'add_agreement_field']);
			
			add_action('woocommerce_checkout_fields', [$this, 'add_checkout_agreement_field']);
			
			add_action('woocommerce_created_customer', [$this, 'save_agreement_field']);

			add_action('wp_footer', [$this, 'add_checkout_script']);
		}
	}

	public function add_agreement_field() {
		// בדוק אם Enable User Signup Feature דלוק
		if (!$this->pulseem_admin_model->getIsUserRegister()) {
			return; // לא להציג את השדה אם לא פעיל
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the nonce before rendering this registration form.
		$registration_agreement = ! empty( $_POST['pulseem_user_registration_agreement'] ) ? 1 : 0;
		?>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="user_registration_agreement_id">
				<input type="checkbox" name="pulseem_user_registration_agreement" id="user_registration_agreement_id" <?php checked(1, $registration_agreement)?> value="1">
				<?php echo esc_html($this->pulseem_admin_model->getUserRegisterAgreementText()); ?>
			</label>
		</p>
		<?php
	}	

	public function add_checkout_agreement_field($fields) {
		$fields['account']['pulseem_user_registration_agreement'] = [
			'type' => 'checkbox',
			'label' => $this->pulseem_admin_model->getUserRegisterAgreementText(),
			'required' => false,
			'class' => ['form-row-wide', 'pulseem-agreement-field'],
		];
		return $fields;
	}

	public function save_agreement_field($user_id){
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the nonce before triggering woocommerce_created_customer.
		$registration_agreement = ! empty( $_POST['pulseem_user_registration_agreement'] ) ? 1 : 0;
		do_action('pulseem-wp-registration-form-save', $registration_agreement);
		UserModel::update_user_pulseem_registration_agreement($user_id, $registration_agreement);
	}

	public function add_checkout_script() {
		wp_enqueue_script(
			'pulseem-agreement',
			PULSEEM_PUBLIC_URI . 'pulseem-agreement.js',
			array(),
			'1.4.2',
			true
		);
	}
}
