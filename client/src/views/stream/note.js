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

define('views/stream/note', ['view'], function (Dep) {

    /**
     * @class
     * @name Class
     * @memberOf module:views/stream/note
     * @extends module:view
     */
    return Dep.extend(/** @lends module:views/stream/note.Class# */{

        /**
         * @protected
         * @type {string|null}
         */
        messageName: null,

        /**
         * @protected
         * @type {string|null}
         */
        messageTemplate: null,

        /**
         * Data to pass to a message template.
         *
         * @protected
         * @type {Object.<string,JQuery|Element|string>|null}
         */
        messageData: null,

        /**
         * @protected
         */
        isEditable: false,

        /**
         * @protected
         */
        isRemovable: false,

        /**
         * @protected
         */
        isSystemAvatar: false,

        data: function () {
            return {
                isUserStream: this.isUserStream,
                noEdit: this.options.noEdit,
                acl: this.options.acl,
                onlyContent: this.options.onlyContent,
                avatar: this.getAvatarHtml()
            };
        },

        init: function () {
            this.createField('createdAt', null, null, 'views/fields/datetime-short');
            this.isUserStream = this.options.isUserStream;
            this.isThis = !this.isUserStream;

            this.parentModel = this.options.parentModel;

            if (!this.isUserStream) {
                if (this.parentModel) {
                    if (
                        this.parentModel.name !== this.model.get('parentType') ||
                        this.parentModel.id !== this.model.get('parentId')
                    ) {
                        this.isThis = false;
                    }
                }
            }

            if (this.getUser().isAdmin()) {
                this.isRemovable = true;
            }

            if (this.messageName && this.isThis) {
                this.messageName += 'This';
            }

            if (!this.isThis) {
                this.createField('parent');
            }

            let translatedEntityType = this.translateEntityType(this.model.get('parentType'));

            this.messageData = {
                'user': 'field:createdBy',
                'entity': 'field:parent',
                'entityType': translatedEntityType,
            };

            if (!this.options.noEdit && (this.isEditable || this.isRemovable)) {
                this.createView('right', 'views/stream/row-actions/default', {
                    el: this.options.el + ' .right-container',
                    acl: this.options.acl,
                    model: this.model,
                    isEditable: this.isEditable,
                    isRemovable: this.isRemovable
                });
            }
        },

        translateEntityType: function (entityType, isPlural) {
            let string = isPlural ?
                (this.translate(entityType, 'scopeNamesPlural') || '') :
                (this.translate(entityType, 'scopeNames') || '');

            string = string.toLowerCase();

            let language = this.getPreferences().get('language') || this.getConfig().get('language');

            if (~['de_DE', 'nl_NL'].indexOf(language)) {
                string = Espo.Utils.upperCaseFirst(string);
            }

            return string;
        },

        createField: function (name, type, params, view, options) {
            type = type || this.model.getFieldType(name) || 'base';

            let o = {
                model: this.model,
                defs: {
                    name: name,
                    params: params || {}
                },
                el: this.options.el + ' .cell-' + name,
                mode: 'list',
            };

            if (options) {
                for (var i in options) {
                    o[i] = options[i];
                }
            }

            this.createView(name, view || this.getFieldManager().getViewName(type), o);
        },

        isMale: function () {
            return this.model.get('createdByGender') === 'Male';
        },

        isFemale: function () {
            return this.model.get('createdByGender') === 'Female';
        },

        createMessage: function () {
            if (!this.messageTemplate) {
                let isTranslated = false;
                let parentType = this.model.get('parentType') || null;

                if (this.isMale()) {
                    this.messageTemplate = this.translate(this.messageName, 'streamMessagesMale', parentType) || '';

                    if (this.messageTemplate !== this.messageName) {
                        isTranslated = true;
                    }
                } else if (this.isFemale()) {
                    this.messageTemplate = this.translate(this.messageName, 'streamMessagesFemale', parentType) || '';

                    if (this.messageTemplate !== this.messageName) {
                        isTranslated = true;
                    }
                }

                if (!isTranslated) {
                    this.messageTemplate = this.translate(this.messageName, 'streamMessages', parentType) || '';
                }
            }

            if (
                this.messageTemplate.indexOf('{entityType}') === 0 &&
                typeof this.messageData.entityType === 'string'
            ) {
                this.messageData.entityTypeUcFirst = Espo.Utils.upperCaseFirst(this.messageData.entityType);

                this.messageTemplate = this.messageTemplate.replace('{entityType}', '{entityTypeUcFirst}');
            }

            this.createView('message', 'views/stream/message', {
                messageTemplate: this.messageTemplate,
                el: this.options.el + ' .message',
                model: this.model,
                messageData: this.messageData,
            });
        },

        getAvatarHtml: function () {
            let id = this.model.get('createdById');

            if (this.isSystemAvatar) {
                id = 'system';
            }

            return this.getHelper().getAvatarHtml(id, 'small', 20);
        },

        getIconHtml: function (scope, id) {
            if (this.isThis && scope === this.parentModel.name) {
                return;
            }

            let iconClass = this.getMetadata().get(['clientDefs', scope, 'iconClass']);

            if (!iconClass) {
                return;
            }

            return $('<span>')
                .addClass(iconClass)
                .addClass('action text-muted icon')
                .css('cursor', 'pointer')
                .attr('title', this.translate('View'))
                .attr('data-action', 'quickView')
                .attr('data-id', id)
                .attr('data-scope', scope)
                .get(0).outerHTML;
        },
    });
});
