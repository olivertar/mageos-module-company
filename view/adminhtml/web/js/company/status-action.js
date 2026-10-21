/**
 * Company status button: asks for a comment (or a required reason) and posts the status action.
 */
define([
    'jquery',
    'Magento_Ui/js/modal/prompt'
], function ($, prompt) {
    'use strict';

    return function (config, element) {
        $(element).on('click', function (event) {
            event.preventDefault();
            event.stopImmediatePropagation();

            prompt({
                title: config.title,
                content: config.label,
                validation: !!config.reasonRequired,
                validationRules: config.reasonRequired ? ['required-entry'] : [],
                actions: {
                    confirm: function (comment) {
                        var form = $('<form>', {
                            method: 'post',
                            action: config.url
                        });

                        form.append($('<input>', {type: 'hidden', name: 'form_key', value: window.FORM_KEY}));
                        form.append($('<input>', {type: 'hidden', name: 'status_action', value: config.action}));
                        form.append($('<input>', {type: 'hidden', name: 'comment', value: comment || ''}));
                        form.appendTo('body').trigger('submit');
                    }
                }
            });
        });
    };
});
