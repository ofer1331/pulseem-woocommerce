<?php
/**
 * Purchase Tracking tab content.
 *
 * @var object $pulseem_admin_model
 * @var array  $groups_list
 * @var object $this (WooPulseemAdminController)
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div x-show="activeTab === 'purchase'" class="bg-white shadow-sm rounded-lg border">
    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
        <h2 class="text-xl font-semibold text-gray-800"><?php esc_html_e('New Purchase Settings', 'pulseem'); ?></h2>
    </div>
    <div class="p-6 space-y-6">
        <!-- Enable Purchase Tracking -->
        <div class="flex flex-col gap-4 p-4 bg-gray-50 rounded-lg">
            <div class="flex items-center">
                <input
                    type="checkbox"
                    name="pulseem_settings[is_user_purchased]"
                    id="is_user_purchased"
                    value="1"
                    <?php checked(1, $pulseem_admin_model->getIsUserPurchased()) ?>
                    class="h-4 w-4 text-pink-600 focus:ring-pink-500 rounded"
                >
                <label class="ms-3" for="is_user_purchased">
                    <span class="block text-sm font-medium text-gray-900">
                        <?php esc_html_e('Enable Purchase Tracking', 'pulseem'); ?>
                    </span>
                    <span class="block text-sm text-gray-500">
                    <?php esc_html_e('Track customer purchases and sync with Pulseem', 'pulseem'); ?>
                    </span>
                </label>
            </div>
            <?php
            $checkbox_name = 'pulseem_settings[is_purchase_pending]';
            $checkbox_id = 'is_purchase_pending';
            $checked_value = $pulseem_admin_model->getIsPurchasePending();
            $warning_message = __('Recipients in "Pending" will be receiving an Email asking them to Opt-In Until they Opt In, they will not receive any Emails or SMS messages.', 'pulseem');
            include dirname(__DIR__) . '/partials/pending-status.php';
            ?>
        </div>

        <!-- Checkout Agreement -->
        <?php include dirname(__DIR__) . '/partials/checkout-agreement.php'; ?>

        <!-- Purchase Group Assignment -->
        <div class="border rounded-lg bg-white">
            <div class="p-4 border-b bg-gray-50">
                <h3 class="text-lg font-medium text-gray-900"><?php esc_html_e('Group Assignment', 'pulseem'); ?></h3>
            </div>
            <div class="p-4">
                <?php
                $this->selectmultiple(
                    $groups_list,
                    $pulseem_admin_model->getUserPurchaseGroupId(),
                    "pulseem_settings[user_purchase_group_id][]",
                    "id",
                    "name",
                    ["select2 w-full rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm"]
                )
                ?>
            </div>
        </div>
    </div>
</div>
