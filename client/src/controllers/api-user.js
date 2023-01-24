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

define('controllers/api-user', ['controllers/record'], function (Dep) {

    /**
     * @class
     * @name Class
     * @extends module:controllers/record.Class
     * @memberOf module:controllers/api-user
     */
    return Dep.extend(/** @lends module:controllers/api-user.Class# */{

        entityType: 'User',

        getCollection: function (callback, context, usePreviouslyFetched) {
            return Dep.prototype.getCollection.call(this, (collection) => {
                collection.data.userType = 'api';

                callback.call(context, collection);
            }, context, usePreviouslyFetched);
        },

        createViewView: function (options, model, view) {
            if (!model.isApi()) {
                if (model.isPortal()) {
                    this.getRouter().dispatch('PortalUser', 'view', {id: model.id, model: model});
                    return;
                }
                this.getRouter().dispatch('User', 'view', {id: model.id, model: model});
                return;
            }
            Dep.prototype.createViewView.call(this, options, model, view);
        },

        actionCreate: function (options) {
            options = options || {};
            options.attributes = options.attributes  || {};
            options.attributes.type = 'api';
            Dep.prototype.actionCreate.call(this, options);
        },
    });
});
