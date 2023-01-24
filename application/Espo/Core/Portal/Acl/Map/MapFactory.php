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

namespace Espo\Core\Portal\Acl\Map;

use Espo\Entities\{
    User,
    Portal,
};

use Espo\Core\{
    InjectableFactory,
    Portal\Acl\Table as PortalTable,
    Portal\Acl\Map\MetadataProvider as PortalMetadataProvider,
    Portal\Acl\Map\CacheKeyProvider as PortalCacheKeyProvider,
    Acl\Map\MetadataProvider,
    Acl\Map\CacheKeyProvider,
    Acl\Map\Map,
    Acl\Table,
    Binding\BindingContainer,
    Binding\Binder,
    Binding\BindingData,
};

class MapFactory
{
    private $injectableFactory;

    public function __construct(InjectableFactory $injectableFactory)
    {
        $this->injectableFactory = $injectableFactory;
    }

    public function create(User $user, PortalTable $table, Portal $portal): Map
    {
        $bindingContainer = $this->createBindingContainer($user, $table, $portal);

        return $this->injectableFactory->createWithBinding(Map::class, $bindingContainer);
    }

    private function createBindingContainer(User $user, PortalTable $table, Portal $portal): BindingContainer
    {
        $bindingData = new BindingData();

        $binder = new Binder($bindingData);

        $binder
            ->bindInstance(User::class, $user)
            ->bindInstance(Table::class, $table)
            ->bindInstance(Portal::class, $portal)
            ->bindImplementation(MetadataProvider::class, PortalMetadataProvider::class)
            ->bindImplementation(CacheKeyProvider::class, PortalCacheKeyProvider::class);

        return new BindingContainer($bindingData);
    }
}
