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

namespace Espo\Modules\Crm\Repositories;

use Espo\Modules\Crm\Entities\Account as AccountEntity;
use Espo\Modules\Crm\Entities\Contact as ContactEntity;
use Espo\Modules\Crm\Entities\Lead as LeadEntity;
use Espo\Modules\Crm\Entities\Task as TaskEntity;
use Espo\ORM\Entity;
use Espo\Core\Repositories\Event as EventRepository;
use Espo\Core\ORM\Entity as CoreEntity;

class Task extends EventRepository
{
    protected $reminderSkippingStatusList = [
        TaskEntity::STATUS_COMPLETED,
        TaskEntity::STATUS_CANCELED,
    ];

    /**
     * @todo Move to hooks.
     */
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (!$entity->isNew() && $entity->isAttributeChanged('parentId')) {
            $entity->set('accountId', null);
            $entity->set('contactId', null);
            $entity->set('accountName', null);
            $entity->set('contactName', null);
        }

        if ($entity->isAttributeChanged('parentId') || $entity->isAttributeChanged('parentType')) {
            $this->processParentChanged($entity);
        }

        parent::beforeSave($entity, $options);
    }

    protected function processParentChanged(Entity $entity): void
    {
        $parent = null;

        $parentId = $entity->get('parentId');
        $parentType = $entity->get('parentType');

        if ($parentId && $parentType && $this->entityManager->hasRepository($parentType)) {
            $columnList = ['id', 'name'];

            $defs = $this->entityManager->getMetadata()->getDefs();

            if ($defs->getEntity($parentType)->hasAttribute('accountId')) {
                $columnList[] = 'accountId';
            }

            if ($defs->getEntity($parentType)->hasAttribute('contactId')) {
                $columnList[] = 'contactId';
            }

            if ($parentType === LeadEntity::ENTITY_TYPE) {
                $columnList[] = 'status';
                $columnList[] = 'createdAccountId';
                $columnList[] = 'createdAccountName';
                $columnList[] = 'createdContactId';
                $columnList[] = 'createdContactName';
            }

            $parent = $this->entityManager
                ->getRDBRepository($parentType)
                ->select($columnList)
                ->where(['id' => $parentId])
                ->findOne();
        }

        $accountId = null;
        $contactId = null;
        $accountName = null;
        $contactName = null;

        if ($parent) {
            if ($parent instanceof AccountEntity) {
                $accountId = $parent->getId();
                $accountName = $parent->get('name');
            }
            else if (
                $parent instanceof LeadEntity &&
                $parent->getStatus() == LeadEntity::STATUS_CONVERTED
            ) {
                if ($parent->get('createdAccountId')) {
                    $accountId = $parent->get('createdAccountId');
                    $accountName = $parent->get('createdAccountName');
                }

                if ($parent->get('createdContactId')) {
                    $contactId = $parent->get('createdContactId');
                    $contactName = $parent->get('createdContactName');
                }
            }
            else if ($parent instanceof ContactEntity) {
                $contactId = $parent->getId();
                $contactName = $parent->get('name');
            }

            if (
                !$accountId &&
                $parent->get('accountId') &&
                $parent instanceof CoreEntity &&
                $parent->getRelationParam('account', 'entity') === AccountEntity::ENTITY_TYPE
            ) {
                $accountId = $parent->get('accountId');
            }

            if (
                !$contactId &&
                $parent->get('contactId') &&
                $parent instanceof CoreEntity &&
                $parent->getRelationParam('contact', 'entity') === ContactEntity::ENTITY_TYPE
            ) {
                $contactId = $parent->get('contactId');
            }
        }

        $entity->set('accountId', $accountId);
        $entity->set('accountName', $accountName);

        $entity->set('contactId', $contactId);
        $entity->set('contactName', $contactName);

        if (
            $entity->get('accountId') &&
            !$entity->get('accountName')
        ) {
            $account = $this->entityManager
                ->getRDBRepository(AccountEntity::ENTITY_TYPE)
                ->select(['id', 'name'])
                ->where(['id' => $entity->get('accountId')])
                ->findOne();

            if ($account) {
                $entity->set('accountName', $account->get('name'));
            }
        }

        if (
            $entity->get('contactId') &&
            !$entity->get('contactName')
        ) {
            $contact = $this->entityManager
                ->getRDBRepository(ContactEntity::ENTITY_TYPE)
                ->select(['id', 'name'])
                ->where(['id' => $entity->get('contactId')])
                ->findOne();

            if ($contact) {
                $entity->set('contactName', $contact->get('name'));
            }
        }
    }
}
