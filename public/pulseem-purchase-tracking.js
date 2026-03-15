/**
 * Pulseem Purchase Tracking
 *
 * Handles purchase tracking on the WooCommerce thank you page.
 *
 * @since 1.3.7
 */
(function () {
    'use strict';

    window.addEventListener('load', function () {
        if (typeof window.trackPurchase === 'function' && typeof pulseem_purchase_data !== 'undefined') {
            window.trackPurchase(
                pulseem_purchase_data.orderId,
                pulseem_purchase_data.grandTotal,
                pulseem_purchase_data.shipping,
                pulseem_purchase_data.tax,
                pulseem_purchase_data.orderItems
            );
        }
    });
})();
