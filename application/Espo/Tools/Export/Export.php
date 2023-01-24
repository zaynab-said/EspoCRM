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

namespace Espo\Tools\Export;

use Espo\Tools\Export\Processor\Data as ProcessorData;
use Espo\Tools\Export\Processor\Params as ProcessorParams;

use Espo\ORM\Entity;
use Espo\ORM\BaseEntity;

use Espo\Entities\User;

use Espo\Entities\Attachment;

use Espo\Core\Acl;
use Espo\Core\Acl\GlobalRestriction;
use Espo\Core\Acl\Table;
use Espo\Core\FieldProcessing\ListLoadProcessor;
use Espo\Core\FieldProcessing\Loader\Params as LoaderParams;
use Espo\Core\FileStorage\Manager as FileStorageManager;
use Espo\Core\Record\ServiceContainer;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Utils\FieldUtil;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Metadata;

use Espo\{
    ORM\Collection,
    ORM\EntityManager,
};

use RuntimeException;
use LogicException;

class Export
{
    /**
     * @var ?Params
     */
    private ?Params $params = null;
    /**
     * @var ?Collection<Entity>
     */
    private ?Collection $collection = null;

    private $processorFactory;
    private $selectBuilderFactory;
    private $serviceContainer;
    private $acl;
    private $entityManager;
    private $metadata;
    private $fileStorageManager;
    private $listLoadProcessor;
    private $fieldUtil;
    private User $user;

    public function __construct(
        ProcessorFactory $processorFactory,
        SelectBuilderFactory $selectBuilderFactor,
        ServiceContainer $serviceContainer,
        Acl $acl,
        EntityManager $entityManager,
        Metadata $metadata,
        FileStorageManager $fileStorageManager,
        ListLoadProcessor $listLoadProcessor,
        FieldUtil $fieldUtil,
        User $user
    ) {
        $this->processorFactory = $processorFactory;
        $this->selectBuilderFactory = $selectBuilderFactor;
        $this->serviceContainer = $serviceContainer;
        $this->acl = $acl;
        $this->entityManager = $entityManager;
        $this->metadata = $metadata;
        $this->fileStorageManager = $fileStorageManager;
        $this->listLoadProcessor = $listLoadProcessor;
        $this->fieldUtil = $fieldUtil;
        $this->user = $user;
    }

    public function setParams(Params $params): self
    {
        $this->params = $params;

        return $this;
    }

    /**
     * @param Collection<Entity> $collection
     */
    public function setCollection(Collection $collection): self
    {
        $this->collection = $collection;

        return $this;
    }

    /**
     * Run export.
     */
    public function run(): Result
    {
        if (!$this->params) {
            throw new LogicException("No params set.");
        }

        $params = $this->params;

        $entityType = $params->getEntityType();

        $format = $params->getFormat() ?? 'csv';

        $processor = $this->processorFactory->create($format);

        $collection = $this->getCollection($params);

        $attributeList = $this->getAttributeList($params);

        $fieldList = $this->getFieldList($params, $processor);

        if ($fieldList !== null && method_exists($processor, 'addAdditionalAttributes')) {
            $processor->addAdditionalAttributes($entityType, $attributeList, $fieldList);
        }

        $dataResource = fopen('php://temp', 'w');

        if ($dataResource === false) {
            throw new RuntimeException("Could not open temp.");
        }

        $loaderParams = LoaderParams
            ::create()
            ->withSelect($attributeList);

        $recordService = $this->serviceContainer->get($entityType);

        foreach ($collection as $entity) {
            $this->listLoadProcessor->process($entity, $loaderParams);

            if (method_exists($recordService, 'loadAdditionalFieldsForExport')) {
                $recordService->loadAdditionalFieldsForExport($entity);
            }

            if (method_exists($processor, 'loadAdditionalFields') && $fieldList !== null) {
                $processor->loadAdditionalFields($entity, $fieldList);
            }

            $row = [];

            foreach ($attributeList as $attribute) {
                $value = $this->getAttributeFromEntity($entity, $attribute);

                $row[$attribute] = $value;
            }

            $line = base64_encode(serialize($row)) . \PHP_EOL;

            fwrite($dataResource, $line);
        }

        rewind($dataResource);

        $mimeType = $this->metadata->get(['app', 'export', 'formatDefs', $format, 'mimeType']);
        $fileExtension = $this->metadata->get(['app', 'export', 'formatDefs', $format, 'fileExtension']);

        $fileName = $params->getFileName();

        if ($fileName !== null) {
            $fileName = trim($fileName);
        }

        if ($fileName) {
            $fileName = $fileName . '.' . $fileExtension;
        }
        else {
            $fileName = "Export_{$entityType}." . $fileExtension;
        }

        $processorParams =
            (new ProcessorParams($fileName, $attributeList, $fieldList))
                ->withName($params->getName())
                ->withEntityType($params->getEntityType());

        $processorData = new ProcessorData($dataResource);

        $stream = $processor->process($processorParams, $processorData);

        fclose($dataResource);

        $attachment = $this->entityManager->getRepositoryByClass(Attachment::class)->getNew();

        $attachment->set('name', $fileName);
        $attachment->set('role', Attachment::ROLE_EXPORT_FILE);
        $attachment->set('type', $mimeType);
        $attachment->set('size', $stream->getSize());

        $this->entityManager->saveEntity($attachment, [
            'createdById' => $this->user->getId(),
        ]);

        $this->fileStorageManager->putStream($attachment, $stream);

        return new Result($attachment->getId());
    }

    /**
     * @return mixed
     */
    protected function getAttributeFromEntity(Entity $entity, string $attribute)
    {
        $methodName = 'getAttribute' . ucfirst($attribute). 'FromEntity';

        if (method_exists($this, $methodName)) {
            return $this->$methodName($entity);
        }

        $type = $entity->getAttributeType($attribute);

        if ($type === Entity::FOREIGN) {
            $type = $this->getForeignAttributeType($entity, $attribute) ?? $type;
        }

        switch ($type) {
            case Entity::JSON_OBJECT:
                if ($this->getAttributeParam($entity, $attribute, 'isLinkMultipleNameMap')) {
                    break;
                }

                $value = $entity->get($attribute);

                return Json::encode($value, \JSON_UNESCAPED_UNICODE);

            case Entity::JSON_ARRAY:
                if ($this->getAttributeParam($entity, $attribute, 'isLinkMultipleIdList')) {
                    break;
                }

                $value = $entity->get($attribute);

                if (is_array($value)) {
                    return Json::encode($value, \JSON_UNESCAPED_UNICODE);
                }

                return null;

            case Entity::PASSWORD:
                return null;
        }

        return $entity->get($attribute);
    }

    private function getForeignAttributeType(Entity $entity, string $attribute): ?string
    {
        $defs = $this->entityManager->getDefs();

        $entityDefs = $defs->getEntity($entity->getEntityType());

        $relation = $this->getAttributeParam($entity, $attribute, 'relation');
        $foreign = $this->getAttributeParam($entity, $attribute, 'foreign');

        if (!$relation) {
            return null;
        }

        if (!$foreign) {
            return null;
        }

        if (!is_string($foreign)) {
            return Entity::VARCHAR;
        }

        if (!$entityDefs->getRelation($relation)->hasForeignEntityType()) {
            return null;
        }

        $entityType = $entityDefs->getRelation($relation)->getForeignEntityType();

        if (!$defs->hasEntity($entityType)) {
            return null;
        }

        $foreignEntityDefs = $defs->getEntity($entityType);

        if (!$foreignEntityDefs->hasAttribute($foreign)) {
            return null;
        }

        return $foreignEntityDefs->getAttribute($foreign)->getType();
    }

    protected function checkAttributeIsAllowedForExport(
        Entity $entity,
        string $attribute,
        bool $exportAllFields = false
    ): bool {

        if ($this->getAttributeParam($entity, $attribute, 'notExportable')) {
            return false;
        }

        if (!$exportAllFields) {
            return true;
        }

        if ($this->getAttributeParam($entity, $attribute, 'isLinkMultipleIdList')) {
            return false;
        }

        if ($this->getAttributeParam($entity, $attribute, 'isLinkMultipleNameMap')) {
            return false;
        }

        if ($this->getAttributeParam($entity, $attribute, 'isLinkStub')) {
            return false;
        }

        return true;
    }

    /**
     * @return Collection<Entity>
     */
    private function getCollection(Params $params): Collection
    {
        if ($this->collection) {
            return $this->collection;
        }

        $entityType = $params->getEntityType();

        $searchParams = $params->getSearchParams();

        $builder = $this->selectBuilderFactory
            ->create()
            ->forUser($this->user)
            ->from($entityType)
            ->withSearchParams($searchParams);

        if ($params->applyAccessControl()) {
            $builder->withStrictAccessControl();
        }

        $query = $builder->build();

        /** @var Collection<Entity> */
        return $this->entityManager
            ->getRDBRepository($entityType)
            ->clone($query)
            ->sth()
            ->find();
    }

    /**
     * @return string[]
     */
    private function getAttributeList(Params $params): array
    {
       $list = [];

       $entityType = $params->getEntityType();

       $entityDefs = $this->entityManager
            ->getDefs()
            ->getEntity($entityType);

        $attributeListToSkip = $params->applyAccessControl() ?
            $this->acl->getScopeForbiddenAttributeList($entityType, Table::ACTION_READ) :
            $this->acl->getScopeRestrictedAttributeList($entityType, [
                GlobalRestriction::TYPE_FORBIDDEN,
                GlobalRestriction::TYPE_INTERNAL,
            ]);

        $attributeListToSkip[] = 'deleted';

        $seed = $this->entityManager->getNewEntity($entityType);

        $initialAttributeList = $params->getAttributeList();

        if ($params->getAttributeList() === null && $params->getFieldList() !== null) {
            $initialAttributeList = $this->getAttributeListFromFieldList($params);
        }

        if ($params->getAttributeList() === null && $params->getFieldList() === null) {
            $initialAttributeList = $entityDefs->getAttributeNameList();
        }

        assert($initialAttributeList !== null);

        foreach ($initialAttributeList as $attribute) {
            if (in_array($attribute, $attributeListToSkip)) {
                continue;
            }

            if (!$this->checkAttributeIsAllowedForExport($seed, $attribute, $params->allFields())) {
                continue;
            }

            $list[] = $attribute;
        }

        return $list;
    }

    /**
     * @return string[]
     * @throws RuntimeException
     */
    private function getAttributeListFromFieldList(Params $params): array
    {
        $entityType = $params->getEntityType();

        $fieldList = $params->getFieldList();

        if ($fieldList === null) {
            throw new RuntimeException();
        }

        $attributeList = [];

        foreach ($fieldList as $field) {
            $attributeList = array_merge(
                $attributeList,
                $this->fieldUtil->getAttributeList($entityType, $field)
            );
        }

        return $attributeList;
    }

    /**
     * @return ?string[]
     */
    private function getFieldList(Params $params, Processor $processor): ?array
    {
        $entityDefs = $this->entityManager
            ->getDefs()
            ->getEntity($params->getEntityType());

        $fieldList = $params->getFieldList();

        if ($params->allFields()) {
            $fieldList = $entityDefs->getFieldNameList();

            array_unshift($fieldList, 'id');
        }

        if ($fieldList === null) {
            return null;
        }

        foreach ($fieldList as $i => $field) {
            if ($field === 'id') {
                continue;
            }

            if (!$entityDefs->hasField($field)) {
                continue;
            }

            if ($entityDefs->getField($field)->getParam('exportDisabled')) {
                unset($fieldList[$i]);
            }
        }

        if (method_exists($processor, 'filterFieldList')) {
            $fieldList = $processor->filterFieldList($params->getEntityType(), $fieldList, $params->allFields());
        }

        return array_values($fieldList);
    }

    /**
     * @return mixed
     */
    private function getAttributeParam(Entity $entity, string $attribute, string $param)
    {
        if ($entity instanceof BaseEntity) {
            return $entity->getAttributeParam($attribute, $param);
        }

        $entityDefs = $this->entityManager
            ->getDefs()
            ->getEntity($entity->getEntityType());

        if (!$entityDefs->hasAttribute($attribute)) {
            return null;
        }

        return $entityDefs->getAttribute($attribute)->getParam($param);
    }
}
