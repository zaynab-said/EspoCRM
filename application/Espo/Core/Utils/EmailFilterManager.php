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

namespace Espo\Core\Utils;

use Espo\Core\ORM\EntityManager;
use Espo\Core\Mail\FiltersMatcher;

use Espo\Entities\Email;
use Espo\Entities\EmailFilter;
use Espo\Entities\User;
use Espo\ORM\Query\Part\Expression;
use Espo\ORM\Query\Part\Order;

/**
 * Looks for any matching Email Filter for a given email and user.
 */
class EmailFilterManager
{
    /** @var array<string, iterable<EmailFilter>> */
    private array $data = [];
    private EntityManager $entityManager;
    private FiltersMatcher $filtersMatcher;

    public function __construct(EntityManager $entityManager, FiltersMatcher $filtersMatcher)
    {
        $this->entityManager = $entityManager;
        $this->filtersMatcher = $filtersMatcher;
    }

    public function getMatchingFilter(Email $email, string $userId): ?EmailFilter
    {
        if (!array_key_exists($userId, $this->data)) {
            $emailFilterList = $this->entityManager
                ->getRDBRepository(EmailFilter::ENTITY_TYPE)
                ->where([
                    'parentId' => $userId,
                    'parentType' => User::ENTITY_TYPE,
                ])
                ->order(
                    Order::createByPositionInList(
                        Expression::column('action'),
                        [
                            EmailFilter::ACTION_SKIP,
                            EmailFilter::ACTION_MOVE_TO_FOLDER,
                            EmailFilter::ACTION_NONE,
                        ]
                    )
                )
                ->find();

            $this->data[$userId] = $emailFilterList;
        }

        return $this->filtersMatcher->findMatch($email, $this->data[$userId]);
    }
}
