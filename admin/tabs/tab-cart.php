<?php
/**
 * Cart Abandonment tab content.
 *
 * @var object $pulseem_admin_model
 * @var array  $groups_list
 * @var array  $options
 * @var object $this (WooPulseemAdminController)
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div x-show="activeTab === 'cart'" class="bg-white shadow-sm rounded-lg border">
    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
        <h2 class="text-xl font-semibold text-gray-800"><?php esc_html_e('Cart Abandonment Settings', 'pulseem'); ?></h2>
    </div>
    <div class="p-6 space-y-6">
        <!-- Enable Cart Abandonment -->
        <div class="flex flex-col gap-4 p-4 bg-gray-50 rounded-lg">
            <div class="flex items-center">
                <input
                    type="checkbox"
                    name="pulseem_settings[is_cart_abandoned]"
                    id="is_cart_abandoned"
                    value="1"
                    <?php checked(1, $pulseem_admin_model->getIsCartAbandoned()) ?>
                    class="h-4 w-4 text-pink-600 focus:ring-pink-500 rounded"
                >
                <label class="ms-3" for="is_cart_abandoned">
                    <span class="block text-sm font-medium text-gray-900">
                        <?php esc_html_e('Enable Cart Abandonment Tracking', 'pulseem'); ?>
                    </span>
                    <span class="block text-sm text-gray-500">
                        <?php esc_html_e('Track abandoned carts and follow up with customers via Pulseem.', 'pulseem'); ?>
                    </span>
                </label>
            </div>

            <?php
            /*
            <!-- Include Pending Payment Orders -->
            <div class="flex items-center ms-4">
                <input
                    type="checkbox"
                    name="pulseem_settings[include_pending_payment]"
                    id="include_pending_payment"
                    value="1"
                    <?php checked(1, $pulseem_admin_model->getIncludePendingPayment()) ?>
                    class="h-4 w-4 text-pink-600 focus:ring-pink-500 rounded"
                >
                <label class="ms-3" for="include_pending_payment">
                    <span class="block text-sm font-medium text-gray-900">
                        <?php esc_html_e('Include "Pending Payment" orders as abandoned carts', 'pulseem'); ?>
                    </span>
                    <span class="block text-sm text-gray-500">
                        <?php esc_html_e('Consider orders with "Pending Payment" status as abandoned carts for follow-up.', 'pulseem'); ?>
                    </span>
                </label>
            </div>
            */
            ?>

            <?php
            $checkbox_name = 'pulseem_settings[is_cart_abandoned_pending]';
            $checkbox_id = 'is_cart_abandoned_pending';
            $checked_value = $pulseem_admin_model->getIsCartAbandonedPending();
            $warning_message = __('Recipients in "Pending" will be receiving an Email asking them to Opt-In Until they Opt In, they will not receive any Emails or SMS messages.   Note: When "Include Pending Payment orders" is enabled, orders that remain in "Pending Payment" status will be treated as abandoned carts after the specified time period.', 'pulseem');
            include dirname(__DIR__) . '/partials/pending-status.php';
            ?>
        </div>

        <?php include dirname(__DIR__) . '/partials/checkout-agreement.php'; ?>

        <!-- Abandonment Time Settings -->
        <div class="border rounded-lg bg-white">
            <div class="p-4 border-b bg-gray-50">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900"><?php esc_html_e('Time Settings', 'pulseem'); ?></h3>
                    <div class="header-toggle">
                        <label class="switch">
                            <input
                                type="checkbox"
                                name="pulseem_settings[cart_abandoned_aftertime]"
                                id="cart_abandoned_aftertime"
                                value="1"
                                <?php checked(1, $pulseem_admin_model->getIsCartAbandonedAftertime()) ?>
                            >
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="p-4">
                <div class="space-y-4">
                    <label class="block text-sm font-medium text-gray-700">
                        <?php esc_html_e('Add customers to group after:', 'pulseem'); ?>
                    </label>
                    <div class="flex items-center gap-2">
                        <select
                            id="pulseem_settings[cart_abandoned_aftertime_duration]-id"
                            name="pulseem_settings[cart_abandoned_aftertime_duration]"
                            class="mt-1 block w-24 rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm"
                        >
                            <?php
                                $db_selected = $pulseem_admin_model->getUserCartAbandonedAftertimeDuration();
                                if ($db_selected == 0 || !$db_selected) {
                                    $db_selected = 15;
                                }
                            ?>
                            <?php for ($i = 5; $i <= 100; $i++) : ?>
                                <?php $selected = ($db_selected == $i) ? 'selected="selected"' : ''; ?>
                                <option value="<?php echo esc_attr($i); ?>" <?php echo esc_attr($selected); ?>><?php echo esc_html($i); ?></option>
                            <?php endfor; ?>
                        </select>

                        <select
                            id="pulseem_settings[cart_abandoned_aftertime_types]-id"
                            name="pulseem_settings[cart_abandoned_aftertime_types]"
                            class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm"
                        >
                            <?php
                            $types = array(
                                60 => __('Minutes', 'pulseem'),
                                3600 => __('Hours', 'pulseem'),
                                86400 => __('Days', 'pulseem'),
                            );
                            foreach ($types as $key => $value) :
                                $selected = ($pulseem_admin_model->getUserCartAbandonedAftertimeTypes() == $key) ? 'selected="selected"' : '';
                            ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php echo esc_attr($selected); ?>><?php echo esc_html($value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php
                    $message = __('This time setting applies to both regular abandoned carts and "Pending Payment" orders (when enabled above).', 'pulseem');
                    include dirname(__DIR__) . '/partials/alert-info.php';
                    ?>
                </div>
            </div>
        </div>

        <!-- Cart Abandonment Group Assignment -->
        <div class="border rounded-lg bg-white">
            <div class="p-4 border-b bg-gray-50">
                <h3 class="text-lg font-medium text-gray-900"><?php esc_html_e('Group Assignment', 'pulseem'); ?></h3>
            </div>
            <div class="p-4">
                <?php
                $this->selectmultiple(
                    $groups_list,
                    $pulseem_admin_model->getUserCartAbandonedGroupId(),
                    "pulseem_settings[user_cart_abandoned_group_id][]",
                    "id",
                    "name",
                    ["select2 w-full rounded-md border-gray-300 shadow-sm focus:ring-pink-500 focus:border-pink-500 sm:text-sm"]
                )
                ?>
            </div>
        </div>
    </div>
</div>
