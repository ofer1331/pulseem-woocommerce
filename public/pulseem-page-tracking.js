/**
 * Pulseem Page Tracking
 *
 * Sends page view data to Pulseem via AJAX.
 *
 * @since 1.3.7
 */
jQuery(window).on('load', function () {
    'use strict';

    if (typeof pulseem_tracking === 'undefined') {
        return;
    }

    var pageData = {
        action: 'pulseem_track_page',
        post_id: pulseem_tracking.post_id,
        nonce: pulseem_tracking.nonce
    };

    jQuery.ajax({
        url: pulseem_tracking.ajax_url,
        type: 'POST',
        data: pageData,
        success: function (response) {
            // Tracking data sent
        },
        error: function () {
            // Tracking failed silently
        }
    });
});
