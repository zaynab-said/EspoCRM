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

define('views/fields/complex-expression', ['views/fields/text'], function (Dep) {

    return Dep.extend({

        detailTemplate: 'fields/formula/detail',

        editTemplate: 'fields/formula/edit',

        height: 50,

        maxLineDetailCount: 80,

        maxLineEditCount: 200,

        events: {
            'click [data-action="addAttribute"]': function () {
                this.addAttribute();
            },
            'click [data-action="addFunction"]': function () {
                this.addFunction();
            },
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.height = this.options.height || this.params.height || this.height;

            this.maxLineDetailCount =
                this.options.maxLineDetailCount ||
                this.params.maxLineDetailCount ||
                this.maxLineDetailCount;

            this.maxLineEditCount =
                this.options.maxLineEditCount ||
                this.params.maxLineEditCount ||
                this.maxLineEditCount;

            this.targetEntityType =
                this.options.targetEntityType ||
                this.params.targetEntityType ||
                this.targetEntityType;

            this.containerId = 'editor-' + Math.floor((Math.random() * 10000) + 1).toString();

            if (this.mode === 'edit' || this.mode === 'detail') {
                this.wait(
                    this.requireAce()
                );
            }

            this.on('remove', () => {
                if (this.editor) {
                    this.editor.destroy();
                }
            });
        },

        requireAce: function () {
            return Espo.loader
                .requirePromise('lib!ace')
                .then(() => {
                    let list = [
                        Espo.loader.requirePromise('lib!ace-ext-language_tools'),
                    ];

                    if (this.getThemeManager().getParam('isDark')) {
                        list.push(
                            Espo.loader.requirePromise('lib!ace-theme-tomorrow_night')
                        );
                    }

                    return Promise.all(list);
                });
        },

        data: function () {
            var data = Dep.prototype.data.call(this);

            data.containerId = this.containerId;
            data.targetEntityType = this.targetEntityType;
            data.hasInsert = true;

            return data;
        },

        afterRender: function () {
            Dep.prototype.setup.call(this);

            this.$editor = this.$el.find('#' + this.containerId);

            if (
                this.$editor.length &
                (this.mode === 'edit' || this.mode === 'detail' || this.mode === 'list')
            ) {
                this.$editor
                    .css('fontSize', '14px');

                if (this.mode === 'edit') {
                    this.$editor.css('minHeight', this.height + 'px');
                }

                var editor = this.editor = ace.edit(this.containerId);

                editor.setOptions({
                    maxLines: this.mode === 'edit' ? this.maxLineEditCount : this.maxLineDetailCount,
                });

                if (this.getThemeManager().getParam('isDark')) {
                    editor.setOptions({
                        theme: 'ace/theme/tomorrow_night',
                    });
                }

                if (this.isEditMode()) {
                    editor.getSession().on('change', () => {
                        this.trigger('change', {ui: true});
                    });

                    editor.getSession().setUseWrapMode(true);
                }

                if (this.isReadMode()) {
                    editor.setReadOnly(true);
                    editor.renderer.$cursorLayer.element.style.display = "none";
                    editor.renderer.setShowGutter(false);
                }

                editor.setShowPrintMargin(false);
                editor.getSession().setUseWorker(false);
                editor.commands.removeCommand('find');
                editor.setHighlightActiveLine(false);

                //var JavaScriptMode = ace.require('ace/mode/javascript').Mode;

                //editor.session.setMode(new JavaScriptMode());

                if (!this.isReadMode()) {
                    this.initAutocomplete();
                }
            }
        },

        fetch: function () {
            var data = {};

            data[this.name] = this.editor.getValue();

            return data;
        },

        getFunctionDataList: function () {
            return this.getMetadata().get(['app', 'complexExpression', 'functionList']) || [];
        },

        initAutocomplete: function () {
            var functionItemList =
                this.getFunctionDataList()
                    .filter(item => {
                        return item.insertText;
                    });

            var attributeList = this.getAttributeList();

            var languageTools = ace.require("ace/ext/language_tools");

            this.editor.setOptions({
                enableBasicAutocompletion: true,
                enableLiveAutocompletion: true,
            });

            var completer = {
                identifierRegexps: [/[\\a-zA-Z0-9{}\[\]\.\$\'\"]/],

                getCompletions: function (editor, session, pos, prefix, callback) {

                    var matchedFunctionItemList = functionItemList
                        .filter((originalItem) => {
                            var text = originalItem.name.toLowerCase();

                            if (text.indexOf(prefix.toLowerCase()) === 0) {
                                return true;
                            }

                            return false;
                        });

                    var itemList = matchedFunctionItemList.map((item) => {
                        return {
                            caption: item.name + '()',
                            value: item.insertText,
                            meta: item.returnType || null,
                        };
                    });

                    var matchedAttributeList = attributeList
                        .filter((item) => {
                            if (item.indexOf(prefix) === 0) {
                                return true;
                            }

                            return false;
                        });

                    var itemAttributeList = matchedAttributeList.map((item) => {
                        return {
                            name: item,
                            value: item,
                            meta: 'attribute',
                        };
                    });

                    itemList = itemList.concat(itemAttributeList);

                    callback(null, itemList);
                }
            };

            this.editor.completers = [completer];
        },

        getAttributeList: function () {
            if (!this.targetEntityType) {
                return [];
            }

            var attributeList = this.getFieldManager()
                .getEntityTypeAttributeList(this.targetEntityType)
                .sort();

            attributeList.unshift('id');

            // @todo Skip not storable attributes.

            var links = this.getMetadata().get(['entityDefs', this.targetEntityType, 'links']) || {};

            var linkList = [];

            Object.keys(links).forEach((link) => {
                var type = links[link].type;

                if (!type) {
                    return;
                }

                if (~['hasMany', 'hasOne', 'belongsTo'].indexOf(type)) {
                    linkList.push(link);
                }
            });

            linkList.sort();

            linkList.forEach((link) => {
                var scope = links[link].entity;

                if (!scope) {
                    return;
                }

                if (links[link].disabled) {
                    return;
                }

                var linkAttributeList = this.getFieldManager()
                    .getEntityTypeAttributeList(scope)
                    .sort();

                linkAttributeList.forEach((item) => {
                    attributeList.push(link + '.' + item);
                });
            });

            return attributeList;
        },

        addAttribute: function () {
            this.createView('dialog', 'views/admin/formula/modals/add-attribute', {
                scope: this.targetEntityType,
                attributeList: this.getAttributeList(),
            }, view => {
                view.render();

                this.listenToOnce(view, 'add', attribute => {
                    this.editor.insert(attribute);

                    this.clearView('dialog');
                });
            });
        },

        addFunction: function () {
            this.createView('dialog', 'views/admin/complex-expression/modals/add-function', {
                scope: this.targetEntityType,
                functionDataList: this.getFunctionDataList(),
            }, view => {
                view.render();

                this.listenToOnce(view, 'add', string => {
                    this.editor.insert(string);

                    this.clearView('dialog');
                });
            });
        },
    });
});
