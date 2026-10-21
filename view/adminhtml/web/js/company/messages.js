/**
 * Company form message thread: sends a reply or internal note and refreshes the thread.
 */
define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function ($, alert, $t) {
    'use strict';

    return function (config, element) {
        var $root = $(element);

        $root.on('click', '[data-role=send]', function (event) {
            var $body = $root.find('[data-role=body]'),
                body = $.trim($body.val());

            event.preventDefault();

            if (!body) {
                alert({content: $t('Please write a message.')});

                return;
            }

            $.ajax({
                url: config.url,
                type: 'POST',
                dataType: 'json',
                showLoader: true,
                data: {
                    'form_key': window.FORM_KEY,
                    'company_id': config.companyId,
                    'body': body,
                    'is_internal': $root.find('[data-role=internal]').is(':checked') ? 1 : 0
                }
            }).done(function (response) {
                if (response.error) {
                    alert({content: response.message});

                    return;
                }
                $root.find('[data-role=thread]').html(response.html);
                $body.val('');
                $root.find('[data-role=internal]').prop('checked', false);
            }).fail(function () {
                alert({content: $t('The message could not be sent. Please try again.')});
            });
        });
    };
});
