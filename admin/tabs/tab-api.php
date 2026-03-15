<?php
/**
 * API Connection tab content.
 *
 * @var object $pulseem_admin_model
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div x-show="activeTab === 'api'" class="bg-white shadow-sm rounded-lg border">
    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
        <h2 class="text-xl font-semibold text-gray-800"><?php esc_html_e('API Connection', 'pulseem'); ?></h2>
    </div>
    <div class="p-6">
        <!-- Compatible Plugins Section -->
        <div class="mb-8">
            <h3 class="text-lg font-medium text-gray-900 mb-4"><?php esc_html_e('Compatible Plugins Status', 'pulseem'); ?></h3>
            <p class="text-sm text-gray-600 mb-4">
                <?php esc_html_e('Below you can see which compatible plugins are currently active on your site.', 'pulseem'); ?>
            </p>

            <div class="grid gap-4">
                <?php
                $installed_themes = wp_get_themes();
                $bricks_installed = false;

                foreach ($installed_themes as $theme_slug => $theme_data) {
                    if (strpos(strtolower($theme_data->get('Name')), 'bricks') !== false) {
                        $bricks_installed = true;
                        break;
                    }
                }

                $plugins = [
                    'elementor' => [
                        'name' => __('Elementor', 'pulseem'),
                        'status' => is_plugin_active('elementor/elementor.php'),
                        'description' => __('Elementor page builder for advanced form creation and integration', 'pulseem'),
                        'icon' => '<img src="' . esc_url(plugin_dir_url(dirname(__DIR__)) . 'assets/logos/elementor.png') . '" alt="Elementor" class="w-12 h-12 border-0" />'
                    ],
                    'cf7' => [
                        'name' => __('Contact Form 7', 'pulseem'),
                        'status' => is_plugin_active('contact-form-7/wp-contact-form-7.php'),
                        'description' => __('Contact Form 7 for lead capture and data collection', 'pulseem'),
                        'icon' => '<img src="' . esc_url(plugin_dir_url(dirname(__DIR__)) . 'assets/logos/cf7.png') . '" alt="Contact Form 7" class="w-12 h-12 border-0" />'
                    ],
                    'bricks' => [
                        'name' => __('Bricks Builder', 'pulseem'),
                        'status' => $bricks_installed,
                        'description' => __('Bricks Builder for signup & checkout forms (soon also for regular forms).', 'pulseem'),
                        'icon' => '<img src="' . esc_url(plugin_dir_url(dirname(__DIR__)) . 'assets/logos/bricks.png') . '" alt="Bricks Builder" class="w-12 h-12 border-0" />'
                    ],
                ];

                foreach ($plugins as $plugin) :
                    $status_color = $plugin['status'] ? 'green' : 'red';
                    $status_text = $plugin['status'] ? __('Active', 'pulseem') : __('Inactive', 'pulseem');
                ?>
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div class="p-4">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                <div class="p-2 bg-gray-100 rounded-lg">
                                    <?php echo wp_kses($plugin['icon'], array(
                                        'img' => array(
                                            'src' => array(),
                                            'alt' => array(),
                                            'class' => array(),
                                        ),
                                    )); ?>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <p class="text-lg font-semibold text-gray-900">
                                        <?php echo esc_html($plugin['name']); ?>
                                    </p>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-<?php echo esc_attr($status_color); ?>-100 text-<?php echo esc_attr($status_color); ?>-800">
                                        <?php echo esc_html($status_text); ?>
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">
                                    <?php echo esc_html($plugin['description']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- API Key Input Section -->
        <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <label for="api_key" class="block text-sm font-medium text-gray-700">
                        <?php esc_html_e('API Key', 'pulseem'); ?>
                    </label>
                    <span class="text-xs text-gray-500"><?php esc_html_e('Required', 'pulseem'); ?></span>
                </div>
                <div class="mt-1 relative rounded-md shadow-sm">
                    <input
                        type="text"
                        name="pulseem_settings[api_key]"
                        id="api_key"
                        value="<?php echo esc_attr($pulseem_admin_model->getApiKey()); ?>"
                        class="block w-full pe-10 focus:ring-pink-500 focus:border-pink-500 sm:text-sm rounded-md border-gray-300"
                        placeholder="<?php esc_attr_e('Enter your API key here', 'pulseem'); ?>"
                    >
                    <div class="absolute inset-y-0 end-0 pe-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                    </div>
                </div>
                <p class="mt-2 text-sm text-gray-500">
                    <?php esc_html_e('Enter your Pulseem API key to enable integration features.', 'pulseem'); ?>
                </p>
            </div>
        </div>
    </div>
</div>
