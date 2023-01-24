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

define('views/fields/user',[ 'views/fields/link'], function (Dep) {

    return Dep.extend({

        searchTemplate: 'fields/user/search',

        setupSearch: function () {
            Dep.prototype.setupSearch.call(this);

            this.searchTypeList = Espo.Utils.clone(this.searchTypeList);

            this.searchTypeList.push('isFromTeams');

            this.searchData.teamIdList = this.getSearchParamsData().teamIdList ||
                this.searchParams.teamIdList || [];

            this.searchData.teamNameHash = this.getSearchParamsData().teamNameHash ||
                this.searchParams.teamNameHash || {};

            this.events['click a[data-action="clearLinkTeams"]'] = function (e) {
                var id = $(e.currentTarget).data('id').toString();

                this.deleteLinkTeams(id);
            };

            this.addActionHandler('selectLinkTeams', function () {
                Espo.Ui.notify(' ... ');

                var viewName = this.getMetadata().get('clientDefs.Team.modalViews.select') ||
                    'views/modals/select-records';

                this.createView('dialog', viewName, {
                    scope: 'Team',
                    createButton: false,
                    multiple: true,
                }, function (view) {
                    view.render();

                    this.notify(false);

                    this.listenToOnce(view, 'select', function (models) {
                        if (Object.prototype.toString.call(models) !== '[object Array]') {
                            models = [models];
                        }

                        models.forEach(function (model) {
                            this.addLinkTeams(model.id, model.get('name'));
                        }, this);
                    });
                }, this);
            });

            this.events['click a[data-action="clearLinkTeams"]'] = function (e) {
                var id = $(e.currentTarget).data('id').toString();

                this.deleteLinkTeams(id);
            };
        },

        handleSearchType: function (type) {
            Dep.prototype.handleSearchType.call(this, type);

            if (type === 'isFromTeams') {
                this.$el.find('div.teams-container').removeClass('hidden');
            }
            else {
                this.$el.find('div.teams-container').addClass('hidden');
            }
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'search') {
                var $elemeneTeams = this.$el.find('input.element-teams');

                $elemeneTeams.autocomplete({
                    serviceUrl: (q) => {
                        return 'Team?&maxSize=' + this.getAutocompleteMaxCount() + '&select=id,name';
                    },
                    minChars: 1,
                    triggerSelectOnValidInput: false,
                    paramName: 'q',
                    noCache: true,
                    formatResult: (suggestion) => {
                        return this.getHelper().escapeString(suggestion.name);
                    },
                    transformResult: (response) => {
                        var response = JSON.parse(response);
                        var list = [];

                        response.list.forEach(item => {
                            list.push({
                                id: item.id,
                                name: item.name,
                                data: item.id,
                                value: item.name
                            });
                        });

                        return {
                            suggestions: list
                        };
                    },
                    onSelect: (s) => {
                        this.addLinkTeams(s.id, s.name);
                        $elemeneTeams.val('');
                        $elemeneTeams.focus();
                    },
                });

                $elemeneTeams.attr('autocomplete', 'espo-' + this.name);

                this.once('render', () => {
                    $elemeneTeams.autocomplete('dispose');
                });

                this.once('remove', () => {
                    $elemeneTeams.autocomplete('dispose');
                });

                var type = this.$el.find('select.search-type').val();

                if (type === 'isFromTeams') {
                    this.searchData.teamIdList.forEach(id => {
                        this.addLinkTeamsHtml(id, this.searchData.teamNameHash[id]);
                    });
                }
            }
        },

        deleteLinkTeams: function (id) {
            this.deleteLinkTeamsHtml(id);

            var index = this.searchData.teamIdList.indexOf(id);

            if (index > -1) {
                this.searchData.teamIdList.splice(index, 1);
            }

            delete this.searchData.teamNameHash[id];

            this.trigger('change');
        },

        addLinkTeams: function (id, name) {
            this.searchData.teamIdList = this.searchData.teamIdList || [];

            if (!~this.searchData.teamIdList.indexOf(id)) {
                this.searchData.teamIdList.push(id);
                this.searchData.teamNameHash[id] = name;
                this.addLinkTeamsHtml(id, name);

                this.trigger('change');
            }
        },

        deleteLinkTeamsHtml: function (id) {
            this.$el.find('.link-teams-container .link-' + id).remove();
        },

        addLinkTeamsHtml: function (id, name) {
            id = Handlebars.Utils.escapeExpression(id);
            name = Handlebars.Utils.escapeExpression(name);

            var $container = this.$el.find('.link-teams-container');
            var $el = $('<div />').addClass('link-' + id).addClass('list-group-item');

            $el.html(name + '&nbsp');

            $el.prepend(
                '<a role="button" class="pull-right" data-id="' + id + '" ' +
                'data-action="clearLinkTeams"><span class="fas fa-times"></a>'
            );

            $container.append($el);

            return $el;
        },

        fetchSearch: function () {
            var type = this.$el.find('select.search-type').val();

            if (type === 'isFromTeams') {
                return {
                    type: 'isUserFromTeams',
                    field: this.name,
                    value: this.searchData.teamIdList,
                    data: {
                        type: type,
                        teamIdList: this.searchData.teamIdList,
                        teamNameHash: this.searchData.teamNameHash,
                    }
                };
            }

            return Dep.prototype.fetchSearch.call(this);
        },
    });
});
