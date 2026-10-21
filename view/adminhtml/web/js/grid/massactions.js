/**
 * Grid mass actions: actions with a "prompt" config ask for a comment (or a required reason) first and post it
 * as "comment" along with the selection.
 */
define([
    'underscore',
    'Magento_Ui/js/grid/massactions',
    'Magento_Ui/js/modal/prompt'
], function (_, Massactions, prompt) {
    'use strict';

    return Massactions.extend({
        /**
         * @inheritdoc
         */
        _getCallback: function (action, selections) {
            var callback = this._super(action, selections),
                config = action.prompt;

            if (!config) {
                return callback;
            }

            return function () {
                prompt({
                    title: config.title,
                    content: config.label,
                    validation: !!config.required,
                    validationRules: config.required ? ['required-entry'] : [],
                    actions: {
                        confirm: function (comment) {
                            this.defaultCallback(action, _.extend({}, selections, {
                                params: _.extend({}, selections.params || {}, {comment: comment || ''})
                            }));
                        }.bind(this)
                    }
                });
            }.bind(this);
        }
    });
});
