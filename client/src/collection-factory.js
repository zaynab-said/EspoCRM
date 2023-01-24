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

 define('collection-factory', [], function () {

    /**
     * A collection factory.
     *
     * @class
     * @name Class
     * @memberOf module:collection-factory
     */
    let CollectionFactory = function (modelFactory, config) {
        this.modelFactory = modelFactory;
        this.config = config;
    };

    _.extend(CollectionFactory.prototype, /** @lends module:collection-factory.Class# */ {

        /**
         * @private
         */
        modelFactory: null,

        /**
         * @private
         */
        recordListMaxSizeLimit: 200,

        /**
         * Create a collection.
         *
         * @param {string} name An entity type.
         * @param {Function} [callback] Deprecated.
         * @param {Object} [context] Deprecated.
         * @returns {Promise<module:collection.Class>}
         */
        create: function (name, callback, context) {
            return new Promise(resolve => {
                context = context || this;

                this.modelFactory.getSeed(name, seed => {
                    let orderBy = this.modelFactory.metadata
                        .get(['entityDefs', name, 'collection', 'orderBy']);

                    let order = this.modelFactory.metadata
                        .get(['entityDefs', name, 'collection', 'order']);

                    let className = this.modelFactory.metadata
                        .get(['clientDefs', name, 'collection']) || 'collection';

                    require(className, collectionClass => {
                        let collection = new collectionClass(null, {
                            name: name,
                            orderBy: orderBy,
                            order: order,
                        });

                        collection.model = seed;
                        collection._user = this.modelFactory.user;
                        collection.entityType = name;

                        collection.maxMaxSize = this.config.get('recordListMaxSizeLimit') ||
                            this.recordListMaxSizeLimit;

                        if (callback) {
                            callback.call(context, collection);
                        }

                        resolve(collection);
                    });
                });
            });
        },
    });

    return CollectionFactory;
});
