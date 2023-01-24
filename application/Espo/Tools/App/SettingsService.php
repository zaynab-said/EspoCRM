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

namespace Espo\Tools\App;

use Espo\Core\Authentication\Logins\Espo;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\ApplicationState;
use Espo\Core\Acl;
use Espo\Core\InjectableFactory;

use Espo\Core\DataManager;
use Espo\Core\FieldValidation\FieldValidationManager;
use Espo\Core\Utils\Currency\DatabasePopulator as CurrencyDatabasePopulator;

use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Core\Utils\Config\Access;

use Espo\Entities\Portal;
use Espo\Repositories\Portal as PortalRepository;

use stdClass;

class SettingsService
{
    private ApplicationState $applicationState;
    private Config $config;
    private ConfigWriter $configWriter;
    private Metadata $metadata;
    private Acl $acl;
    private EntityManager $entityManager;
    private DataManager $dataManager;
    private FieldValidationManager $fieldValidationManager;
    private InjectableFactory $injectableFactory;
    private Access $access;

    public function __construct(
        ApplicationState $applicationState,
        Config $config,
        ConfigWriter $configWriter,
        Metadata $metadata,
        Acl $acl,
        EntityManager $entityManager,
        DataManager $dataManager,
        FieldValidationManager $fieldValidationManager,
        InjectableFactory $injectableFactory,
        Access $access
    ) {
        $this->applicationState = $applicationState;
        $this->config = $config;
        $this->configWriter = $configWriter;
        $this->metadata = $metadata;
        $this->acl = $acl;
        $this->entityManager = $entityManager;
        $this->dataManager = $dataManager;
        $this->fieldValidationManager = $fieldValidationManager;
        $this->injectableFactory = $injectableFactory;
        $this->access = $access;
    }

    public function getConfigData(): stdClass
    {
        $data = $this->config->getAllNonInternalData();

        $this->filterDataByAccess($data);
        $this->filterData($data);
        $this->loadAdditionalParams($data);

        return $data;
    }

    public function getMetadataConfigData(): stdClass
    {
        $data = (object) [];

        $data->jsLibs = $this->metadata->get(['app', 'jsLibs']);

        unset($data->loginView);

        $loginView = $this->metadata->get(['clientDefs', 'App', 'loginView']);

        if ($loginView) {
            $data->loginView = $loginView;
        }

        $loginData = $this->getLoginData();

        if ($loginData) {
            $data->loginData = (object) $loginData;
        }

        return $data;
    }

    /**
     * @return ?array{
     *     handler: string,
     *     fallback: bool,
     *     data: stdClass,
     *     method: string,
     * }
     */
    private function getLoginData(): ?array
    {
        $method = $this->config->get('authenticationMethod') ?? Espo::NAME;

        /** @var array<string, mixed> $mData */
        $mData = $this->metadata->get(['authenticationMethods', $method, 'login']) ?? [];

        /** @var ?string $handler */
        $handler = $mData['handler'] ?? null;

        if (!$handler) {
            return null;
        }

        if ($this->applicationState->isPortal()) {
            /** @var ?bool $portal */
            $portal = $mData['portal'] ?? null;

            if ($portal === null) {
                /** @var ?string $portalConfigParam */
                $portalConfigParam = $mData['portalConfigParam'] ?? null;

                $portal = $portalConfigParam && $this->config->get($portalConfigParam);
            }

            if (!$portal) {
                return null;
            }
        }

        /** @var ?bool $fallback */
        $fallback = $mData['fallback'] ?? null;

        if ($fallback === null) {
            /** @var ?string $fallbackConfigParam */
            $fallbackConfigParam = $mData['fallbackConfigParam'] ?? null;

            $fallback = $fallbackConfigParam && $this->config->get($fallbackConfigParam);
        }

        /** @var stdClass $data */
        $data = (object) ($mData['fallbackConfigParam'] ?? []);

        return [
            'handler' => $handler,
            'fallback' => $fallback,
            'method' => $method,
            'data' => $data,
        ];
    }

    /**
     * @throws \Espo\Core\Exceptions\BadRequest
     * @throws Forbidden
     * @throws \Espo\Core\Exceptions\Error
     */
    public function setConfigData(stdClass $data): void
    {
        $user = $this->applicationState->getUser();

        if (!$user->isAdmin()) {
            throw new Forbidden();
        }

        $ignoreItemList = [];

        foreach ($this->access->getSystemParamList() as $item) {
            $ignoreItemList[] = $item;
        }

        if ($this->config->get('restrictedMode') && !$user->isSuperAdmin()) {
            foreach ($this->access->getSuperAdminParamList() as $item) {
                $ignoreItemList[] = $item;
            }
        }

        foreach ($ignoreItemList as $item) {
            unset($data->$item);
        }

        $entity = $this->entityManager->getNewEntity('Settings');

        $entity->set($data);
        $entity->setAsNotNew();

        $this->processValidation($entity, $data);

        if (
            (isset($data->useCache) && $data->useCache !== $this->config->get('useCache'))
        ) {
            $this->dataManager->clearCache();
        }

        $this->configWriter->setMultiple(
            get_object_vars($data)
        );

        $this->configWriter->save();

        if (isset($data->personNameFormat)) {
            $this->dataManager->clearCache();
        }

        if (isset($data->defaultCurrency) || isset($data->baseCurrency) || isset($data->currencyRates)) {
            $this->populateDatabaseWithCurrencyRates();
        }
    }

    private function loadAdditionalParams(stdClass $data): void
    {
        if ($this->applicationState->isPortal()) {
            $portal = $this->applicationState->getPortal();

            $this->getPortalRepository()->loadUrlField($portal);

            $data->siteUrl = $portal->get('url');
        }

        if (
            ($this->config->get('outboundEmailFromAddress') || $this->config->get('internalSmtpServer')) &&
            !$this->config->get('passwordRecoveryDisabled')
        ) {
            $data->passwordRecoveryEnabled = true;
        }
    }

    private function filterDataByAccess(stdClass $data): void
    {
        $user = $this->applicationState->getUser();

        $ignoreItemList = [];

        foreach ($this->access->getSystemParamList() as $item) {
            $ignoreItemList[] = $item;
        }

        foreach ($this->access->getInternalParamList() as $item) {
            $ignoreItemList[] = $item;
        }

        if (!$user->isAdmin() || $user->isSystem()) {
            foreach ($this->access->getAdminParamList() as $item) {
                $ignoreItemList[] = $item;
            }
        }

        if ($this->config->get('restrictedMode') && !$user->isSuperAdmin()) {
            // @todo Maybe add restriction level for non-super admins.
        }

        foreach ($ignoreItemList as $item) {
            unset($data->$item);
        }

        if ($user->isSystem()) {
            $globalItemList = $this->access->getGlobalParamList();

            foreach (array_keys(get_object_vars($data)) as $item) {
                if (!in_array($item, $globalItemList)) {
                    unset($data->$item);
                }
            }
        }
    }

    private function filterEntityTypeParams(stdClass $data): void
    {
        $entityTypeListParamList = $this->metadata->get(['app', 'config', 'entityTypeListParamList']) ?? [];

        /** @var string[] $scopeList */
        $scopeList = array_keys($this->metadata->get(['entityDefs'], []));

        foreach ($scopeList as $scope) {
            if (!$this->metadata->get(['scopes', $scope, 'acl'])) {
                continue;
            }

            if ($this->acl->tryCheck($scope)) {
                continue;
            }

            foreach ($entityTypeListParamList as $param) {
                $list = $data->$param ?? [];

                foreach ($list as $i => $item) {
                    if ($item === $scope) {
                        unset($list[$i]);
                    }
                }

                $data->$param = array_values($list);
            }
        }
    }

    private function populateDatabaseWithCurrencyRates(): void
    {
        $this->injectableFactory->create(CurrencyDatabasePopulator::class)->process();
    }

    private function filterData(stdClass $data): void
    {
        $user = $this->applicationState->getUser();

        if (!$user->isAdmin() && !$user->isSystem()) {
            $this->filterEntityTypeParams($data);
        }

        $fieldDefs = $this->metadata->get(['entityDefs', 'Settings', 'fields']);

        foreach ($fieldDefs as $field => $fieldParams) {
            if ($fieldParams['type'] === 'password') {
                unset($data->$field);
            }
        }

        if (empty($data->useWebSocket)) {
            unset($data->webSocketUrl);
        }

        if ($user->isSystem()) {
            return;
        }

        if ($user->isAdmin()) {
            return;
        }

        if (
            !$this->acl->checkScope('Email', 'create') ||
            !$this->config->get('outboundEmailIsShared')
        ) {
            unset($data->outboundEmailFromAddress);
            unset($data->outboundEmailFromName);
            unset($data->outboundEmailBccAddress);
        }
    }

    /**
     * @throws \Espo\Core\Exceptions\BadRequest
     */
    private function processValidation(Entity $entity, stdClass $data): void
    {
        $this->fieldValidationManager->process($entity, $data);
    }

    private function getPortalRepository(): PortalRepository
    {
        /** @var PortalRepository */
        return $this->entityManager->getRepository(Portal::ENTITY_TYPE);
    }
}
