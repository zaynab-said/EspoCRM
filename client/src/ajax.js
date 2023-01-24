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

/**
 * @module ajax
 */
define('ajax', [], function () {

    /**
     * Ajax request functions.
     */
    let Ajax = Espo.Ajax = {

        /**
         * Options.
         *
         * @typedef {Object} Espo.Ajax~Options
         *
         * @property {Number} [timeout] A timeout.
         * @property {Object.<string,string>} [headers] A request headers.
         * @property {'xml'|'json'|'text'} [dataType] A data type.
         * @property {boolean} [local] If true, the API URL won't be prepended.
         * @property {string} [contentType] A content type.
         * @property {boolean} [fullResponse] To resolve with `module:ajax.XhrWrapper`.
         */

        /**
         * Request.
         *
         * @param {string} url An URL.
         * @param {string} type A method.
         * @param {any} [data] Data.
         * @param {Espo.Ajax~Options} [options] Options.
         * @returns {Promise<any>}
         */
        request: function (url, type, data, options) {
            options = options || {};

            options.type = type;
            options.url = url;

            if (data) {
                options.data = data;
            }

            return new AjaxPromise((resolve, reject) => {
                $.ajax(options)
                    .then((response, status, xhr) => {
                        let obj = options.fullResponse ?
                            new XhrWrapper(xhr) :
                            response;

                        resolve(obj);
                    })
                    .fail(xhr => {
                        reject(xhr);
                    });
            });
        },

        /**
         * POST request.
         *
         * @param {string} url An URL.
         * @param {any} [data] Data.
         * @param {Espo.Ajax~Options} [options] Options.
         * @returns {Promise<any>}
         */
        postRequest: function (url, data, options) {
            if (data) {
                data = JSON.stringify(data);
            }

            return Ajax.request(url, 'POST', data, options);
        },

        /**
         * PATCH request.
         *
         * @param {string} url An URL.
         * @param {any} [data] Data.
         * @param {Espo.Ajax~Options} [options] Options.
         * @returns {Promise<any>}
         */
        patchRequest: function (url, data, options) {
            if (data) {
                data = JSON.stringify(data);
            }

            return Ajax.request(url, 'PATCH', data, options);
        },

        /**
         * PUT request.
         *
         * @param {string} url An URL.
         * @param {any} [data] Data.
         * @param {Espo.Ajax~Options} [options] Options.
         * @returns {Promise<any>}
         */
        putRequest: function (url, data, options) {
            if (data) {
                data = JSON.stringify(data);
            }

            return Ajax.request(url, 'PUT', data, options);
        },

        /**
         * DELETE request.
         *
         * @param {string} url An URL.
         * @param {any} [data] Data.
         * @param {Espo.Ajax~Options} [options] Options.
         * @returns {Promise<any>}
         */
        deleteRequest: function (url, data, options) {
            if (data) {
                data = JSON.stringify(data);
            }

            return Ajax.request(url, 'DELETE', data, options);
        },

        /**
         * GET request.
         *
         * @param {string} url An URL.
         * @param {any} [data] Data.
         * @param {Espo.Ajax~Options} [options] Options.
         * @returns {Promise<any>}
         */
        getRequest: function (url, data, options) {
            return Ajax.request(url, 'GET', data, options);
        },
    };

    // For bc.
    class AjaxPromise extends Promise {
        fail(...args) {
            return this.catch(args[0]);
        }
        done(...args) {
            return this.then(args[0]);
        }
    }

    /**
     * @name module:ajax.XhrWrapper
     */
    class XhrWrapper {
        /**
         * @param {JQueryXHR} xhr
         */
        constructor(xhr) {
            this.xhr = xhr;
        }

        /**
         * @param {string} name
         * @return {string}
         */
        getResponseHeader(name) {
            return this.xhr.getResponseHeader(name);
        }

        /**
         * @return {Number}
         */
        getStatus() {
            return this.xhr.status;
        }

        /**
         * @return {*}
         */
        getResponseParsedBody() {
            return this.xhr.responseJSON;
        }

        /**
         * @return {string}
         */
        getResponseBody() {
            return this.xhr.responseText;
        }
    }

    return Ajax;
});
