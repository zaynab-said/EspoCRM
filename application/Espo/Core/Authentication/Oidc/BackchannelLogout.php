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

namespace Espo\Core\Authentication\Oidc;

use Espo\Core\Authentication\AuthToken\AuthToken;
use Espo\Core\Authentication\AuthToken\Manager as AuthTokenManager;
use Espo\Core\Authentication\Jwt\Exceptions\Invalid;
use Espo\Core\Authentication\Jwt\Exceptions\SignatureNotVerified;
use Espo\Core\Authentication\Jwt\Token;
use Espo\Core\Authentication\Jwt\Validator;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\Entities\AuthToken as AuthTokenEntity;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

/**
 * Compatible only with default Espo auth tokens.
 *
 * @todo Use a token-sessionId map to retrieve tokens.
 */
class BackchannelLogout
{
    private Log $log;
    private Validator $validator;
    private TokenValidator $tokenValidator;
    private Config $config;
    private EntityManager $entityManager;
    private AuthTokenManager $authTokenManger;

    public function __construct(
        Log $log,
        Validator $validator,
        TokenValidator $tokenValidator,
        Config $config,
        EntityManager $entityManager,
        AuthTokenManager $authTokenManger
    ) {
        $this->log = $log;
        $this->validator = $validator;
        $this->tokenValidator = $tokenValidator;
        $this->config = $config;
        $this->entityManager = $entityManager;
        $this->authTokenManger = $authTokenManger;
    }

    /**
     * @throws SignatureNotVerified
     * @throws Invalid
     */
    public function logout(string $rawToken): void
    {
        $token = Token::create($rawToken);

        $this->log->debug("OIDC logout: JWT header: " . $token->getHeaderRaw());
        $this->log->debug("OIDC logout: JWT payload: " . $token->getPayloadRaw());

        $this->validator->validate($token);
        $this->tokenValidator->validateSignature($token);
        $this->tokenValidator->validateFields($token);

        $usernameClaim = $this->config->get('oidcUsernameClaim');

        if (!$usernameClaim) {
            throw new Invalid("No username claim in config.");
        }

        $username = $token->getPayload()->get($usernameClaim);

        if (!$username) {
            throw new Invalid("No username claim `{$usernameClaim}` in token.");
        }

        $user = $this->entityManager
            ->getRDBRepositoryByClass(User::class)
            ->where([
                'userName' => $username,
            ])
            ->findOne();

        if (!$user) {
            return;
        }

        if ($user->isPortal()) {
            return;
        }

        $authTokenList = $this->entityManager
            ->getRDBRepositoryByClass(AuthTokenEntity::class)
            ->where([
                'userId' => $user->getId(),
                'isActive' => true,
            ])
            ->find();

        foreach ($authTokenList as $authToken) {
            assert($authToken instanceof AuthToken);

            $this->authTokenManger->inactivate($authToken);
        }
    }
}
