/**
 * Pulseem Admin Settings JavaScript
 *
 * Handles: Select2 init, duration options, product sync, agreement sync, checkbox sync.
 */
jQuery(document).ready(function ($) {
    // Initialize Select2
    $('.select2').select2({
        maximumSelectionLength: 10,
        theme: 'default'
    });

    // Duration options for cart abandonment time settings
    var timeTypeSelect = document.getElementById('pulseem_settings[cart_abandoned_aftertime_types]-id');
    var durationSelect = document.getElementById('pulseem_settings[cart_abandoned_aftertime_duration]-id');

    if (timeTypeSelect && durationSelect) {
        function updateDurationOptions() {
            var startValue = 5;
            if (timeTypeSelect.value === '3600' || timeTypeSelect.value === '86400') {
                startValue = 1;
            }
            var maxValue = 100;
            var currentValue = parseInt(durationSelect.value, 10);

            durationSelect.innerHTML = '';

            for (var i = startValue; i <= maxValue; i++) {
                var option = document.createElement('option');
                option.value = i;
                option.text = i;
                if (i === currentValue) {
                    option.selected = true;
                }
                durationSelect.appendChild(option);
            }
        }

        timeTypeSelect.addEventListener('change', updateDurationOptions);
        updateDurationOptions();
    }

    // Product Sync
    var syncOverlay = $('#sync-overlay');
    var syncModal = $('#sync-modal');
    var syncButton = $('#sync-products-btn');

    function toggleModal(show) {
        if (show) {
            syncOverlay.removeClass('hidden');
            syncModal.removeClass('hidden');
        } else {
            syncOverlay.addClass('hidden');
            syncModal.addClass('hidden');
        }
    }

    function showSuccessNotification(message) {
        var notification = $('<div>', {
            class: 'fixed top-12 start-4 bg-green-50 border-s-4 border-green-400 p-4 rounded-e-lg shadow-lg transform translate-x-full transition-transform duration-300 flex items-center',
            css: { 'z-index': 50 }
        }).append(
            $('<svg>', {
                class: 'h-6 w-6 text-green-400 me-3',
                fill: 'none',
                viewBox: '0 0 24 24',
                stroke: 'currentColor'
            }).append(
                $('<path>', {
                    'stroke-linecap': 'round',
                    'stroke-linejoin': 'round',
                    'stroke-width': '2',
                    d: 'M5 13l4 4L19 7'
                })
            ),
            $('<span>', {
                class: 'text-green-700',
                text: message
            })
        ).appendTo('body');

        setTimeout(function () {
            notification.css('transform', 'translateX(0)');
        }, 100);

        setTimeout(function () {
            notification.css('transform', 'translateX(full)');
            setTimeout(function () {
                notification.remove();
            }, 300);
        }, 3000);
    }

    function triggerConfetti() {
        if (typeof confetti === 'undefined') return;
        var duration = 1500;
        var end = Date.now() + duration;

        (function frame() {
            var timeLeft = end - Date.now();
            if (timeLeft <= 0) return;

            confetti({ particleCount: 7, angle: 60, spread: 55, origin: { x: 0, y: 0.65 } });
            confetti({ particleCount: 7, angle: 120, spread: 55, origin: { x: 1, y: 0.65 } });
            confetti({ particleCount: 7, angle: 90, spread: 100, origin: { x: 0.5, y: 0.7 } });

            requestAnimationFrame(frame);
        })();
    }

    syncButton.on('click', function () {
        toggleModal(true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'pulseem_sync_products' },
            success: function () {
                toggleModal(false);
                showSuccessNotification(pulseem_admin_i18n.sync_success);
                triggerConfetti();
            },
            error: function (xhr, status, error) {
                toggleModal(false);
                console.error('Error:', error);
                alert(pulseem_admin_i18n.sync_error);
            }
        });
    });

    $('#is_product_sync_switch').on('change', function () {
        var isChecked = $(this).is(':checked') ? 1 : 0;

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'pulseem_update_product_sync_status',
                is_product_sync: isChecked,
                nonce: pulseem_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    if (isChecked === 1) {
                        syncButton.trigger('click');
                    }
                } else {
                    console.error('Error:', response.data.message);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', error);
            }
        });
    });

    // Sync agreement text inputs across purchase and cart tabs
    var agreementInputs = document.querySelectorAll('[name="pulseem_settings[checkout_agreement_text]"]');
    agreementInputs.forEach(function (input) {
        input.addEventListener('input', function () {
            var value = this.value;
            agreementInputs.forEach(function (otherInput) {
                if (otherInput !== input) {
                    otherInput.value = value;
                }
            });
        });
    });

    // Sync checkout agreement checkboxes
    var syncCheckboxes = document.querySelectorAll('.sync-checkbox');
    syncCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            var isChecked = this.checked;
            syncCheckboxes.forEach(function (cb) {
                if (cb !== checkbox) {
                    cb.checked = isChecked;
                }
            });
        });
    });
});
