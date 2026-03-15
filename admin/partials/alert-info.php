<?php
/**
 * Reusable blue info alert partial.
 *
 * @param string $message The info message to display.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="p-3 bg-blue-50 border-s-4 border-blue-400 rounded-e-lg">
    <div class="flex">
        <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
        </div>
        <div class="ms-3">
            <p class="text-sm text-blue-700">
                <?php echo esc_html($message); ?>
            </p>
        </div>
    </div>
</div>
