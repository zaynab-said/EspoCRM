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

namespace Espo\Core\Utils\Database\Orm\Relations;

use RuntimeException;

class BelongsTo extends Base
{
    /**
     * @param string $linkName
     * @param string $entityName
     * @return array<string,mixed>
     */
    protected function load($linkName, $entityName)
    {
        $linkParams = $this->getLinkParams();

        $foreignEntityName = $this->getForeignEntityName();
        $foreignLinkName = $this->getForeignLinkName();

        if ($foreignEntityName === null) {
            throw new RuntimeException("No foreign-entity-type.");
        }

        $index = true;

        if (!empty($linkParams['noIndex'])) {
            $index = false;
        }

        $noForeignName = false;
        $foreign = null;

        if (!empty($linkParams['noForeignName'])) {
            $noForeignName = true;
        } else {
            if (!empty($linkParams['foreignName'])) {
                $foreign = $linkParams['foreignName'];
            } else {
                $foreign = $this->getForeignField('name', $foreignEntityName);
            }
        }

        if (!empty($linkParams['noJoin'])) {
            $fieldNameDefs = [
                'type' => 'varchar',
                'notStorable' => true,
                'relation' => $linkName,
                'foreign' => $this->getForeignField('name', $foreignEntityName),
            ];
        } else {
            $fieldNameDefs = [
                'type' => 'foreign',
                'relation' => $linkName,
                'foreign' => $foreign,
                'notStorable' => false
            ];
        }

        $data = [
            $entityName => [
                'fields' => [
                    $linkName.'Id' => [
                        'type' => 'foreignId',
                        'index' => $index
                    ]
                ],
                'relations' => [
                    $linkName => [
                        'type' => 'belongsTo',
                        'entity' => $foreignEntityName,
                        'key' => $linkName.'Id',
                        'foreignKey' => 'id',
                        'foreign' => $foreignLinkName
                    ]
                ]
            ]
        ];

        if (!$noForeignName) {
            $data[$entityName]['fields'][$linkName.'Name'] = $fieldNameDefs;
        }

        return $data;
    }
}
