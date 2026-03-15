<?php
/**
 * Checkout Form Handler
 * 
 * Manages the checkout form functionality for Pulseem integration including:
 * - Adding consent checkbox to checkout form
 * - Validating customer agreement (optional)
 * - Saving agreement status with order
 * Handles all frontend checkout form modifications and related data processing.
 *
 * @since 1.0.0
 * @version 1.0.0
 */

namespace pulseem;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WooCheckoutForm {

    /**
     * Pulseem admin model instance
     *
     * @var WooPulseemAdminModel
     * @since 1.0.0
     * @version 1.0.0
     */
    private $pulseem_admin_model;

    /**
     * WooCheckoutForm constructor
     *
     * Initializes the checkout form functionalities.
     * Adds hooks for adding, validating, and saving the agreement checkbox.
     *
     * @since 1.0.0
     * @version 1.0.0
     */
    public function __construct() {
        $this->pulseem_admin_model = new WooPulseemAdminModel();
        if ($this->pulseem_admin_model->getIsEnableCheckoutAgreement()) {

            // Hook to add the agreement field to the checkout form
            add_action('woocommerce_review_order_before_submit', [$this, 'add_agreement_field']);

            // Hook to enqueue agreement styles
            add_action('wp_enqueue_scripts', [$this, 'enqueue_agreement_styles']);

            // Hook to save the agreement field's value in the order meta
            add_action('woocommerce_checkout_update_order_meta', [$this, 'save_agreement_field']);

            // Hook to validate the agreement field (currently optional)
            add_action('woocommerce_checkout_process', [$this, 'validate_agreement_field']);
        }
    }

    /**
     * Add agreement field to the checkout form
     *
     * Adds a checkbox for the user to agree to terms.
     * Adjusts layout for RTL if the site is RTL-enabled.
     *
     * @since 1.0.0
     * @version 1.0.0
     */
    /**
     * Enqueue agreement checkbox styles
     *
     * @since 1.4.2
     */
    public function enqueue_agreement_styles() {
        if ( is_checkout() ) {
            // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Virtual handle for wp_add_inline_style only, no external resource to version.
            wp_register_style( 'pulseem-checkout-agreement', false );
            wp_enqueue_style( 'pulseem-checkout-agreement' );
            wp_add_inline_style( 'pulseem-checkout-agreement',
                '.form-row.terms-agreement.rtl-agreement { direction: rtl; text-align: right; width: 100%; }' .
                '.form-row.terms-agreement.ltr-agreement { direction: ltr; text-align: left; width: 100%; }'
            );
        }
    }

    public function add_agreement_field() {
        $is_rtl = is_rtl();
        $rtl_class = $is_rtl ? 'rtl-agreement' : 'ltr-agreement';
        ?>
        <p class="form-row terms-agreement <?php echo esc_attr($rtl_class); ?>">
            <label for="pulseem_checkout_agreement" class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="pulseem_checkout_agreement" id="pulseem_checkout_agreement" />
                <span><?php echo esc_html($this->pulseem_admin_model->getCheckoutAgreementText()); ?></span>
            </label>
        </p>
        <?php
    }

    /**
     * Validate agreement field
     *
     * Checks if the user has agreed to the terms.
     * Since the field is optional, no error is triggered.
     *
     * @since 1.0.0
     * @version 1.0.0
     */
    public function validate_agreement_field() {
        // No validation needed since the field is optional
    }

    /**
     * Save agreement field
     *
     * Saves the value of the agreement checkbox (1 if checked, 0 otherwise) to the order meta.
     * Triggers a custom action for additional handling if needed.
     *
     * @param int $order_id The ID of the order being processed
     * @since 1.0.0
     * @version 1.0.0
     */
    public function save_agreement_field($order_id) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Runs inside 'woocommerce_checkout_update_order_meta'; nonce verified by WC_Checkout::process_checkout() upstream.
        $registration_agreement = ! empty( $_POST['pulseem_checkout_agreement'] ) ? 1 : 0;

        // Trigger custom action for additional handling
        do_action('pulseem-wp-checkout-form-save', $registration_agreement);

        // Save the agreement status to order meta
        update_post_meta($order_id, 'pulseem_checkout_agreement', $registration_agreement);
    }
}
