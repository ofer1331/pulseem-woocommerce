<?php
/**
 * Checkout agreement section partial.
 * Used in both Purchase and Cart Abandonment tabs.
 *
 * @param object $pulseem_admin_model The admin model instance.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="border rounded-lg bg-white">
    <div class="p-4 border-b bg-gray-50">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900"><?php esc_html_e('Checkout Agreement', 'pulseem'); ?></h3>
            <div class="header-toggle">
                <label class="switch">
                    <input
                        type="checkbox"
                        name="pulseem_settings[is_checkout_agreement]"
                        id="is_checkout_agreement"
                        value="1"
                        <?php checked(1, $pulseem_admin_model->getIsEnableCheckoutAgreement()) ?>
                        class="sync-checkbox"
                    >
                    <span class="slider"></span>
                </label>
            </div>
        </div>
    </div>
    <div class="p-4 space-y-4">
        <div>
            <p class="text-sm text-black mb-2">
               <?php esc_html_e('Add a checkbox to your signup/checkout form to collect customer consent.', 'pulseem'); ?>
            </p>
            <label class="block text-sm font-medium text-gray-700" for="checkout_agreement_text">
                <?php esc_html_e('Agreement Text', 'pulseem'); ?>
            </label>
            <input
                type="text"
                data-group="agreement_text"
                id="checkout_agreement_text"
                name="pulseem_settings[checkout_agreement_text]"
                value="<?php echo esc_attr($pulseem_admin_model->getCheckoutAgreementText()); ?>"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm"
            >
        </div>
        <?php
        $message = __('Only customers who check the box will be inserted as "Active". This text field and checkbox are shared between New Purchase and Cart Abandonment settings.  Any changes made to the text or checkbox here will automatically apply to both features.', 'pulseem');
        include __DIR__ . '/alert-warning.php';
        ?>
    </div>
</div>
