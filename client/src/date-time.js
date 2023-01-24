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

define('date-time', [], function () {

    /**
     * A date-time util.
     *
     * @class
     * @name Class
     * @memberOf module:date-time
     */
    let DateTime = function () {};

    _.extend(DateTime.prototype, /** @lends module:date-time.Class# */{

        /**
         * A system date format.
         *
         * @type {string}
         */
        internalDateFormat: 'YYYY-MM-DD',

        /**
         * A system date-time format.
         *
         * @type {string}
         */
        internalDateTimeFormat: 'YYYY-MM-DD HH:mm',

        /**
         * A system date-time format including seconds.
         *
         * @type {string}
         */
        internalDateTimeFullFormat: 'YYYY-MM-DD HH:mm:ss',

        /**
         * A date format for a current user.
         *
         * @type {string}
         */
        dateFormat: 'MM/DD/YYYY',

        /**
         * A time format for a current user.
         *
         * @type {string}
         */
        timeFormat: 'HH:mm',

        /**
         * A time zone for a current user.
         *
         * @type {string|null}
         */
        timeZone: null,

        /**
         * A week start for a current user.
         *
         * @type {Number}
         */
        weekStart: 1,

        /**
         * @private
         */
        readableDateFormatMap: {
            'DD.MM.YYYY': 'DD MMM',
            'DD/MM/YYYY': 'DD MMM',
        },

        /**
         * @private
         */
        readableShortDateFormatMap: {
            'DD.MM.YYYY': 'D MMM',
            'DD/MM/YYYY': 'D MMM',
        },

        /**
         * Whether a time format has a meridian (am/pm).
         *
         * @returns {boolean}
         */
        hasMeridian: function () {
            return (new RegExp('A', 'i')).test(this.timeFormat);
        },

        /**
         * Get a date format.
         *
         * @returns {string}
         */
        getDateFormat: function () {
            return this.dateFormat;
        },

        /**
         * Get a time format.
         *
         * @returns {string}
         */
        getTimeFormat: function () {
            return this.timeFormat;
        },

        /**
         * Get a date-time format.
         *
         * @returns {string}
         */
        getDateTimeFormat: function () {
            return this.dateFormat + ' ' + this.timeFormat;
        },

        /**
         * Get a readable date format.
         *
         * @returns {string}
         */
        getReadableDateFormat: function () {
            return this.readableDateFormatMap[this.getDateFormat()] || 'MMM DD';
        },

        /**
         * Get a readable short date format.
         *
         * @returns {string}
         */
        getReadableShortDateFormat: function () {
            return this.readableShortDateFormatMap[this.getDateFormat()] || 'MMM D';
        },

        /**
         * Get a readable date-time format.
         *
         * @returns {string}
         */
        getReadableDateTimeFormat: function () {
            return this.getReadableDateFormat() + ' ' + this.timeFormat;
        },

        /**
         * Get a readable short date-time format.
         *
         * @returns {string}
         */
        getReadableShortDateTimeFormat: function () {
            return this.getReadableShortDateFormat() + ' ' + this.timeFormat;
        },

        /**
         * Convert a date from a display representation to system.
         *
         * @param {string} string A date value.
         * @returns {string} A system date value.
         */
        fromDisplayDate: function (string) {
            if (!string) {
                return null;
            }

            let m = moment(string, this.dateFormat);

            if (!m.isValid()) {
                return -1;
            }

            return m.format(this.internalDateFormat);
        },

        /**
         * Get a time-zone.
         *
         * @returns {string}
         */
        getTimeZone: function () {
            return this.timeZone ? this.timeZone : 'UTC';
        },

        /**
         * Convert a date from system to a display representation.
         *
         * @param {string} string A system date value.
         * @returns {string} A display date value.
         */
        toDisplayDate: function (string) {
            if (!string || (typeof string !== 'string')) {
                return '';
            }

            let m = moment(string, this.internalDateFormat);

            if (!m.isValid()) {
                return '';
            }

            return m.format(this.dateFormat);
        },

        /**
         * Convert a date-time from system to a display representation.
         *
         * @param {string} string A system date-tyime value.
         * @returns {string} A display date-time value.
         */
        fromDisplay: function (string) {
            if (!string) {
                return null;
            }

            let m;

            if (this.timeZone) {
                m = moment.tz(string, this.getDateTimeFormat(), this.timeZone).utc();
            }
            else {
                m = moment.utc(string, this.getDateTimeFormat());
            }

            if (!m.isValid()) {
                return -1;
            }

            return m.format(this.internalDateTimeFormat) + ':00';
        },

        /**
         * Convert a date-time from system to a display representation.
         *
         * @param {string} string A system date value.
         * @returns {string} A display date-time value.
         */
        toDisplay: function (string) {
            if (!string) {
                return '';
            }

            return this.toMoment(string).format(this.getDateTimeFormat());
        },

        /**
         * @deprecated Use `fromDisplay`.
         */
        fromDisplayDateTime: function (string) {
            return this.fromDisplay(string);
        },

        /**
         * @deprecated Use `toDisplay`.
         */
        toDisplayDateTime: function (string) {
            return this.toDisplay(string);
        },

        /**
         * Get a now moment.
         *
         * @returns {moment.Moment}
         */
        getNowMoment: function () {
            return moment().tz(this.getTimeZone())
        },

        /**
         * Convert a date to a moment.
         *
         * @param {string} string A date value in a system representation.
         * @returns {moment.Moment}
         */
        toMomentDate: function (string) {
            return moment.utc(string, this.internalDateFormat);
        },

        /**
         * Convert a date-time to a moment.
         *
         * @param {string} string A date-time value in a system representation.
         * @returns {moment.Moment}
         */
        toMoment: function (string) {
            let m = moment.utc(string, this.internalDateTimeFullFormat);

            if (this.timeZone) {
                m = m.tz(this.timeZone);
            }

            return m;
        },

        /**
         * Convert a date-time value from ISO to a system representation.
         *
         * @param {string} string
         * @returns {string} A date-time value in a system representation.
         */
        fromIso: function (string) {
            if (!string) {
                return '';
            }

            let m = moment(string).utc();

            return m.format(this.internalDateTimeFormat);
        },

        /**
         * Convert a date-time value from system to an ISO representation.
         *
         * @param string A date-time value in a system representation.
         * @returns {string} An ISO date-time value.
         */
        toIso: function (string) {
            if (!string) {
                return null;
            }

            return this.toMoment(string).format();
        },

        /**
         * Get a today date value in a system representation.
         *
         * @returns {string}
         */
        getToday: function () {
            return moment().tz(this.getTimeZone()).format(this.internalDateFormat);
        },

        /**
         * Get a date-time value in a system representation, shifted from now.
         *
         * @param {Number} shift A number to shift by.
         * @param {'minutes'|'hours'|'days'|'weeks'|'months'|'years'} type A shift unit.
         * @param {Number} [multiplicity] A number of minutes a value will be aliquot to.
         * @returns {string} A date-time value in a system representation
         */
        getDateTimeShiftedFromNow: function (shift, type, multiplicity) {
            if (!multiplicity) {
                return moment.utc().add(shift, type).format(this.internalDateTimeFormat);
            }

            let unix = moment().unix();

            unix = unix - (unix % (multiplicity * 60));

            return moment.unix(unix).utc().add(shift, type).format(this.internalDateTimeFormat);
        },

        /**
         * Get a date value in a system representation, shifted from today.
         *
         * @param {Number} shift A number to shift by.
         * @param {'days'|'weeks'|'months'|'years'} type A shift unit.
         * @returns {string} A date value in a system representation
         */
        getDateShiftedFromToday: function (shift, type) {
            return moment.tz(this.getTimeZone()).add(shift, type).format(this.internalDateFormat);
        },

        /**
         * Get a now date-time value in a system representation.
         *
         * @param {Number} [multiplicity] A number of minutes a value will be aliquot to.
         * @returns {string}
         */
        getNow: function (multiplicity) {
            if (!multiplicity) {
                return moment.utc().format(this.internalDateTimeFormat);
            }

            let unix = moment().unix();

            unix = unix - (unix % (multiplicity * 60));

            return moment.unix(unix).utc().format(this.internalDateTimeFormat);
        },

        /**
         * Set settings and preferences.
         *
         * @param {module:models/settings.Class} settings Settings.
         * @param {module:models/preferences.Class} preferences Preferences.
         * @internal
         */
        setSettingsAndPreferences: function (settings, preferences) {
            if (settings.has('dateFormat')) {
                this.dateFormat = settings.get('dateFormat');
            }

            if (settings.has('timeFormat')) {
                this.timeFormat = settings.get('timeFormat');
            }

            if (settings.has('timeZone')) {
                this.timeZone = settings.get('timeZone') || null;

                if (this.timeZone === 'UTC') {
                    this.timeZone = null;
                }
            }

            if (settings.has('weekStart')) {
                this.weekStart = settings.get('weekStart');
            }

            preferences.on('change', model => {
                if (model.has('dateFormat') && model.get('dateFormat')) {
                    this.dateFormat = model.get('dateFormat');
                }

                if (model.has('timeFormat') && model.get('timeFormat')) {
                    this.timeFormat = model.get('timeFormat');
                }

                if (model.has('timeZone') && model.get('timeZone')) {

                    this.timeZone = model.get('timeZone');
                }
                if (model.has('weekStart') && model.get('weekStart') !== -1) {
                    this.weekStart = model.get('weekStart');
                }

                if (this.timeZone === 'UTC') {
                    this.timeZone = null;
                }
            });
        },

        /**
         * Set a language.
         *
         * @param {module:language.Class} language A language.
         * @internal
         */
        setLanguage: function (language) {
            moment.updateLocale('en', {
                months: language.translate('monthNames', 'lists'),
                monthsShort: language.translate('monthNamesShort', 'lists'),
                weekdays: language.translate('dayNames', 'lists'),
                weekdaysShort: language.translate('dayNamesShort', 'lists'),
                weekdaysMin: language.translate('dayNamesMin', 'lists'),
            });

            moment.locale('en');
        },
    });

    return DateTime;
});
