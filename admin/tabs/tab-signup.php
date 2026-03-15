<?php
/**
 * Customer Signup tab content.
 *
 * @var object $pulseem_admin_model
 * @var array  $groups_list
 * @var object $this (WooPulseemAdminController)
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div x-show="activeTab === 'signup'" class="bg-white shadow-sm rounded-lg border">
    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
        <h2 class="text-xl font-semibold text-gray-800"><?php esc_html_e('New Customer Signup Settings', 'pulseem'); ?></h2>
    </div>
    <div class="p-6 space-y-6">
        <!-- Enable Customer Signup -->
        <div class="flex flex-col gap-4 p-4 bg-gray-50 rounded-lg">
            <div class="flex items-center">
                <input
                    type="checkbox"
                    name="pulseem_settings[is_user_register]"
                    id="is_user_register"
                    value="1"
                    <?php checked(1, $pulseem_admin_model->getIsUserRegister()) ?>
                    class="h-4 w-4 text-pink-600 focus:ring-pink-500 rounded"
                >
                <label class="ms-3" for="is_user_register">
                    <span class="block text-sm font-medium text-gray-900">
                        <?php esc_html_e('Enable Customer Signup Feature', 'pulseem'); ?>
                    </span>
                    <span class="block text-sm text-gray-500">
                        <?php esc_html_e('Allow new customers to be automatically inserted.', 'pulseem'); ?>
                    </span>
                </label>
            </div>
            <?php
            $checkbox_name = 'pulseem_settings[is_user_register_pending]';
            $checkbox_id = 'is_user_register_pending';
            $checked_value = $pulseem_admin_model->getIsUserRegisterPending();
            $warning_message = __('Recipients in "Pending" will be receiving an Email asking them to Opt-In Until they Opt In, they will not receive any Emails or SMS messages.', 'pulseem');
            include dirname(__DIR__) . '/partials/pending-status.php';
            ?>
        </div>

        <!-- Agreement Settings -->
        <div class="border rounded-lg bg-white">
            <div class="p-4 border-b bg-gray-50">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900"><?php esc_html_e('Agreement Settings', 'pulseem'); ?></h3>
                    <div class="header-toggle">
                        <label class="switch">
                            <input
                                type="checkbox"
                                name="pulseem_settings[is_enable_user_register_agreement]"
                                id="is_enable_user_register_agreement"
                                value="1"
                                <?php checked(1, $pulseem_admin_model->getIsEnableUserRegisterAgreement()) ?>
                            >
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="p-4">
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-black mb-2">
                            <?php esc_html_e('Add a checkbox to your signup/checkout form to collect customer consent.', 'pulseem'); ?>
                        </p>
                        <label class="block text-sm font-medium text-gray-700" for="user_register_agreement_text">
                            <?php esc_html_e('Agreement Text', 'pulseem'); ?>
                        </label>
                        <input
                            type="text"
                            id="user_register_agreement_text"
                            name="pulseem_settings[user_register_agreement_text]"
                            value="<?php echo esc_attr($pulseem_admin_model->getUserRegisterAgreementText()); ?>"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm"
                        >
                    </div>

                    <?php
                    $message = __('Only customers who check the box will be inserted as "Active".', 'pulseem');
                    include dirname(__DIR__) . '/partials/alert-warning.php';
                    ?>
                </div>
            </div>
        </div>

        <!-- Group Assignment -->
        <div class="border rounded-lg bg-white">
            <div class="p-4 border-b bg-gray-50">
                <h3 class="text-lg font-medium text-gray-900"><?php esc_html_e('Group Assignment', 'pulseem'); ?></h3>
            </div>
            <div class="p-4">
                <?php
                $this->selectmultiple(
                    $groups_list,
                    $pulseem_admin_model->getUserRegisterGroupId(),
                    "pulseem_settings[user_register_group_id][]",
                    "id",
                    "name",
                    ["select2 w-full rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm"]
                )
                ?>
            </div>
        </div>
    </div>
</div>
