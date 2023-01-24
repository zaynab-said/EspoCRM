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

namespace Espo\Modules\Crm\EntryPoints;

use Espo\Core\Exceptions\Error;
use Espo\Modules\Crm\Entities\Campaign;
use Espo\Modules\Crm\Entities\CampaignLogRecord;
use Espo\Modules\Crm\Entities\EmailQueueItem;
use Espo\Modules\Crm\Entities\MassEmail;
use Espo\Modules\Crm\Entities\TargetList;
use Espo\Repositories\EmailAddress as EmailAddressRepository;
use Espo\Modules\Crm\Tools\MassEmail\Util as MassEmailUtil;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\EntryPoint\EntryPoint;
use Espo\Core\EntryPoint\Traits\NoAuth;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\HookManager;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\ClientManager;
use Espo\Core\Utils\Hasher;
use Espo\Core\Utils\Metadata;

/**
 * @todo Use ActionRenderer.
 */
class SubscribeAgain implements EntryPoint
{
    use NoAuth;

    public function __construct(
        private EntityManager $entityManager,
        private ClientManager $clientManager,
        private HookManager $hookManager,
        private Metadata $metadata,
        private Hasher $hasher,
        private MassEmailUtil $util
    ) {}

    /**
     * @throws BadRequest
     * @throws Error
     * @throws NotFound
     */
    public function run(Request $request, Response $response): void
    {
        $id = $request->getQueryParam('id') ?? null;
        $emailAddress = $request->getQueryParam('emailAddress') ?? null;
        $hash = $request->getQueryParam('hash') ?? null;

        if ($emailAddress && $hash) {
            $this->processWithHash($emailAddress, $hash);

            return;
        }

        if (!$id || !is_string($id)) {
            throw new BadRequest();
        }

        $queueItemId = $id;

        /** @var ?EmailQueueItem $queueItem */
        $queueItem = $this->entityManager->getEntity(EmailQueueItem::ENTITY_TYPE, $queueItemId);

        if (!$queueItem) {
            throw new NotFound();
        }

        $campaign = null;
        $target = null;
        $massEmail = null;
        $massEmailId = $queueItem->getMassEmailId();

        if ($massEmailId) {
            /** @var ?MassEmail $massEmail */
            $massEmail = $this->entityManager->getEntity(MassEmail::ENTITY_TYPE, $massEmailId);
        }

        if ($massEmail) {
            $campaignId = $massEmail->getCampaignId();

            if ($campaignId) {
                $campaign = $this->entityManager->getEntityById(Campaign::ENTITY_TYPE, $campaignId);
            }

            $targetType = $queueItem->getTargetType();
            $targetId = $queueItem->getTargetId();

            $target = $this->entityManager->getEntityById($targetType, $targetId);

            if (!$target) {
                throw new NotFound();
            }

            if ($massEmail->get('optOutEntirely')) {
                $emailAddress = $target->get('emailAddress');

                if ($emailAddress) {
                    $ea = $this->getEmailAddressRepository()->getByAddress($emailAddress);

                    if ($ea) {
                        $ea->set('optOut', false);
                        $this->entityManager->saveEntity($ea);
                    }
                }
            }

            $link = $this->util->getLinkByEntityType($target->getEntityType());

            $targetListList = $this->entityManager
                ->getRDBRepository(MassEmail::ENTITY_TYPE)
                ->getRelation($massEmail, 'targetLists')
                ->find();

            foreach ($targetListList as $targetList) {
                $optedInResult = $this->entityManager
                    ->getRDBRepository(TargetList::ENTITY_TYPE)
                    ->updateRelation($targetList, $link, $target->getId(), ['optedOut' => false]);

                if ($optedInResult) {
                    $hookData = [
                       'link' => $link,
                       'targetId' => $targetId,
                       'targetType' => $targetType,
                    ];

                    $this->hookManager
                        ->process(TargetList::ENTITY_TYPE, 'afterCancelOptOut', $targetList, [], $hookData);
                }
            }

            $this->hookManager->process($target->getEntityType(), 'afterCancelOptOut', $target, [], []);

            $this->display(['queueItemId' => $queueItemId]);
        }

        if ($campaign && $target) {
            $logRecord = $this->entityManager
                ->getRDBRepository(CampaignLogRecord::ENTITY_TYPE)
                ->where([
                    'queueItemId' => $queueItemId,
                    'action' => CampaignLogRecord::ACTION_OPTED_OUT,
                ])
                ->order('createdAt', true)
                ->findOne();

            if ($logRecord) {
                $this->entityManager->removeEntity($logRecord);
            }
        }
    }

    /**
     * @param array<string, mixed> $actionData
     */
    protected function display(array $actionData): void
    {
        $data = [
            'actionData' => $actionData,
            'view' => $this->metadata->get(['clientDefs', 'Campaign', 'subscribeView']),
            'template' => $this->metadata->get(['clientDefs', 'Campaign', 'subscribeTemplate']),
        ];

        $runScript = "
            Espo.require('crm:controllers/unsubscribe', function (Controller) {
                var controller = new Controller(app.baseController.params, app.getControllerInjection());
                controller.masterView = app.masterView;
                controller.doAction('subscribeAgain', ".json_encode($data).");
            });
        ";

        $this->clientManager->display($runScript);
    }

    /**
     * @throws NotFound
     */
    protected function processWithHash(string $emailAddress, string $hash): void
    {
        $hash2 = $this->hasher->hash($emailAddress);

        if ($hash2 !== $hash) {
            throw new NotFound();
        }

        $repository = $this->getEmailAddressRepository();

        $ea = $repository->getByAddress($emailAddress);

        if (!$ea) {
            throw new NotFound();
        }

        $entityList = $repository->getEntityListByAddressId($ea->getId());

        if ($ea->isOptedOut()) {
            $ea->set('optOut', false);

            $this->entityManager->saveEntity($ea);

            foreach ($entityList as $entity) {
                $this->hookManager->process($entity->getEntityType(), 'afterCancelOptOut', $entity, [], []);
            }
        }

        $this->display([
            'emailAddress' => $emailAddress,
            'hash' => $hash,
        ]);
    }

    private function getEmailAddressRepository(): EmailAddressRepository
    {
        /** @var EmailAddressRepository */
        return $this->entityManager->getRepository('EmailAddress');
    }
}
