<?php
/**
 * Admin Settings View Template
 *
 * Main wrapper that includes header, form, sidebar navigation, and tab content.
 * Tab content is loaded from admin/tabs/ and reusable components from admin/partials/.
 *
 * @since      1.0.0
 * @version    2.0.0
 */

// Check if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$options = get_option('pulseem_settings', []);

?>

<div class="wrap pulseemplugin-wrapper bg-gray-50 min-h-screen" x-data="{ activeTab: 'api' }" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
    <!-- Header Section -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                    <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'public/logo.webp'); ?>" alt="Pulseem Logo" class="w-8 h-8" />
                    <span><?php echo esc_html__('Pulseem Integration', 'pulseem'); ?></span>
                </h1>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <form method="post" action="options.php">
            <?php settings_fields('pulseem_settings'); ?>

            <!-- Overlay and Modal -->
            <div id="sync-overlay" class="hidden fixed inset-0 bg-gray-800 bg-opacity-50 z-50"></div>
            <div id="sync-modal" class="hidden fixed inset-0 flex items-center justify-center z-50">
                <div class="bg-white p-8 rounded-lg shadow-lg text-center space-y-4 w-96">
                    <div class="sync-spinner animate-spin rounded-full h-12 w-12 border-t-2 border-pink-500 mx-auto"></div>
                    <p class="text-lg font-medium text-gray-800">
                        <?php esc_html_e('Synchronization in Progress', 'pulseem'); ?>
                    </p>
                    <p class="text-sm text-gray-600">
                        <?php esc_html_e('Updating existing products and adding new ones...', 'pulseem'); ?>
                    </p>
                </div>
            </div>

            <?php
            // Notification Messages
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading $_GET['settings-updated'] for success notice display, no data processing.
            if (
                isset($_GET['settings-updated']) &&
                filter_var(sanitize_text_field(wp_unslash($_GET['settings-updated'])), FILTER_VALIDATE_BOOLEAN)
            ) : ?>
                <div class="bg-green-100 border-s-4 border-green-500 text-green-700 p-4 mb-6 rounded-e-lg shadow-sm" role="alert">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ms-3">
                            <p class="font-medium"><?php echo esc_html__('Settings have been successfully updated.', 'pulseem'); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>

            <?php $errors = get_settings_errors('pulseem_settings'); ?>
            <?php if (!empty($errors)) : ?>
                <?php foreach ($errors as $error) : ?>
                    <div class="bg-red-100 border-s-4 border-red-500 text-red-700 p-4 mb-6 rounded-e-lg shadow-sm" role="alert">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ms-3">
                                <p class="font-medium"><?php echo esc_html($error['message']); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Main Content with Sidebar -->
            <div class="flex gap-8">
                <!-- Sidebar Navigation -->
                <div class="w-64 flex-shrink-0">
                    <nav class="bg-white rounded-lg shadow-sm border overflow-hidden">
                        <div class="p-4 bg-gray-50 border-b">
                            <h3 class="text-sm font-medium text-gray-900"><?php esc_html_e('Settings', 'pulseem'); ?></h3>
                        </div>
                        <div class="space-y-1 p-2">
                            <?php
                            $tabs = [
                                'api' => [
                                    'name' => __('API Connection', 'pulseem'),
                                    'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>'
                                ],
                                'signup' => [
                                    'name' => __('Customer Signup', 'pulseem'),
                                    'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>'
                                ],
                                'purchase' => [
                                    'name' => __('Purchase Tracking', 'pulseem'),
                                    'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>'
                                ],
                                'cart' => [
                                    'name' => __('Cart Abandonment', 'pulseem'),
                                    'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>'
                                ],
                                'product_sync' => [
                                    'name' => __('Product Sync', 'pulseem'),
                                    'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>'
                                ],
                                'page_tracking' => [
                                    'name' => __('Page Tracking', 'pulseem'),
                                    'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>'
                                ],
                                'tutorials' => [
                                    'name' => __('Video Tutorials', 'pulseem'),
                                    'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1.01M15 10h1.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
                                ],
                            ];

                            foreach ($tabs as $tab => $details) :
                            ?>
                                <button
                                    @click="activeTab = '<?php echo esc_attr($tab); ?>'"
                                    :class="{
                                        'bg-pink-50 border-pink-200 text-pink-700': activeTab === '<?php echo esc_attr($tab); ?>',
                                        'text-gray-600 hover:bg-gray-50 hover:text-gray-900': activeTab !== '<?php echo esc_attr($tab); ?>'
                                    }"
                                    class="w-full flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg border border-transparent transition-colors duration-150"
                                    type="button"
                                >
                                    <?php echo wp_kses($details['icon'], array(
                                        'svg' => array(
                                            'class' => array(),
                                            'fill' => array(),
                                            'stroke' => array(),
                                            'viewBox' => array(),
                                        ),
                                        'path' => array(
                                            'stroke-linecap' => array(),
                                            'stroke-linejoin' => array(),
                                            'stroke-width' => array(),
                                            'd' => array(),
                                            'fill-rule' => array(),
                                            'clip-rule' => array(),
                                        ),
                                        'g' => array(
                                            'class' => array(),
                                        ),
                                    )); ?>
                                    <?php echo esc_html($details['name']); ?>
                                </button>
                            <?php endforeach; ?>

                            <!-- View Logs Link -->
                            <div class="mt-4 pt-4 border-t">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=pulseem_logs')); ?>"
                                   class="w-full flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors duration-150">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <?php esc_html_e('View Logs', 'pulseem'); ?>
                                </a>
                            </div>
                        </div>
                    </nav>
                </div>

                <!-- Main Content Area -->
                <div class="flex-1 min-w-0">
                    <?php
                    include __DIR__ . '/tabs/tab-api.php';
                    include __DIR__ . '/tabs/tab-signup.php';
                    include __DIR__ . '/tabs/tab-purchase.php';
                    include __DIR__ . '/tabs/tab-cart.php';
                    include __DIR__ . '/tabs/tab-product-sync.php';
                    include __DIR__ . '/tabs/tab-page-tracking.php';
                    include __DIR__ . '/tabs/tab-tutorials.php';
                    ?>

                    <!-- Update Settings Button -->
                    <div class="mt-8 flex justify-end">
                        <button
                            type="submit"
                            class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-pink-600 hover:bg-pink-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pink-500 transition-colors duration-200"
                        >
                            <svg class="w-5 h-5 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <?php esc_html_e('Update Settings', 'pulseem'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
