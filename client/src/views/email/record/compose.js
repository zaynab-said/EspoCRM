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

define('views/email/record/compose', ['views/record/edit', 'views/email/record/detail'], function (Dep, Detail) {

    return Dep.extend({

        isWide: true,

        sideView: false,

        setupBeforeFinal: function () {
            Dep.prototype.setupBeforeFinal.call(this);

            this.initialBody = null;
            this.initialIsHtml = null;

            if (!this.model.get('isHtml') && this.getPreferences().get('emailReplyForceHtml')) {
                let body = (this.model.get('body') || '').replace(/\n/g, '<br>');

                this.model.set('body', body, {silent: true});
                this.model.set('isHtml', true, {silent: true});
            }

            if (this.model.get('body')) {
                this.initialBody = this.model.get('body');
                this.initialIsHtml = this.model.get('isHtml');
            }

            if (!this.options.signatureDisabled && this.hasSignature()) {
                let addSignatureMethod = 'prependSignature';

                if (this.options.appendSignature) {
                    addSignatureMethod = 'appendSignature';
                }

                let body = this[addSignatureMethod](this.model.get('body') || '', this.model.get('isHtml'));

                this.model.set('body', body, {silent: true});
            }
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.isBodyChanged = false;

            this.listenTo(this.model, 'change:body', () => {
                this.isBodyChanged = true;
            });

            if (!this.options.removeAttachmentsOnSelectTemplate) {
                this.initialAttachmentsIds = this.model.get('attachmentsIds') || [];
                this.initialAttachmentsNames = this.model.get('attachmentsNames') || {};
            }

            this.initInsertTemplate();

            if (this.options.selectTemplateDisabled) {
                this.hideField('selectTemplate');
            }
        },

        initInsertTemplate: function () {
            this.listenTo(this.model, 'insert-template', data => {
                let body = this.model.get('body') || '';

                let bodyPlain = body.replace(/<br\s*\/?>/mg, '');

                bodyPlain = bodyPlain.replace(/<\/p\s*\/?>/mg, '');
                bodyPlain = bodyPlain.replace(/ /g, '');
                bodyPlain = bodyPlain.replace(/\n/g, '');

                let $div = $('<div>').html(bodyPlain);

                bodyPlain = $div.text();

                if (bodyPlain !== '' && this.isBodyChanged) {
                    this.confirm({
                            message: this.translate('confirmInsertTemplate', 'messages', 'Email'),
                            confirmText: this.translate('Yes')
                        })
                        .then(() => this.insertTemplate(data));

                    return;
                }

                this.insertTemplate(data);
            });
        },

        insertTemplate: function (data) {
            let body = data.body;

            if (this.hasSignature()) {
                body = this.appendSignature(body || '', data.isHtml);
            }

            if (this.initialBody && !this.isBodyChanged) {
                let initialBody = this.initialBody;

                if (data.isHtml !== this.initialIsHtml) {
                    if (data.isHtml) {
                        initialBody = this.plainToHtml(initialBody);
                    } else {
                        initialBody = this.htmlToPlain(initialBody);
                    }
                }

                body += initialBody;
            }

            this.model.set('isHtml', data.isHtml);

            if (data.subject) {
                this.model.set('name', data.subject);
            }

            this.model.set('body', '');
            this.model.set('body', body);

            if (!this.options.removeAttachmentsOnSelectTemplate) {
                this.initialAttachmentsIds.forEach((id) => {
                    if (data.attachmentsIds) {
                        data.attachmentsIds.push(id);
                    }

                    if (data.attachmentsNames) {
                        data.attachmentsNames[id] = this.initialAttachmentsNames[id] || id;
                    }
                });
            }

            this.model.set({
                attachmentsIds: data.attachmentsIds,
                attachmentsNames: data.attachmentsNames
            });

            this.isBodyChanged = false;
        },

        prependSignature: function (body, isHtml) {
            if (isHtml) {
                let signature = this.getSignature();

                if (body) {
                    signature += '';
                }

                return'<p><br></p>' + signature + body;
            }

            let signature = this.getPlainTextSignature();

            if (body) {
                signature += '\n';
            }

            return '\n\n' + signature + body;
        },

        appendSignature: function (body, isHtml) {
            if (isHtml) {
                let signature = this.getSignature();

                return  body + '' + signature;
            }

            let signature = this.getPlainTextSignature();

            return body + '\n\n' + signature;
        },

        hasSignature: function () {
            return !!this.getPreferences().get('signature');
        },

        getSignature: function () {
            return this.getPreferences().get('signature') || '';
        },

        getPlainTextSignature: function () {
            var value = this.getSignature().replace(/<br\s*\/?>/mg, '\n');

            value = $('<div>').html(value).text();

            return value;
        },

        afterSave: function () {
            Dep.prototype.afterSave.call(this);

            if (this.isSending && this.model.get('status') === 'Sent') {
                Espo.Ui.success(this.translate('emailSent', 'messages', 'Email'));
            }
        },

        send: function () {
            Detail.prototype.send.call(this);
        },

        saveDraft: function (options) {
            let model = this.model;

            model.set('status', 'Draft');

            var subjectView = this.getFieldView('subject');

            if (subjectView) {
                subjectView.fetchToModel();

                if (!model.get('name')) {
                    model.set('name', this.translate('No Subject', 'labels', 'Email'));
                }
            }

            return this.save(options);
        },

        htmlToPlain: function (text) {
            text = text || '';

            var value = text.replace(/<br\s*\/?>/mg, '\n');

            value = value.replace(/<\/p\s*\/?>/mg, '\n\n');

            var $div = $('<div>').html(value);

            $div.find('style').remove();
            $div.find('link[ref="stylesheet"]').remove();

            value =  $div.text();

            return value;
        },

        plainToHtml: function (html) {
            html = html || '';

            return html.replace(/\n/g, '<br>');
        },

        errorHandlerSendingFail: function (data) {
            Detail.prototype.errorHandlerSendingFail.call(this, data);
        },

        focusForCreate: function () {
            if (!this.model.get('to')) {
                this.$el
                    .find('.field[data-name="to"] input')
                    .focus();

                return;
            }

            if (!this.model.get('subject')) {
                this.$el
                    .find('.field[data-name="subject"] input')
                    .focus();

                return;
            }

            if (this.model.get('isHtml')) {
                let $div = this.$el.find('.field[data-name="body"] .note-editable');

                if (!$div.length) {
                    return;
                }

                $div.focus();

                return;
            }

            this.$el
                .find('.field[data-name="body"] textarea')
                .prop('selectionEnd', 0)
                .focus();
        },
    });
});
