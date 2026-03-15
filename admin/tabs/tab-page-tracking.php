<?php
/**
 * Page Tracking tab content.
 *
 * @var object $pulseem_admin_model
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div x-show="activeTab === 'page_tracking'" class="bg-white shadow-sm rounded-lg border">
    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
        <h2 class="text-xl font-semibold text-gray-800"><?php esc_html_e('Page Tracking Settings', 'pulseem'); ?></h2>
    </div>
    <div class="p-6 space-y-6">
        <!-- Enable Page Tracking -->
        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div class="flex items-center">
                <input
                    type="checkbox"
                    name="pulseem_settings[is_page_tracking]"
                    id="is_page_tracking"
                    value="1"
                    <?php checked(1, $pulseem_admin_model->getIsPageTracking()) ?>
                    class="h-4 w-4 text-pink-600 focus:ring-pink-500 rounded"
                >
                <label class="ms-3" for="is_page_tracking">
                    <span class="block text-sm font-medium text-gray-900">
                        <?php esc_html_e('Enable General Page Tracking', 'pulseem'); ?>
                    </span>
                    <span class="block text-sm text-gray-500">
                        <?php esc_html_e('Track customer activities on general website pages.', 'pulseem'); ?>
                    </span>
                </label>
            </div>
        </div>

        <!-- Enable WooCommerce Page Tracking -->
        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div class="flex items-center">
                <input
                    type="checkbox"
                    name="pulseem_settings[is_woocommerce_page_tracking]"
                    id="is_woocommerce_page_tracking"
                    value="1"
                    <?php checked(1, $pulseem_admin_model->getIsWooCommercePageTracking()) ?>
                    class="h-4 w-4 text-pink-600 focus:ring-pink-500 rounded"
                >
                <label class="ms-3" for="is_woocommerce_page_tracking">
                    <span class="block text-sm font-medium text-gray-900">
                        <?php esc_html_e('Enable WooCommerce Page Tracking', 'pulseem'); ?>
                    </span>
                    <span class="block text-sm text-gray-500">
                        <?php esc_html_e('Track customer activities specifically on WooCommerce pages like product, cart, and checkout pages.', 'pulseem'); ?>
                    </span>
                </label>
            </div>
        </div>

        <!-- Explanation -->
        <?php
        $message = __('Enable these options to monitor which pages customers are visiting. This helps in analyzing customer behavior and improving engagement.', 'pulseem');
        include dirname(__DIR__) . '/partials/alert-warning.php';
        ?>
    </div>
</div>
