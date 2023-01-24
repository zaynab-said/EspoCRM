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

namespace Espo\Core;

use Espo\Core\Utils\Config\MissingDefaultParamsSaver as ConfigMissingDefaultParamsSaver;

use Espo\Core\Exceptions\Error;
use Espo\Core\ORM\EntityManagerProxy;
use Espo\Core\Utils\File\Manager as FileManager;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\Util;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Core\Utils\Metadata\OrmMetadataData;
use Espo\Core\Utils\Database\Schema\SchemaProxy;
use Espo\Core\Utils\Log;
use Espo\Core\Utils\Module;
use Espo\Core\Rebuild\RebuildActionProcessor;

use Throwable;

/**
 * Clears cache, rebuilds the application.
 */
class DataManager
{
    private Config $config;
    private ConfigWriter $configWriter;
    private EntityManagerProxy $entityManager;
    private Metadata $metadata;
    private OrmMetadataData $ormMetadataData;
    private HookManager $hookManager;
    private SchemaProxy $schemaProxy;
    private Log $log;
    private Module $module;
    private RebuildActionProcessor $rebuildActionProcessor;
    private ConfigMissingDefaultParamsSaver $configMissingDefaultParamsSaver;
    private FileManager $fileManager;

    private string $cachePath = 'data/cache';

    public function __construct(
        EntityManagerProxy $entityManager,
        Config $config,
        ConfigWriter $configWriter,
        Metadata $metadata,
        OrmMetadataData $ormMetadataData,
        HookManager $hookManager,
        SchemaProxy $schemaProxy,
        Log $log,
        Module $module,
        RebuildActionProcessor $rebuildActionProcessor,
        ConfigMissingDefaultParamsSaver $configMissingDefaultParamsSaver,
        FileManager $fileManager
    ) {
        $this->entityManager = $entityManager;
        $this->config = $config;
        $this->configWriter = $configWriter;
        $this->metadata = $metadata;
        $this->ormMetadataData = $ormMetadataData;
        $this->hookManager = $hookManager;
        $this->schemaProxy = $schemaProxy;
        $this->log = $log;
        $this->module = $module;
        $this->rebuildActionProcessor = $rebuildActionProcessor;
        $this->configMissingDefaultParamsSaver = $configMissingDefaultParamsSaver;
        $this->fileManager = $fileManager;
    }

    /**
     * Rebuild the system with metadata, database and cache clearing.
     *
     * @param ?string[] $entityList
     * @throws Error
     */
    public function rebuild(?array $entityList = null): void
    {
        $this->clearCache();
        $this->disableHooks();
        $this->checkModules();
        $this->rebuildMetadata();
        $this->populateConfigParameters();
        $this->rebuildDatabase($entityList);
        $this->rebuildActionProcessor->process();
        $this->configMissingDefaultParamsSaver->process();
        $this->enableHooks();
    }

    /**
     * Clear cache.
     *
     * @throws Error
     */
    public function clearCache(): void
    {
        $this->module->clearCache();

        $result = $this->fileManager->removeInDir($this->cachePath);

        if (!$result) {
            throw new Error("Error while clearing cache");
        }

        $this->updateCacheTimestamp();
    }

    /**
     * Rebuild database.
     *
     * @param ?string[] $entityList
     * @throws Error
     */
    public function rebuildDatabase(?array $entityList = null): void
    {
        $schema = $this->schemaProxy;

        try {
            $result = $schema->rebuild($entityList);
        }
        catch (Throwable $e) {
            $result = false;

            $this->log->error(
                "Failed to rebuild database schema. Details: ". $e->getMessage() .
                " at " . $e->getFile() . ":" . $e->getLine()
            );
        }

        if (!$result) {
            throw new Error("Error while rebuilding database. See log file for details.");
        }

        $databaseType = strtolower($schema->getDatabaseHelper()->getDatabaseType());

        if (
            !$this->config->get('actualDatabaseType') ||
            $this->config->get('actualDatabaseType') !== $databaseType
        ) {
            $this->configWriter->set('actualDatabaseType', $databaseType);
        }

        $databaseVersion = $schema->getDatabaseHelper()->getDatabaseVersion();

        if (
            !$this->config->get('actualDatabaseVersion') ||
            $this->config->get('actualDatabaseVersion') !== $databaseVersion
        ) {
            $this->configWriter->set('actualDatabaseVersion', $databaseVersion);
        }

        $this->configWriter->updateCacheTimestamp();
        $this->configWriter->save();
    }

    /**
     * Rebuild metadata.
     *
     * @throws Error
     */
    public function rebuildMetadata(): void
    {
        $metadata = $this->metadata;

        $metadata->init(true);

        $ormData = $this->ormMetadataData->getData(true);

        $this->entityManager->getMetadata()->updateData();

        $this->updateCacheTimestamp();

        if (empty($ormData)) {
            throw new Error("Error while rebuilding metadata. See log file for details.");
        }
    }

    /**
     * Update cache timestamp.
     */
    public function updateCacheTimestamp(): void
    {
        $this->configWriter->updateCacheTimestamp();
        $this->configWriter->save();
    }

    /**
     * Update app timestamp.
     */
    public function updateAppTimestamp(): void
    {
        $this->configWriter->set('appTimestamp', time());
        $this->configWriter->save();
    }

    private function populateConfigParameters(): void
    {
        $this->setFullTextConfigParameters();
        $this->setCryptKeyConfigParameter();

        if (!$this->config->get('appTimestamp')) {
            $this->updateAppTimestamp();
        }

        $this->configWriter->save();
    }

    private function setFullTextConfigParameters(): void
    {
        $platform = $this->config->get('database.platform') ?? null;
        $driver = $this->config->get('database.driver') ?? '';

        if ($platform !== 'Mysql' && strpos($driver, 'mysql') === false) {
            return;
        }

        $fullTextSearchMinLength = null;

        $sql = "SHOW VARIABLES LIKE 'ft_min_word_len'";

        $row = $this->entityManager
            ->getSqlExecutor()
            ->execute($sql)
            ->fetch();

        if ($row && isset($row['Value'])) {
            $fullTextSearchMinLength = intval($row['Value']);
        }

        $this->configWriter->set('fullTextSearchMinLength', $fullTextSearchMinLength);
    }

    private function setCryptKeyConfigParameter(): void
    {
        if ($this->config->get('cryptKey')) {
            return;
        }

        $cryptKey = Util::generateSecretKey();

        $this->configWriter->set('cryptKey', $cryptKey);
    }

    private function disableHooks(): void
    {
        $this->hookManager->disable();
    }

    private function enableHooks(): void
    {
        $this->hookManager->enable();
    }

    /**
     * @throws Error
     */
    private function checkModules(): void
    {
        $moduleNameList = $this->module->getList();

        if (count(array_unique($moduleNameList)) !== count($moduleNameList)) {
            throw new Error(
                "There is a same module in both `custom` and `internal` directories. " .
                "Should be only in one location."
            );
        }
    }
}
