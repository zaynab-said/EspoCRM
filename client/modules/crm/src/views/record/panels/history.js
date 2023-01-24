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

define('crm:views/record/panels/history', ['crm:views/record/panels/activities'], function (Dep) {

    return Dep.extend({

        name: 'history',

        orderBy: 'dateStart',

        orderDirection: 'desc',

        rowActionsView: 'crm:views/record/row-actions/history',

        actionList: [],

        listLayout: {
            'Email': {
                rows: [
                    [
                        {name: 'ico', view: 'crm:views/fields/ico'},
                        {
                            name: 'name',
                            link: true,
                        },
                    ],
                    [
                        {name: 'status'},
                        {name: 'dateSent'},
                        {name: 'hasAttachment', view: 'views/email/fields/has-attachment'},
                    ],
                ]
            },
        },

        where: {
            scope: false,
        },

        setupActionList: function () {
            Dep.prototype.setupActionList.call(this);

            this.actionList.push({
                action: 'archiveEmail',
                label: 'Archive Email',
                acl: 'create',
                aclScope: 'Email',
            });
        },

        getArchiveEmailAttributes: function (scope, data, callback) {
            data = data || {};

            let attributes = {
                dateSent: this.getDateTime().getNow(15),
                status: 'Archived',
                from: this.model.get('emailAddress'),
                to: this.getUser().get('emailAddress'),
            };

            if (this.model.name === 'Contact') {
                if (this.getConfig().get('b2cMode')) {
                    attributes.parentType = 'Contact';
                    attributes.parentName = this.model.get('name');
                    attributes.parentId = this.model.id;
                } else {
                    if (this.model.get('accountId')) {
                        attributes.parentType = 'Account',
                        attributes.parentId = this.model.get('accountId');
                        attributes.parentName = this.model.get('accountName');
                    }
                }
            } else if (this.model.name === 'Lead') {
                attributes.parentType = 'Lead';
                attributes.parentId = this.model.id
                attributes.parentName = this.model.get('name');
            }

            attributes.nameHash = {};
            attributes.nameHash[this.model.get('emailAddress')] = this.model.get('name');

            if (scope) {
                if (!attributes.parentId) {
                    if (this.checkParentTypeAvailability(scope, this.model.name)) {
                        attributes.parentType = this.model.name;
                        attributes.parentId = this.model.id;
                        attributes.parentName = this.model.get('name');
                    }
                } else {
                    if (attributes.parentType && !this.checkParentTypeAvailability(scope, attributes.parentType)) {
                        attributes.parentType = null;
                        attributes.parentId = null;
                        attributes.parentName = null;
                    }
                }
            }

            callback.call(this, attributes);
        },

        actionArchiveEmail: function (data) {
            let self = this;
            let link = 'emails';
            let scope = 'Email';

            let relate = null;

            if ('emails' in this.model.defs['links']) {
                relate = {
                    model: this.model,
                    link: this.model.defs['links']['emails'].foreign,
                };
            }

            Espo.Ui.notify(' ... ');

            let viewName = this.getMetadata().get('clientDefs.' + scope + '.modalViews.edit') ||
                'views/modals/edit';

            this.getArchiveEmailAttributes(scope, data, (attributes) => {
                this.createView('quickCreate', viewName, {
                    scope: scope,
                    relate: relate,
                    attributes: attributes,
                }, (view) => {
                    view.render();
                    view.notify(false);

                    this.listenToOnce(view, 'after:save', () => {
                        this.collection.fetch();
                        this.model.trigger('after:relate');
                    });
                });
            });
        },

        actionReply: function (data) {
            let id = data.id;

            if (!id) {
                return;
            }

            Espo.require('EmailHelper', EmailHelper => {
                let emailHelper =
                    new EmailHelper(this.getLanguage(), this.getUser(), this.getDateTime(), this.getAcl());

                Espo.Ui.notify(' ... ');

                this.getModelFactory().create('Email')
                    .then(model => {
                        model.id = id;

                        model.fetch()
                            .then(() => {
                                let attributes = emailHelper
                                    .getReplyAttributes(model, data,
                                        this.getPreferences().get('emailReplyToAllByDefault'));

                                let viewName = this.getMetadata().get('clientDefs.Email.modalViews.compose') ||
                                    'views/modals/compose-email';

                                return this.createView('quickCreate', viewName, {
                                    attributes: attributes,
                                    focusForCreate: true,
                                });
                            })
                            .then(view => {
                                view.render();

                                this.listenToOnce(view, 'after:save', () => {
                                    this.collection.fetch();
                                    this.model.trigger('after:relate');
                                });

                                view.notify(false);
                            });
                    });
            });
        },
    });
});
