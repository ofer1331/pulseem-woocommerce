<?php
/**
 * Product Sync tab content.
 *
 * @var object $pulseem_admin_model
 * @var array  $options
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div x-show="activeTab === 'product_sync'" class="bg-white shadow-sm rounded-lg border">
    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
        <h2 class="text-xl font-semibold text-gray-800"><?php esc_html_e('Product Synchronization', 'pulseem'); ?></h2>
    </div>
    <div class="p-6 space-y-6">
        <!-- Enable Product Sync -->
        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
            <div class="flex items-center">
                <label for="is_product_sync_switch" class="block text-sm font-medium text-gray-900">
                    <?php esc_html_e('Enable Product Synchronization', 'pulseem'); ?>
                </label>
            </div>
            <div class="flex items-center">
                <label class="switch">
                    <input type="hidden" name="pulseem_settings[is_product_sync]" value="0">
                    <input
                        type="checkbox"
                        id="is_product_sync_switch"
                        name="pulseem_settings[is_product_sync]"
                        value="1"
                        <?php checked(1, isset($options['is_product_sync']) ? $options['is_product_sync'] : 0); ?>
                    >
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <div class="bg-white rounded-lg">
            <div class="space-y-6">
                <div class="p-4 bg-green-50 border-s-4 border-green-400 rounded-e-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20"></svg>
                        </div>
                        <div class="ms-3">
                            <h3 class="text-lg font-medium text-green-800"><?php esc_html_e('Product Synchronization', 'pulseem'); ?></h3>
                            <p class="text-sm text-green-700 mt-1">
                                <?php esc_html_e('View all synchronized products in the JSON file. Use the sync button to update existing products and add new ones from your store. Products already in the system will only be updated, not duplicated.', 'pulseem'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex gap-6 items-center">
                    <a
                        href="<?php echo esc_url(rest_url('pulseem/v1/get-products-json')); ?>"
                        target="_blank"
                        class="inline-flex items-center px-5 py-2.5 text-sm font-medium rounded-md text-pink-700 bg-pink-50 hover:bg-pink-100 transition-all duration-200 hover:shadow-md"
                        title="View current list of synchronized products"
                    >
                        <svg class="me-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                        </svg>
                        <?php esc_html_e('View Products List', 'pulseem'); ?>
                    </a>

                    <button
                        id="sync-products-btn"
                        type="button"
                        class="inline-flex items-center px-6 py-3 text-base font-medium rounded-md text-white bg-gradient-to-r from-pink-600 to-pink-500 hover:from-pink-700 hover:to-pink-600 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200 focus:ring-2 focus:ring-offset-2 focus:ring-pink-500 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:transform-none"
                        <?php if (!$pulseem_admin_model->getIsProductSync()) echo 'disabled'; ?>
                    >
                        <svg class="me-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <g class="animate-spin origin-center">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </g>
                        </svg>
                        <?php esc_html_e('Sync Store Products', 'pulseem'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
