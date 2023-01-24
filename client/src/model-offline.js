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

define('model-offline', ['model'], function (Model) {

    /**
     * @internal Not used.
     *
     * @class
     * @name Class
     * @extends module:model.Class
     * @memberOf module:model-offline
     */
    return Model.extend(/** @lends module:model-offline.Class# */{

        cache: null,

        _key: null,

        initialize: function (attributes, options) {
            Model.prototype.initialize.apply(this, arguments);

            options = options || {};

            this._key = this.url = this.name;

            this.cache = options.cache || null;
        },

        load: function (callback, disableCache) {
            this.once('sync', callback);

            if (!disableCache) {
                if (this.loadFromCache()) {
                    this.trigger('sync');

                    return new Promise(resolve => resolve());
                }
            }

            return new Promise(resolve => {
                this.fetch()
                    .then(() => {
                        this.storeToCache();

                        resolve();
                    });
            });
        },

        loadSkipCache: function () {
            return this.load(null, true);
        },

        loadFromCache: function () {
            if (this.cache) {
                let cached = this.cache.get('app', this._key);

                if (cached) {
                    this.set(cached);

                    return true;
                }
            }

            return null;
        },

        storeToCache: function () {
            if (this.cache) {
                this.cache.set('app', this._key, this.toJSON());
            }
        },

        isNew: function () {
            return false;
        },
    });
});
