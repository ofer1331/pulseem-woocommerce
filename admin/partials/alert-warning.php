<?php
/**
 * Reusable yellow warning alert partial.
 *
 * @param string $message The warning message to display.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="p-4 bg-yellow-50 border-s-4 border-yellow-400 rounded-e-lg">
    <div class="flex">
        <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.682-1.36 3.447 0l6.518 11.618c.722 1.286-.191 2.883-1.724 2.883H3.463c-1.533 0-2.446-1.597-1.724-2.883L8.257 3.1zM11 14a1 1 0 100-2 1 1 0 000 2zm-1-4a1 1 0 011-1V7a1 1 0 10-2 0v2a1 1 0 011 1z" clip-rule="evenodd"/>
            </svg>
        </div>
        <div class="ms-3">
            <p class="text-sm text-yellow-700">
                <?php echo esc_html($message); ?>
            </p>
        </div>
    </div>
</div>
