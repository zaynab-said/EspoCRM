/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2023 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

define('views/dashlet', ['view'], function (Dep) {

    /**
     * A base dashlet view.
     *
     * @class
     * @name Class
     * @memberOf module:views/dashlet
     * @extends module:view.Class
     */
    return Dep.extend(/** @lends module:views/dashlet.Class# */{

        /**
         * A dashlet name.
         *
         * @type {string}
         */
        name: null,

        /**
         * A dashlet ID.
         *
         * @type {string}
         */
        id: null,

        /**
         * @inheritDoc
         */
        template: 'dashlet',

        /**
         * An options view name.
         *
         * @protected
         * @type {string|null}
         */
        optionsView: null,

        /**
         * @inheritDoc
         */
        data: function () {
            return {
                name: this.name,
                id: this.id,
                title: this.getTitle(),
                actionList: (this.getView('body') || {}).actionList || [],
                buttonList: (this.getView('body') || {}).buttonList || [],
                noPadding: (this.getView('body') || {}).noPadding,
            };
        },

        /**
         * @inheritDoc
         */
        events: {
            'click .action': function (e) {
                var $target = $(e.currentTarget);
                var action = $target.data('action');
                var data = $target.data();

                if (action) {
                    var method = 'action' + Espo.Utils.upperCaseFirst(action);

                    delete data['action'];

                    if (typeof this[method] == 'function') {
                        e.preventDefault();
                        this[method].call(this, data);
                    } else {
                        var bodyView = this.getView('body');

                        if (typeof bodyView[method] == 'function') {
                            e.preventDefault();
                            bodyView[method].call(bodyView, data);
                        }
                    }
                }
            },
            'mousedown .panel-heading .dropdown-menu': function (e) {
                // Prevent dragging.
                e.stopPropagation();
            },
            'shown.bs.dropdown .panel-heading .btn-group': function (e) {
                this.controlDropdownShown($(e.currentTarget).parent());
            },
            'hide.bs.dropdown .panel-heading .btn-group': function () {
                this.controlDropdownHide();
            },
        },

        controlDropdownShown: function ($dropdownContainer) {
            let $panel = this.$el.children().first();

            let dropdownBottom = $dropdownContainer.find('.dropdown-menu')
                .get(0).getBoundingClientRect().bottom;

            let panelBottom = $panel.get(0).getBoundingClientRect().bottom;

            if (dropdownBottom < panelBottom) {
                return;
            }

            $panel.addClass('has-dropdown-opened');
        },

        controlDropdownHide: function () {
            this.$el.children().first().removeClass('has-dropdown-opened');
        },

        /**
         * @inheritDoc
         */
        setup: function () {
            this.name = this.options.name;
            this.id = this.options.id;

            this.on('resize', () => {
                let bodyView = this.getView('body');

                if (!bodyView) {
                    return;
                }

                bodyView.trigger('resize');
            });

            var viewName = this.getMetadata().get(['dashlets', this.name, 'view']) ||
                'views/dashlets/' + Espo.Utils.camelCaseToHyphen(this.name);

            this.createView('body', viewName, {
                el: this.options.el + ' .dashlet-body',
                id: this.id,
                name: this.name,
                readOnly: this.options.readOnly
            });
        },

        /**
         * Refresh.
         */
        refresh: function () {
            this.getView('body').actionRefresh();
        },

        actionRefresh: function () {
            this.refresh();
        },

        actionOptions: function () {
            let optionsView =
                this.getMetadata().get(['dashlets', this.name, 'options', 'view']) ||
                this.optionsView ||
                'views/dashlets/options/base';

            this.createView('options', optionsView, {
                name: this.name,
                optionsData: this.getOptionsData(),
                fields: this.getView('body').optionsFields,
            }, (view) => {
                view.render();

                this.listenToOnce(view, 'save', (attributes) => {
                    let id = this.id;

                    this.notify('Saving...');

                    this.getPreferences().once('sync', () => {
                        this.getPreferences().trigger('update');
                        this.notify(false);

                        view.close();
                        this.trigger('change');
                    });

                    let o = this.getPreferences().get('dashletsOptions') || {};

                    o[id] = attributes;

                    this.getPreferences().save({
                        dashletsOptions: o
                    }, {patch: true});
                });
            });
        },

        /**
         * Get options data.
         *
         * @returns {Object}
         */
        getOptionsData: function () {
            return this.getView('body').optionsData;
        },

        /**
         * Get an option value.
         *
         * @param {string} key A option name.
         * @returns {*}
         */
        getOption: function (key) {
            return this.getView('body').getOption(key);
        },

        /**
         * Get a dashlet title.
         *
         * @returns {string}
         */
        getTitle: function () {
            return this.getView('body').getTitle();
        },

        actionRemove: function () {
            this.confirm(this.translate('confirmation', 'messages'), () => {
                this.trigger('remove-dashlet');
                this.$el.remove();
                this.remove();
            });
        },
    });
});
