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

define('views/modals/select-template', ['views/modals/select-records'], function (Dep) {

    return Dep.extend({

        multiple: false,

        createButton: false,

        searchPanel: false,

        scope: 'Template',

        setup: function () {
            Dep.prototype.setup.call(this);
        },

        loadSearch: function () {
            Dep.prototype.loadSearch.call(this);

            this.searchManager.setAdvanced({
                entityType: {
                    type: 'equals',
                    value: this.options.entityType,
                }
            });

            this.collection.where = this.searchManager.getWhere();
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            let firstLinkElement = this.$el.find('a.link').first().get(0);

            if (firstLinkElement) {
                setTimeout(() => firstLinkElement.focus({preventScroll: true}), 10);
            }
        },
    });
});
