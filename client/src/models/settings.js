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

define('models/settings', ['model'], function (Dep) {

    /**
     * A config.
     *
     * @class
     * @name Class
     * @extends module:model.Class
     * @memberOf module:models/settings
     */
    return Dep.extend(/** @lends module:models/settings.Class# */{

        /**
         * @inheritDoc
         */
        name: 'Settings',

        entityType: 'Settings',

        /**
         * Load.
         *
         * @returns {Promise}
         */
        load: function () {
            return new Promise(resolve => {
                this.fetch()
                    .then(() => resolve());
            });
        },

        /**
         * Get a value by a path.
         *
         * @param {string[]} arr A path.
         * @returns {*} Null if not set.
         */
        getByPath: function (arr) {
            if (!arr.length) {
                return null;
            }

            let p;

            for (let i = 0; i < arr.length; i++) {
                var item = arr[i];

                if (i === 0) {
                    p = this.get(item);
                }
                else {
                    if (item in p) {
                        p = p[item];
                    }
                    else {
                        return null;
                    }
                }

                if (i === arr.length - 1) {
                    return p;
                }

                if (p === null || typeof p !== 'object') {
                    return null;
                }
            }
        },
    });
});
