<?php
/**
 * Video Tutorials tab content.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div x-show="activeTab === 'tutorials'" class="bg-white shadow-sm rounded-lg border">
    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
        <h2 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
            <svg class="w-6 h-6 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553 4.553a1 1 0 010 1.414l-4.553 4.553M9 5l8 7-8 7"/>
            </svg>
            <?php esc_html_e('Video Tutorial', 'pulseem'); ?>
        </h2>
        <p class="text-sm text-gray-600 mt-2">
            <?php esc_html_e('Learn how to install and set up Pulseem integration with this comprehensive video guide.', 'pulseem'); ?>
        </p>
    </div>

    <div class="p-6">
        <!-- Simple Video Display -->
        <div class="max-w-4xl mx-auto">
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">

                <!-- Video Title -->
                <div class="p-4 bg-gray-50 border-b">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">
                        <?php esc_html_e('Plugin Installation Guide', 'pulseem'); ?>
                    </h3>
                    <p class="text-gray-600 text-sm">
                        <?php esc_html_e('Complete step-by-step guide for installing and setting up the Pulseem WooCommerce integration plugin.', 'pulseem'); ?>
                    </p>
                </div>

                <!-- YouTube Video Embed -->
                <div class="aspect-video">
                    <iframe
                        width="100%"
                        height="100%"
                        src="https://www.youtube.com/embed/SyBkFm58Rto"
                        title="Pulseem Plugin Installation Guide"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen
                        class="w-full h-full"
                    ></iframe>
                </div>

                <!-- Video Info -->
                <div class="p-4">
                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                            <?php esc_html_e('Setup Guide', 'pulseem'); ?>
                        </span>
                        <span class="text-sm text-gray-500">
                            <?php esc_html_e('Duration: 3:45', 'pulseem'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
