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

namespace Espo\Core\Portal\Acl\Table;

use Espo\Entities\{
    User,
    Portal,
};

use Espo\Core\{
    InjectableFactory,
    Acl\Table\CacheKeyProvider,
    Portal\Acl\Table,
    Portal\Acl\Table\CacheKeyProvider as PortalCacheKeyProvider,
    Acl\Table\RoleListProvider,
    Portal\Acl\Table\RoleListProvider as PortalRoleListProvider,
    Binding\BindingContainer,
    Binding\Binder,
    Binding\BindingData,
};

class TableFactory
{
    private InjectableFactory $injectableFactory;

    public function __construct(InjectableFactory $injectableFactory)
    {
        $this->injectableFactory = $injectableFactory;
    }

    /**
     * Create a table.
     */
    public function create(User $user, Portal $portal): Table
    {
        $bindingContainer = $this->createBindingContainer($user, $portal);

        return $this->injectableFactory->createWithBinding(Table::class, $bindingContainer);
    }

    private function createBindingContainer(User $user, Portal $portal): BindingContainer
    {
        $bindingData = new BindingData();

        $binder = new Binder($bindingData);

        $binder
            ->bindInstance(User::class, $user)
            ->bindInstance(Portal::class, $portal)
            ->bindImplementation(RoleListProvider::class, PortalRoleListProvider::class)
            ->bindImplementation(CacheKeyProvider::class, PortalCacheKeyProvider::class);

        return new BindingContainer($bindingData);
    }
}
