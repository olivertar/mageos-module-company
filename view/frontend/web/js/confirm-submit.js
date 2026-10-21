/**
 * Asks for confirmation before submitting a form.
 */
define([
    'jquery',
    'Magento_Ui/js/modal/confirm'
], function ($, confirm) {
    'use strict';

    return function (config, form) {
        var confirmed = false;

        $(form).on('submit', function (event) {
            if (confirmed) {
                return;
            }
            event.preventDefault();
            confirm({
                content: config.message,
                actions: {
                    confirm: function () {
                        confirmed = true;
                        form.submit();
                    }
                }
            });
        });
    };
});
