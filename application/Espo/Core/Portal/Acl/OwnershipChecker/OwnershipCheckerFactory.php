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

namespace Espo\Core\Portal\Acl\OwnershipChecker;

use Espo\Core\{
    Utils\Metadata,
    InjectableFactory,
    Acl\Exceptions\NotImplemented,
    Acl\OwnershipChecker,
    Portal\AclManager as PortalAclManager,
    Portal\Acl\DefaultOwnershipChecker,
    Binding\BindingContainer,
    Binding\Binder,
    Binding\BindingData,
};

class OwnershipCheckerFactory
{
    /**
     * @var class-string<OwnershipChecker>
     */
    private $defaultClassName = DefaultOwnershipChecker::class;

    private Metadata $metadata;

    private InjectableFactory $injectableFactory;

    public function __construct(
        Metadata $metadata,
        InjectableFactory $injectableFactory
    ) {
        $this->metadata = $metadata;
        $this->injectableFactory = $injectableFactory;
    }

    /**
     * Create an ownership checker.
     *
     * @throws NotImplemented
     */
    public function create(string $scope, PortalAclManager $aclManager): OwnershipChecker
    {
        $className = $this->getClassName($scope);

        $bindingContainer = $this->createBindingContainer($aclManager);

        return $this->injectableFactory->createWithBinding($className, $bindingContainer);
    }

    /**
     * @return class-string<OwnershipChecker>
     */
    private function getClassName(string $scope): string
    {
        $className = $this->metadata->get(['aclDefs', $scope, 'portalOwnershipCheckerClassName']);

        if ($className) {
            /** @var class-string<OwnershipChecker> */
            return $className;
        }

        if (!$this->metadata->get(['scopes', $scope])) {
            throw new NotImplemented();
        }

        return $this->defaultClassName;
    }

    private function createBindingContainer(PortalAclManager $aclManager): BindingContainer
    {
        $bindingData = new BindingData();

        $binder = new Binder($bindingData);

        $binder->bindInstance(PortalAclManager::class, $aclManager);

        return new BindingContainer($bindingData);
    }
}
