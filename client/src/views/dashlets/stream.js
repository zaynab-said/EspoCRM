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

define('views/dashlets/stream', 'views/dashlets/abstract/base', function (Dep) {

    return Dep.extend({

        name: 'Stream',

        _template: '<div class="list-container">{{{list}}}</div>',

        actionRefresh: function () {
            this.getView('list').showNewRecords();
        },

        afterRender: function () {
            this.getCollectionFactory().create('Note', (collection) => {
                this.collection = collection;

                collection.url = 'Stream';
                collection.maxSize = this.getOption('displayRecords');

                if (this.getOption('skipOwn')) {
                    collection.data.skipOwn = true;
                }

                this.listenToOnce(collection, 'sync', () => {
                    this.createView('list', 'views/stream/record/list', {
                        el: this.getSelector() + ' > .list-container',
                        collection: collection,
                        isUserStream: true,
                        noEdit: false,
                    }, (view) => {
                        view.render();
                    });
                });

                collection.fetch();
            });
        },

        setupActionList: function () {
            this.actionList.unshift({
                name: 'viewList',
                text: this.translate('View'),
                iconHtml: '<span class="fas fa-align-justify"></span>',
                url: '#Stream',
            });

            if (!this.getUser().isPortal()) {
                this.actionList.unshift({
                    name: 'create',
                    text: this.translate('Create Post', 'labels'),
                    iconHtml: '<span class="fas fa-plus"></span>',
                });
            }
        },

        actionCreate: function () {
            this.createView('dialog', 'views/stream/modals/create-post', {}, (view) => {
                view.render();

                this.listenToOnce(view, 'after:save', () => {
                    view.close();

                    this.actionRefresh();
                });
            });
        },

        actionViewList: function () {
            this.getRouter().navigate('#Stream', {trigger: true});
        },
    });
});
