/**
 * Pulseem Logs Page
 *
 * Handles bulk select, modal, export, settings save, and sorting.
 *
 * @since 1.4.0
 */

function pulseemLogs() {
    'use strict';

    return {
        selectedIds: [],
        modalOpen: false,
        modalData: null,
        settingsSaved: false,

        toggleAll(event) {
            var checkboxes = document.querySelectorAll('input[name="log_ids[]"]');
            var checked = event.target.checked;
            this.selectedIds = [];
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = checked;
                if (checked) {
                    this.selectedIds.push(parseInt(checkboxes[i].value));
                }
            }
        },

        toggleId(id, event) {
            if (event.target.checked) {
                this.selectedIds.push(id);
            } else {
                this.selectedIds = this.selectedIds.filter(function(item) { return item !== id; });
            }
        },

        openModal(data) {
            this.modalData = data;
            this.modalOpen = true;
        },

        saveLogSettings() {
            var self = this;
            var level = document.getElementById('setting_log_level').value;
            var retention = document.getElementById('setting_retention').value;

            var formData = new FormData();
            formData.append('action', 'pulseem_save_log_settings');
            formData.append('nonce', pulseem_logs.nonce);
            formData.append('log_level', level);
            formData.append('log_retention_days', retention);

            fetch(pulseem_logs.ajax_url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) { return response.json(); })
            .then(function(result) {
                if (result.success) {
                    self.settingsSaved = true;
                    setTimeout(function() { self.settingsSaved = false; }, 3000);
                }
            });
        }
    };
}

/* Register with Alpine.data() for Alpine v3 */
document.addEventListener('alpine:init', function() {
    if (window.Alpine && window.Alpine.data) {
        window.Alpine.data('pulseemLogs', pulseemLogs);
    }
});
