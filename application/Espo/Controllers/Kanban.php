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

namespace Espo\Controllers;

use Espo\Core\{
    Api\Request,
    Exceptions\BadRequest,
    Record\SearchParamsFetcher,
};

use Espo\Tools\Kanban\KanbanService;

use stdClass;

class Kanban
{
    private $service;

    private $searchParamsFetcher;

    public function __construct(KanbanService $service, SearchParamsFetcher $searchParamsFetcher)
    {
        $this->service = $service;
        $this->searchParamsFetcher = $searchParamsFetcher;
    }

    public function getActionGetData(Request $request): stdClass
    {
        /** @var string $entityType */
        $entityType = $request->getRouteParam('entityType');

        $searchParams = $this->searchParamsFetcher->fetch($request);

        $result = $this->service->getData($entityType, $searchParams);

        return (object) [
            'total' => $result->getTotal(),
            'list' => $result->getCollection()->getValueMapList(),
            'additionalData' => $result->getData(),
        ];
    }

    public function postActionStoreOrder(Request $request): bool
    {
        $data = $request->getParsedBody();

        $entityType = $data->entityType ?? null;
        $group = $data->group ?? null;
        $ids = $data->ids ?? null;

        if (empty($entityType) || !is_string($entityType)) {
            throw new BadRequest();
        }

        if (empty($group) || !is_string($group)) {
            throw new BadRequest();
        }

        if (!is_array($ids)) {
            throw new BadRequest();
        }

        $this->service->order($entityType, $group, $ids);

        return true;
    }
}
