/**
 * Row actions of listings embedded in the company form: every ajax action reloads the listing.
 */
define([
    'Magento_Customer/js/grid/columns/actions'
], function (Actions) {
    'use strict';

    return Actions.extend({
        /**
         * Reload the listing after any action.
         */
        onAction: function () {
            this.source().reload({
                refresh: true
            });
        }
    });
});
