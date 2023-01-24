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

define('views/record/kanban', ['views/record/list'], function (Dep) {

    /**
     * A kanban record view.
     *
     * @class
     * @name Class
     * @extends module:views/record/list.Class
     * @memberOf module:views/record/kanban
     */
    return Dep.extend(/** @lends module:views/record/kanban.Class# */{

        template: 'record/kanban',

        type: 'kanban',

        name: 'kanban',

        scope: null,

        showCount: true,

        buttonList: [],

        headerDisabled: false,

        layoutName: 'kanban',

        portalLayoutDisabled: false,

        itemViewName: 'views/record/kanban-item',

        rowActionsView: 'views/record/row-actions/default-kanban',

        minColumnWidthPx: 220,

        events: {
            'click a.link': function (e) {
                if (e.ctrlKey || e.metaKey || e.shiftKey) {
                    return;
                }

                e.stopPropagation();

                if (!this.scope || this.selectable) {
                    return;
                }

                e.preventDefault();

                let id = $(e.currentTarget).data('id');
                let model = this.collection.get(id);

                let scope = this.getModelScope(id);

                let options = {
                    id: id,
                    model: model,
                };

                if (this.options.keepCurrentRootUrl) {
                    options.rootUrl = this.getRouter().getCurrentUrl();
                }

                this.getRouter().navigate('#' + scope + '/view/' + id, {trigger: false});
                this.getRouter().dispatch(scope, 'view', options);
            },
            'click [data-action="groupShowMore"]': function (e) {
                let $target = $(e.currentTarget);

                let group = $target.data('name');

                this.groupShowMore(group);
            },
            'click .action': function (e) {
                Espo.Utils.handleAction(this, e);
            },
            'mouseenter th.group-header': function (e) {
                let group = $(e.currentTarget).attr('data-name');

                this.showPlus(group);
            },
            'mouseleave th.group-header': function (e) {
                let group = $(e.currentTarget).attr('data-name');

                this.hidePlus(group);
            },
            'click [data-action="createInGroup"]': function (e) {
                let group = $(e.currentTarget).attr('data-group');

                this.actionCreateInGroup(group);
            },
            'mousedown .kanban-columns td': function (e) {
                if ($(e.originalEvent.target).closest('.item').length) {
                    return;
                }

                this.initBackDrag(e.originalEvent);
            },
            /**
             * @param {JQueryMouseEventObject} e
             * @this module:views/record/kanban.Class
             */
            'auxclick a.link': function (e) {
                let isCombination = e.button === 1 && (e.ctrlKey || e.metaKey);

                if (!isCombination) {
                    return;
                }

                let $target = $(e.currentTarget);

                let id = $target.attr('data-id');

                if (!id) {
                    return;
                }

                if (this.quickDetailDisabled) {
                    return;
                }

                let $quickView = $target.parent().closest(`[data-id="${id}"]`)
                    .find(`ul.list-row-dropdown-menu[data-id="${id}"] a[data-action="quickView"]`);

                if (!$quickView.length) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();

                this.actionQuickView({id: id});
            },
        },

        showMore: true,

        quickDetailDisabled: false,

        quickEditDisabled: false,

        listLayout: null,

        _internalLayout: null,

        buttonsDisabled: false,

        backDragStarted: true,

        data: function () {
            return {
                scope: this.scope,
                header: this.header,
                topBar: this.displayTotalCount || this.buttonList.length && !this.buttonsDisabled,
                showCount: this.showCount && this.collection.total > 0,
                buttonList: this.buttonList,
                displayTotalCount: this.displayTotalCount && this.collection.total >= 0,
                totalCount: this.collection.total,
                statusList: this.statusList,
                groupDataList: this.groupDataList,
                minTableWidthPx: this.minColumnWidthPx * this.statusList.length,
                isEmptyList: this.collection.models.length === 0,
                totalCountFormatted: this.getNumberUtil().formatInt(this.collection.total),
                isCreatable: this.isCreatable,
            };
        },

        init: function () {
            this.listLayout = this.options.listLayout || this.listLayout;
            this.type = this.options.type || this.type;

            this.layoutName = this.options.layoutName || this.layoutName || this.type;

            this.rowActionsView = _.isUndefined(this.options.rowActionsView) ?
                this.rowActionsView :
                this.options.rowActionsView;

            if (this.massActionsDisabled && !this.selectable) {
                this.checkboxes = false;
            }

            this.rowActionsDisabled = this.options.rowActionsDisabled || this.rowActionsDisabled;

            if ('buttonsDisabled' in this.options) {
                this.buttonsDisabled = this.options.buttonsDisabled;
            }
        },

        getModelScope: function (id) {
            return this.scope;
        },

        setup: function () {
            if (typeof this.collection === 'undefined') {
                throw new Error('Collection has not been injected into Record.List view.');
            }

            this.layoutLoadCallbackList = [];

            this.entityType = this.collection.name || null;
            this.scope = this.options.scope || this.entityType;

            this.buttonList = Espo.Utils.clone(this.buttonList);

            if ('showCount' in this.options) {
                this.showCount = this.options.showCount;
            }

            this.displayTotalCount = this.showCount && this.getConfig().get('displayListViewRecordCount');

            this.minColumnWidthPx = this.getConfig().get('kanbanMinColumnWidth') || this.minColumnWidthPx;

            if ('displayTotalCount' in this.options) {
                this.displayTotalCount = this.options.displayTotalCount;
            }

            if (this.getUser().isPortal() && !this.portalLayoutDisabled) {
                if (
                    this.getMetadata()
                        .get(['clientDefs', this.scope, 'additionalLayouts', this.layoutName + 'Portal'])
                ) {
                    this.layoutName += 'Portal';
                }
            }

            this.orderDisabled = this.getMetadata().get(['scopes', this.scope, 'kanbanOrderDisabled']);

            if (this.getUser().isPortal()) {
                this.orderDisabled = true;
            }

            this.statusField = this.getMetadata().get(['scopes', this.scope, 'statusField']);

            if (!this.statusField) {
                throw new Error("No status field for entity type '" + this.scope + "'.");
            }

            this.statusList = Espo.Utils.clone(this.getMetadata().get(
                ['entityDefs', this.scope, 'fields', this.statusField, 'options'])
            );

            let statusIgnoreList = this.getMetadata().get(['scopes', this.scope, 'kanbanStatusIgnoreList']) || [];

            this.statusList = this.statusList.filter((item) => {
                if (~statusIgnoreList.indexOf(item)) {
                    return;
                }

                return true;
            });

            this.seedCollection = this.collection.clone();
            this.seedCollection.reset();
            this.seedCollection.url = this.scope;
            this.seedCollection.maxSize = this.collection.maxSize;
            this.seedCollection.name = this.collection.name;
            this.seedCollection.orderBy = this.collection.defaultOrderBy;
            this.seedCollection.order = this.collection.defaultOrder;

            this.listenTo(this.collection, 'sync', () => {
                if (this.hasView('modal') && this.getView('modal').isRendered()) {
                    return;
                }

                this.buildRows(() => {
                    this.render();
                });
            });

            this.collection.listenTo(
                this.collection,
                'change:' + this.statusField,
                this.onChangeGroup.bind(this),
                this
            );

            this.buildRows();

            this.on('remove', () => {
                $(window).off('resize.kanban-a-' + this.cid);
                $(window).off('scroll.kanban-' + this.cid);
                $(window).off('resize.kanban-' + this.cid);
            });

            if (
                this.getAcl().checkScope(this.entityType, 'edit') &&
                !~this.getAcl().getScopeForbiddenFieldList(this.entityType, 'edit').indexOf(this.statusField) &&
                !this.getMetadata().get(['clientDefs', this.scope, 'editDisabled'])
            ) {
                this.statusFieldIsEditable = true;
            } else {
                this.statusFieldIsEditable = false;
            }

            this.isCreatable = this.statusFieldIsEditable && this.getAcl().check(this.entityType, 'create');

            this.getHelper().processSetupHandlers(this, 'record/kanban');
        },

        afterRender: function () {
            let $window = $(window);

            this.$listKanban = this.$el.find('.list-kanban');
            this.$content = $('#content');

            this.$groupColumnList = this.$listKanban.find('.group-column-list');

            this.$container = this.$el.find('.list-kanban-container');

            $window.off('resize.kanban-a-' + this.cid);
            $window.on('resize.kanban-a-' + this.cid, () => this.adjustMinHeight());

            this.$container.on('scroll', () => this.syncHeadScroll());

            this.adjustMinHeight();

            if (this.statusFieldIsEditable) {
                this.initSortable();
            }

            this.initStickableHeader();

            this.$showMore = this.$el.find('.group-column .show-more');

            this.plusElementMap = {};

            this.statusList.forEach(status => {
                let value = status.replace(/"/g, '\\"');

                this.plusElementMap[status] = this.$el
                    .find('.kanban-head .create-button[data-group="' + value + '"]');
            });
        },

        initStickableHeader: function () {
            let $container = this.$headContainer = this.$el.find('.kanban-head-container');
            let topBarHeight = this.getThemeManager().getParam('navbarHeight') || 30;

            let screenWidthXs = this.getThemeManager().getParam('screenWidthXs');

            let $middle = this.$el.find('.kanban-columns-container');
            let $window = $(window);

            let $block = $('<div>')
                .addClass('kanban-head-placeholder')
                .html('&nbsp;')
                .hide()
                .insertAfter($container);

            $window.off('scroll.kanban-' + this.cid);
            $window.on('scroll.kanban-' + this.cid, () => {
                controlSticking();
            });

            $window.off('resize.kanban-' + this.cid);
            $window.on('resize.kanban-' + this.cid, () => controlSticking());

            let controlSticking = () => {
                let width = $middle.width();

                if ($(window.document).width() < screenWidthXs) {
                    $container.removeClass('sticked');
                    $container.css('width', '');
                    $block.hide();
                    $container.show();

                    $container.get(0).scrollLeft = 0;
                    $container.children(0).css('width', '');

                    return;
                }

                let stickTop = this.$listKanban.position().top - topBarHeight;

                let edge = $middle.position().top + $middle.outerHeight(true);
                let scrollTop = $window.scrollTop();

                if (scrollTop < edge) {
                    if (scrollTop > stickTop) {
                        let containerWidth = this.$container.width() - 3;

                        $container.children().css('width', width);

                        $container.css('width', containerWidth + 'px');

                        if (!$container.hasClass('sticked')) {
                            $container.addClass('sticked');
                            $block.show();
                        }
                    } else {
                        $container.css('width', '');

                        if ($container.hasClass('sticked')) {
                            $container.removeClass('sticked');
                            $block.hide();
                        }
                    }

                    $container.show();

                    this.syncHeadScroll();

                    return;
                }

                $container.css('width', width + 'px');
                $container.hide();

                $block.show();

                $container.get(0).scrollLeft = 0;
                $container.children().css('width', '');
            };
        },

        initSortable: function () {
            let $list = this.$groupColumnList;

            $list.find('> .item').on('touchstart', (e) => {
                e.originalEvent.stopPropagation();
            });

            let orderDisabled = this.orderDisabled;

            let $groupColumnList = this.$el.find('.group-column-list');

            $list.sortable({
                distance: 10,
                connectWith: '.group-column-list',
                cancel: '.btn-group *',
                containment: this.getSelector(),
                scroll: false,
                over: function (e, ui) {
                    $(this).addClass('drop-hover');
                },
                out: function (e, ui) {
                    $(this).removeClass('drop-hover');
                },
                sort: (e, ui) => {
                    if (!this.blockScrollControl) {
                        this.controlHorizontalScroll(e.originalEvent);
                    }
                },
                start: (e, ui) => {
                    $groupColumnList.addClass('drop-active');

                    $list.sortable('refreshPositions');

                    $(ui.item)
                        .find('.btn-group.open > .dropdown-toggle')
                        .parent()
                        .removeClass('open');

                    this.draggedGroupFrom = $(ui.item).closest('.group-column-list').data('name');
                    this.$showMore.addClass('hidden');

                    this.sortIsStarted = true;
                    this.sortWasCentered = false;

                    this.$draggable = ui.item;
                },
                stop: (e, ui) => {
                    this.blockScrollControl = false;
                    this.sortIsStarted = false;
                    this.$draggable = null;

                    let $item = $(ui.item);

                    this.$el.find('.group-column-list').removeClass('drop-active');

                    let group = $item.closest('.group-column-list').data('name');
                    let id = $item.data('id');

                    let draggedGroupFrom = this.draggedGroupFrom;

                    this.draggedGroupFrom = null;

                    this.$showMore.removeClass('hidden');

                    if (group !== draggedGroupFrom) {
                        let model = this.collection.get(id);

                        if (!model) {
                            $list.sortable('cancel');

                            return;
                        }

                        let attributes = {};

                        attributes[this.statusField] = group;

                        this.handleAttributesOnGroupChange(model, attributes, group);

                        $list.sortable('disable');

                        model
                            .save(attributes, {
                                patch: true,
                                isDrop: true,
                            })
                            .then(() => {
                                Espo.Ui.success(this.translate('Saved'));

                                $list.sortable('destroy');

                                this.initSortable();

                                this.moveModelBetweenGroupCollections(model, draggedGroupFrom, group);

                                if (!orderDisabled) {
                                    this.reOrderGroup(group);
                                    this.storeGroupOrder(group);
                                }

                                this.rebuildGroupDataList();
                            })
                            .catch(() => {
                                $list.sortable('cancel');
                                $list.sortable('enable');
                            });

                        return;
                    }

                    if (orderDisabled) {
                        $list.sortable('cancel');
                        $list.sortable('enable');

                        return;
                    }

                    this.reOrderGroup(group);
                    this.storeGroupOrder(group);
                    this.rebuildGroupDataList();
                },
            });
        },

        storeGroupOrder: function (group) {
            Espo.Ajax.postRequest('Kanban/action/storeOrder', {
                entityType: this.entityType,
                group: group,
                ids: this.getGroupOrderFromDom(group),
            });
        },

        getGroupOrderFromDom: function (group) {
            let ids = [];

            let $group = this.$el.find('.group-column-list[data-name="'+group+'"]');

            $group.children().each((i, el) => {
                ids.push($(el).data('id'));
            });

            return ids;
        },

        reOrderGroup: function (group) {
            let groupCollection = this.getGroupCollection(group);
            let ids = this.getGroupOrderFromDom(group);

            let modelMap = {};

            groupCollection.models.forEach((m) => {
                modelMap[m.id] = m;
            });

            while (groupCollection.models.length) {
                groupCollection.pop({silent: true});
            }

            ids.forEach(id => {
                let model = modelMap[id];

                if (!model) {
                    return;
                }

                groupCollection.add(model, {silent: true});
            });
        },

        rebuildGroupDataList: function () {
            this.groupDataList.forEach(item => {
                item.dataList = [];

                for (let model of item.collection.models) {
                    item.dataList.push({
                        key: model.id,
                        id: model.id,
                    });
                }
            });
        },

        moveModelBetweenGroupCollections: function (model, groupFrom, groupTo) {
            let collection = this.getGroupCollection(groupFrom);

            if (!collection) {
                return;
            }

            collection.remove(model.id, {silent: true});

            collection = this.getGroupCollection(groupTo);

            if (!collection) {
                return;
            }

            collection.add(model, {silent: true});
        },

        handleAttributesOnGroupChange: function (model, attributes, group) {},

        adjustMinHeight: function () {
            if (
                this.collection.models.length === 0 ||
                !this.$container
            ) {
                return;
            }

            let height = this.getHelper()
                .calculateContentContainerHeight(this.$el.find('.kanban-columns-container'));

            let containerEl = this.$container.get(0);

            if (containerEl.scrollWidth > containerEl.clientWidth) {
                height -= 18;
            }

            if (height < 100) {
                height = 100;
            }

            this.$listKanban.find('td.group-column').css({
                minHeight: height + 'px',
            });
        },

        getListLayout: function (callback) {
            if (this.listLayout) {
                callback.call(this, this.listLayout);

                return;
            }

            this._loadListLayout((listLayout) => {
                this.listLayout = listLayout;
                callback.call(this, listLayout);
            });
        },

        getSelectAttributeList: function (callback) {
            Dep.prototype.getSelectAttributeList.call(this, (attributeList) => {
                if (attributeList) {
                    if (!~attributeList.indexOf(this.statusField)) {
                        attributeList.push(this.statusField);
                    }
                }

                callback(attributeList);
            });
        },

        buildRows: function (callback) {
            let groupList = (this.collection.dataAdditional || {}).groupList || [];

            this.collection.reset();

            this.collection.subCollectionList = [];

            this.wait(true);

            this.groupDataList = [];

            let count = 0;
            let loadedCount = 0;

            this.getListLayout((listLayout) => {
                this.listLayout = listLayout;

                groupList.forEach((item, i) => {
                    let collection = this.seedCollection.clone();

                    this.listenTo(collection, 'destroy', (model, attributes, o) => {
                        if (o.fromList) {
                            return;
                        }

                        this.removeRecordFromList(model.id);
                    });

                    collection.total = item.total;

                    collection.url = this.collection.url;

                    collection.where = this.collection.where;
                    collection.name = this.seedCollection.name;
                    collection.maxSize = this.seedCollection.maxSize;
                    collection.orderBy = this.seedCollection.orderBy;
                    collection.order = this.seedCollection.order;

                    collection.whereAdditional = [
                        {
                            field: this.statusField,
                            type: 'equals',
                            value: item.name,
                        }
                    ];

                    collection.groupName = item.name;
                    collection.set(item.list);

                    this.collection.subCollectionList.push(collection);

                    this.collection.add(collection.models);

                    let itemDataList = [];

                    collection.models.forEach(model => {
                        count ++;

                        itemDataList.push({
                            key: model.id,
                            id: model.id,
                        });
                    });

                    let nextStyle = null;

                    if (i < groupList.length - 1) {
                        nextStyle = this.getMetadata()
                            .get(['entityDefs', this.scope, 'fields', this.statusField,
                                'style', groupList[i + 1].name]);
                    }

                    let o = {
                        name: item.name,
                        label: this.getLanguage().translateOption(item.name, this.statusField, this.scope),
                        dataList: itemDataList,
                        collection: collection,
                        isLast: i === groupList.length - 1,
                        hasShowMore: collection.total > collection.length || collection.total === -1,
                        style: this.getMetadata().get(
                            ['entityDefs', this.scope, 'fields', this.statusField, 'style', item.name]
                        ),
                        nextStyle: nextStyle,
                    };

                    this.groupDataList.push(o);
                });

                if (count === 0) {
                    this.wait(false);

                    if (callback) {
                        callback();
                    }

                    return;
                }

                this.groupDataList.forEach(groupItem => {
                    groupItem.dataList.forEach((item, j) => {
                        let model = groupItem.collection.get(item.id);

                        this.buildRow(j, model, () => {
                            loadedCount++;

                            if (loadedCount === count) {
                                this.wait(false);

                                if (callback) {
                                    callback();
                                }
                            }
                        });
                    });
                });
            });
        },

        buildRow: function (i, model, callback) {
            let key = model.id;

            this.createView(key, this.itemViewName, {
                model: model,
                el: this.getSelector() + ' .item[data-id="'+model.id+'"]',
                itemLayout: this.listLayout,
                rowActionsDisabled: this.rowActionsDisabled,
                rowActionsView: this.rowActionsView,
                setViewBeforeCallback: this.options.skipBuildRows && !this.isRendered(),
                statusFieldIsEditable: this.statusFieldIsEditable,
            }, callback);
        },

        removeRecordFromList: function (id) {
            this.collection.remove(id);

            if (this.collection.total > 0) {
                this.collection.total--;
            }

            this.totalCount = this.collection.total;

            this.$el.find('.total-count-span').text(this.totalCount.toString());

            this.clearView(id);

            this.$el.find('.item[data-id="'+id+'"]').remove();

            this.collection.subCollectionList.forEach(collection => {
                if (collection.get(id)) {
                    collection.remove(id);
                }
            });

            for (let groupItem of this.groupDataList) {
                for (let j = 0; j < groupItem.dataList.length; j++) {
                    let item = groupItem.dataList[j];

                    if (item.id !== id) {
                        continue;
                    }

                    groupItem.dataList.splice(j, 1);

                    if (groupItem.collection.total > 0) {
                        groupItem.collection.total--;
                    }

                    groupItem.hasShowMore = groupItem.collection.total > groupItem.collection.length ||
                        groupItem.collection.total === -1;

                    break;
                }
            }
        },

        onChangeGroup: function (model, value, o) {
            let id = model.id;
            let group = model.get(this.statusField);

            this.collection.subCollectionList.forEach((collection) => {
                if (collection.get(id)) {
                    collection.remove(id);

                    if (collection.total > 0) {
                        collection.total--;
                    }
                }
            });

            let dataItem;

            for (let groupItem of this.groupDataList) {
                for (let j = 0; j < groupItem.dataList.length; j++) {
                    let item = groupItem.dataList[j];

                    if (item.id === id) {
                        dataItem = item;
                        groupItem.dataList.splice(j, 1);

                        break;
                    }
                }
            }

            if (!group) {
                return;
            }

            if (o.isDrop) {
                return;
            }

            for (let groupItem of this.groupDataList) {
                if (groupItem.name !== group) {
                    continue;
                }

                groupItem.collection.unshift(model);
                groupItem.collection.total++;

                if (dataItem) {
                    groupItem.dataList.unshift(dataItem);

                    groupItem.hasShowMore = groupItem.collection.total > groupItem.collection.length ||
                        groupItem.collection.total === -1;
                }
            }

            let $item = this.$el.find('.item[data-id="' + id + '"]');
            let $column = this.$el.find('.group-column[data-name="' + group + '"] .group-column-list');

            if ($column.length) {
                $column.prepend($item);
            } else {
                $item.remove();
            }

            if (!this.orderDisabled) {
                this.storeGroupOrder(group);
            }
        },

        groupShowMore: function (group) {
            let groupItem;

            for (let i in this.groupDataList) {
                groupItem = this.groupDataList[i];

                if (groupItem.name === group) {
                    break;
                }

                groupItem = null;
            }

            if (!groupItem) {
                return;
            }

            let collection = groupItem.collection;

            let $list = this.$el.find('.group-column-list[data-name="'+group+'"]');
            let $showMore = this.$el.find('.group-column[data-name="'+group+'"] .show-more');

            collection.data.select = this.collection.data.select;

            this.showMoreRecords({}, collection, $list, $showMore, () => {
                this.noRebuild = false;

                collection.models.forEach((model) => {
                    if (this.collection.get(model.id)) {
                        return;
                    }

                    this.collection.add(model);

                    groupItem.dataList.push({
                        key: model.id,
                        id: model.id,
                    });
                });
            });
        },

        getDomRowItem: function (id) {
            return this.$el.find('.item[data-id="'+id+'"]');
        },

        getRowContainerHtml: function (id) {
            return $('<div>')
                .attr('data-id', id)
                .addClass('item')
                .get(0).outerHTML;
        },

        actionMoveOver: function (data) {
            let model = this.collection.get(data.id);

            this.createView('moveOverDialog', 'views/modals/kanban-move-over', {
                model: model,
                statusField: this.statusField,
            }, (view) => {
                view.render();
            });
        },

        getGroupCollection: function (group) {
            let collection = null;

            this.collection.subCollectionList.forEach((itemCollection) => {
                if (itemCollection.groupName === group) {
                    collection = itemCollection;
                }
            });

            return collection;
        },

        showPlus: function (group) {
            let $el = this.plusElementMap[group];

            if (!$el) {
                return;
            }

            $el.removeClass('hidden');
        },

        hidePlus: function (group) {
            let $el = this.plusElementMap[group];

            if (!$el) {
                return;
            }

            $el.addClass('hidden');
        },

        actionCreateInGroup: function (group) {
            let attributes = {};

            attributes[this.statusField] = group;

            let viewName = this.getMetadata().get('clientDefs.' + this.scope + '.modalViews.edit') ||
                'views/modals/edit';

            let options = {
                attributes: attributes,
                scope: this.scope,
            };

            this.createView('quickCreate', viewName, options, (view) => {
                view.render();

                this.listenToOnce(view, 'after:save', () => {
                    this.collection.fetch();
                });
            });
        },

        initBackDrag: function (e) {
            this.backDragStarted = true;

            let containerEl = this.$container.get(0);

            containerEl.style.cursor = 'grabbing';
            containerEl.style.userSelect = 'none';

            let $document = $(document);

            let startLeft = containerEl.scrollLeft;
            let startX = e.clientX;

            $document.on('mousemove.' + this.cid, (e) => {
                let dx = e.originalEvent.clientX - startX;

                containerEl.scrollLeft = startLeft - dx;

                this.syncHeadScroll();
            });

            $document.one('mouseup.' + this.cid, () => {
                this.stopBackDrag();
            });
        },

        stopBackDrag: function () {
            this.$container.get(0).style.cursor = 'default';
            this.$container.get(0).style.userSelect = 'none';

            $(document).off('mousemove.' + this.cid);
        },

        syncHeadScroll: function () {
            if (!this.$headContainer.hasClass('sticked')) {
                return;
            }

            this.$headContainer.get(0).scrollLeft = this.$container.get(0).scrollLeft;
        },

        controlHorizontalScroll: function (e) {
            if (!this.sortIsStarted) {
                return;
            }

            if (!this.$draggable) {
                return;
            }

            let draggableRect = this.$draggable.get(0).getBoundingClientRect();

            let itemLeft = draggableRect.left;
            let itemRight = draggableRect.right;

            let containerEl = this.$container.get(0);

            let rect = containerEl.getBoundingClientRect();

            let marginSens = 70;
            let step = 2;
            let interval = 5;
            let marginSensStepRatio = 4;
            let stepRatio = 3;

            let isRight = rect.right - marginSens < itemRight &&
                containerEl.scrollLeft + containerEl.offsetWidth < containerEl.scrollWidth;

            let isLeft = rect.left + marginSens > itemLeft &&
                containerEl.scrollLeft > 0;

            this.$groupColumnList.sortable('refreshPositions');

            if (isRight && this.sortWasCentered) {
                let margin = rect.right - itemRight;

                if (margin < marginSens / marginSensStepRatio) {
                    step *= stepRatio;
                }

                let stepActual = Math.min(step, containerEl.offsetWidth - containerEl.scrollLeft);

                containerEl.scrollLeft = containerEl.scrollLeft + stepActual;

                this.syncHeadScroll();

                if (containerEl.scrollLeft + containerEl.offsetWidth === containerEl.scrollWidth) {
                    this.blockScrollControl = false;

                    return;
                }

                this.blockScrollControl = true;

                setTimeout(() => this.controlHorizontalScroll(e), interval);

                return;
            }

            if (isLeft && this.sortWasCentered) {
                let margin = - (rect.left - itemLeft);

                if (margin < marginSens / marginSensStepRatio) {
                    step *= stepRatio;
                }

                let stepActual = Math.min(step, containerEl.scrollLeft);

                containerEl.scrollLeft = containerEl.scrollLeft - stepActual;

                this.syncHeadScroll();

                if (containerEl.scrollLeft === 0) {
                    this.blockScrollControl = false;

                    return;
                }

                this.blockScrollControl = true;

                setTimeout(() => this.controlHorizontalScroll(e), interval);

                return;
            }

            if (this.blockScrollControl && !isLeft && !isRight) {
                this.blockScrollControl = false;
            }

            if (!isLeft && !isRight) {
                this.sortWasCentered = true;
            }
        },
    });
});
