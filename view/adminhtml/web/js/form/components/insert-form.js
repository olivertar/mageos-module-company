/**
 * Modal form inserted in the company form (addresses, users).
 * Closes the modal and reloads the related listing after a successful save or delete.
 */
define([
    'Magento_Ui/js/form/components/insert-form'
], function (Insert) {
    'use strict';

    return Insert.extend({
        defaults: {
            listens: {
                responseData: 'onResponse'
            },
            modules: {
                listing: '${ $.listingProvider }',
                modal: '${ $.modalProvider }'
            }
        },

        /**
         * @param {Object} responseData
         */
        onResponse: function (responseData) {
            if (!responseData.error) {
                this.onDelete();
            }
        },

        /**
         * Close the modal and refresh the listing.
         */
        onDelete: function () {
            this.modal().closeModal();
            this.listing().reload({
                refresh: true
            });
        }
    });
});
