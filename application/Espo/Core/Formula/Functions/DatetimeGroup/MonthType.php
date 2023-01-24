<?php
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

namespace Espo\Core\Formula\Functions\DateTimeGroup;

use Espo\Core\Di;

use Espo\Core\Formula\{
    Functions\BaseFunction,
    ArgumentList,
};

class MonthType extends BaseFunction implements Di\DateTimeAware
{
    use Di\DateTimeSetter;

    public function process(ArgumentList $args)
    {
        $args = $this->evaluate($args);

        if (count($args) < 1) {
            $this->throwTooFewArguments();
        }

        $value = $args[0];

        $timezone = null;
        if (count($args) > 1) {
             $timezone = $args[1];
        }

        if (empty($value)) {
            return 0;
        }

        if (strlen($value) > 11) {
            $resultString = $this->dateTime->convertSystemDateTime($value, $timezone, 'M');
        } else {
            $resultString = $this->dateTime->convertSystemDate($value, 'M');
        }

        return intval($resultString);
    }
}
