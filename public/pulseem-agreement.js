/**
 * Pulseem Agreement Checkbox Toggle
 *
 * Shows/hides the agreement field based on create account checkbox.
 *
 * @since 1.3.7
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var accountCheckbox = document.querySelector('#createaccount');
    var agreementField = document.querySelector('.pulseem-agreement-field');

    if (accountCheckbox && agreementField) {
        agreementField.style.display = accountCheckbox.checked ? 'block' : 'none';

        accountCheckbox.addEventListener('change', function () {
            agreementField.style.display = accountCheckbox.checked ? 'block' : 'none';
        });
    }
});
