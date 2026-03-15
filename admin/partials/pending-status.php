<?php
/**
 * Pending status checkbox + warning partial.
 * Used in signup, purchase, and cart tabs.
 *
 * @param string $checkbox_name  The input name attribute.
 * @param string $checkbox_id    The input id attribute.
 * @param mixed  $checked_value  The current saved value (1 or 0).
 * @param string $warning_message The warning text to display.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="flex items-center ms-4">
    <input
        type="checkbox"
        name="<?php echo esc_attr($checkbox_name); ?>"
        id="<?php echo esc_attr($checkbox_id); ?>"
        value="1"
        <?php checked(1, $checked_value) ?>
        class="h-4 w-4 text-pink-600 focus:ring-pink-500 rounded"
    >
    <label class="ms-3" for="<?php echo esc_attr($checkbox_id); ?>">
        <span class="block text-sm font-medium text-gray-900">
            <?php esc_html_e('Insert customers who did not Opt-In  in "Pending" status', 'pulseem'); ?>
        </span>
    </label>
</div>
<div class="ms-4">
    <?php
    $message = $warning_message;
    include __DIR__ . '/alert-warning.php';
    ?>
</div>
