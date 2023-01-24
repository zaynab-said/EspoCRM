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

namespace Espo\Core\Authentication;

use Espo\Core\Authentication\Logout\Params as LogoutParams;
use Espo\Repositories\UserData as UserDataRepository;
use Espo\Entities\Portal;
use Espo\Entities\User;
use Espo\Entities\AuthLogRecord;
use Espo\Entities\AuthToken as AuthTokenEntity;
use Espo\Entities\UserData;

use Espo\Core\Authentication\Result\FailReason;
use Espo\Core\Authentication\TwoFactor\LoginFactory as TwoFactorLoginFactory;
use Espo\Core\Authentication\AuthToken\Manager as AuthTokenManager;
use Espo\Core\Authentication\AuthToken\Data as AuthTokenData;
use Espo\Core\Authentication\AuthToken\AuthToken;
use Espo\Core\Authentication\Hook\Manager as HookManager;
use Espo\Core\Authentication\Login\Data as LoginData;

use Espo\Core\ApplicationUser;
use Espo\Core\ApplicationState;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Utils\Log;
use Espo\Core\ORM\EntityManagerProxy;
use Espo\Core\Exceptions\ServiceUnavailable;

use RuntimeException;

/**
 * Handles authentication. The entry point of the auth process.
 */
class Authentication
{
    private const LOGOUT_USERNAME = '**logout';

    private const HEADER_ESPO_AUTHORIZATION = 'Espo-Authorization';
    private const HEADER_CREATE_TOKEN_SECRET = 'Espo-Authorization-Create-Token-Secret';
    private const HEADER_BY_TOKEN = 'Espo-Authorization-By-Token';
    private const HEADER_ANOTHER_USER = 'X-Another-User';
    private const HEADER_LOGOUT_REDIRECT_URL = 'X-Logout-Redirect-Url';

    private const COOKIE_AUTH_TOKEN_SECRET = 'auth-token-secret';

    private bool $allowAnyAccess;
    private ?Portal $portal = null;

    private ApplicationUser $applicationUser;
    private ApplicationState $applicationState;
    private ConfigDataProvider $configDataProvider;
    private EntityManagerProxy $entityManager;
    private LoginFactory $loginFactory;
    private TwoFactorLoginFactory $twoFactorLoginFactory;
    private AuthTokenManager $authTokenManager;
    private HookManager $hookManager;
    private LogoutFactory $logoutFactory;
    private Log $log;

    public function __construct(
        ApplicationUser $applicationUser,
        ApplicationState $applicationState,
        ConfigDataProvider $configDataProvider,
        EntityManagerProxy $entityManagerProxy,
        LoginFactory $loginFactory,
        TwoFactorLoginFactory $twoFactorLoginFactory,
        AuthTokenManager $authTokenManager,
        HookManager $hookManager,
        Log $log,
        LogoutFactory $logoutFactory,
        bool $allowAnyAccess = false
    ) {
        $this->allowAnyAccess = $allowAnyAccess;

        $this->applicationUser = $applicationUser;
        $this->applicationState = $applicationState;
        $this->configDataProvider = $configDataProvider;
        $this->entityManager = $entityManagerProxy;
        $this->loginFactory = $loginFactory;
        $this->twoFactorLoginFactory = $twoFactorLoginFactory;
        $this->authTokenManager = $authTokenManager;
        $this->hookManager = $hookManager;
        $this->logoutFactory = $logoutFactory;
        $this->log = $log;
    }

    /**
     * Process logging in.
     * Note: This method can change the state of the object (by setting the `portal` property.).
     *
     * @throws ServiceUnavailable
     */
    public function login(AuthenticationData $data, Request $request, Response $response): Result
    {
        $username = $data->getUsername();
        $password = $data->getPassword();
        $authenticationMethod = $data->getMethod();
        $byTokenOnly = $data->byTokenOnly();

        if (
            $authenticationMethod &&
            !$this->configDataProvider->authenticationMethodIsApi($authenticationMethod)
        ) {
            $this->log
                ->warning("AUTH: Trying to use not allowed authentication method '{$authenticationMethod}'.");

            return $this->processFail(Result::fail(FailReason::METHOD_NOT_ALLOWED), $data, $request);
        }

        $this->hookManager->processBeforeLogin($data, $request);

        if (!$authenticationMethod && $password === null) {
            $this->log->error("AUTH: Trying to login w/o password.");

            return Result::fail(FailReason::NO_PASSWORD);
        }

        $authToken = null;

        if (!$authenticationMethod) {
            $authToken = $this->authTokenManager->get($password);
        }

        if ($authToken && $authToken->getSecret()) {
            $sentSecret = $request->getCookieParam(self::COOKIE_AUTH_TOKEN_SECRET);

            if ($sentSecret !== $authToken->getSecret()) {
                $authToken = null;
            }
        }

        $authTokenIsFound = $authToken !== null;

        if ($authToken && !$authToken->isActive()) {
            $authToken = null;
        }

        if ($authToken) {
            $authTokenCheckResult = $this->processAuthTokenCheck($authToken);

            if (!$authTokenCheckResult) {
                return Result::fail(FailReason::DENIED);
            }
        }

        $byTokenAndUsername = $request->getHeader(self::HEADER_BY_TOKEN) === 'true';

        if ($authenticationMethod && $byTokenAndUsername) {
            return Result::fail(FailReason::DISCREPANT_DATA);
        }

        if (($byTokenAndUsername || $byTokenOnly) && !$authToken) {
            if ($username) {
                $this->log->info("AUTH: Trying to login as user '{$username}' by token but token is not found.");
            }

            return $this->processFail(Result::fail(FailReason::TOKEN_NOT_FOUND), $data, $request);
        }

        if ($byTokenOnly) {
            assert($authToken !== null);

            $username = $this->getUsernameByAuthToken($authToken);

            if (!$username) {
                return $this->processFail(Result::fail(FailReason::USER_NOT_FOUND), $data, $request);
            }
        }

        $authenticationMethod ??= $this->configDataProvider->getDefaultAuthenticationMethod();

        $login = $this->loginFactory->create($authenticationMethod, $this->isPortal());

        $loginData = LoginData
            ::createBuilder()
            ->setUsername($username)
            ->setPassword($password)
            ->setAuthToken($authToken)
            ->build();

        $result = $login->login($loginData, $request);

        $user = $result->getUser();

        $authLogRecord = !$authTokenIsFound ?
            $this->createAuthLogRecord($username, $user, $request, $authenticationMethod) :
            null;

        if ($result->isFail()) {
            return $this->processFail($result, $data, $request);
        }

        if (!$user) {
            // Supposed not to ever happen.
            return $this->processFail(Result::fail(FailReason::USER_NOT_FOUND), $data, $request);
        }

        if (!$user->isAdmin() && $this->configDataProvider->isMaintenanceMode()) {
            throw new ServiceUnavailable("Application is in maintenance mode.");
        }

        if (!$this->processUserCheck($user, $authLogRecord)) {
            return $this->processFail(Result::fail(FailReason::DENIED), $data, $request);
        }

        if ($this->isPortal()) {
            $user->set('portalId', $this->getPortal()->getId());
        }

        if (!$this->isPortal()) {
            $user->loadLinkMultipleField('teams');
        }

        $user->set('ipAddress', $request->getServerParam('REMOTE_ADDR') ?? null);

        [$loggedUser, $anotherUserFailReason] = $this->getLoggedUser($request, $user);

        if (!$loggedUser) {
            $anotherUserFailReason = $anotherUserFailReason ?? FailReason::ANOTHER_USER_NOT_FOUND;

            return $this->processFail(Result::fail($anotherUserFailReason), $data, $request);
        }

        $this->applicationUser->setUser($loggedUser);

        if (
            !$result->isSecondStepRequired() &&
            !$authToken &&
            $this->configDataProvider->isTwoFactorEnabled()
        ) {
            $result = $this->processTwoFactor($result, $request);

            if ($result->isFail()) {
                return $this->processFail($result, $data, $request);
            }
        }

        if (
            !$result->isSecondStepRequired() &&
            $request->getHeader(self::HEADER_ESPO_AUTHORIZATION)
        ) {
            $authToken = $this->processAuthTokenFinal(
                $authToken,
                $authLogRecord,
                $user,
                $loggedUser,
                $request,
                $response
            );
        }

        $this->processAuthLogRecord($authLogRecord, $authToken, $loggedUser);

        if ($result->isSuccess()) {
            return $this->processSuccess($result, $data, $request, $authTokenIsFound);
        }

        if ($result->isSecondStepRequired()) {
            return $this->processSecondStepRequired($result, $data, $request);
        }

        return $result;
    }

    private function processAuthTokenFinal(
        ?AuthToken $authToken,
        ?AuthLogRecord $authLogRecord,
        User $user,
        User $loggedUser,
        Request $request,
        Response $response
    ): AuthToken {

        if ($authToken) {
            $this->authTokenManager->renew($authToken);
        }

        if (!$authToken) {
            $authToken = $this->createAuthToken($user, $request, $response);
        }

        $authTokenId = null;

        if ($authToken instanceof AuthTokenEntity) {
            $authTokenId = $authToken->hasId() ? $authToken->getId() : null;
        }

        $loggedUser->set('token', $authToken->getToken());
        $loggedUser->set('authTokenId', $authTokenId);

        if ($authLogRecord) {
            $authLogRecord->set('authTokenId', $authTokenId);
        }

        return $authToken;
    }

    private function processAuthLogRecord(
        ?AuthLogRecord $authLogRecord,
        ?AuthToken $authToken,
        User $loggedUser
    ): void {

        if ($authLogRecord) {
            $this->entityManager->saveEntity($authLogRecord);
        }

        if (
            !$authLogRecord &&
            $authToken instanceof AuthLogRecord &&
            $authToken->hasId()
        ) {
            $authLogRecord = $this->entityManager
                ->getRDBRepository(AuthLogRecord::ENTITY_TYPE)
                ->select(['id'])
                ->where(['authTokenId' => $authToken->getId()])
                ->order('requestTime', true)
                ->findOne();
        }

        if ($authLogRecord) {
            $loggedUser->set('authLogRecordId', $authLogRecord->getId());
        }
    }

    private function setPortal(Portal $portal): void
    {
        $this->portal = $portal;
    }

    private function isPortal(): bool
    {
        return $this->portal || $this->applicationState->isPortal();
    }

    private function getPortal(): Portal
    {
        if ($this->portal) {
            return $this->portal;
        }

        return $this->applicationState->getPortal();
    }

    private function processAuthTokenCheck(AuthToken $authToken): bool
    {
        if ($this->allowAnyAccess && $authToken->getPortalId() && !$this->isPortal()) {
            /** @var ?Portal $portal */
            $portal = $this->entityManager->getEntity('Portal', $authToken->getPortalId());

            if ($portal) {
                $this->setPortal($portal);
            }
        }

        if ($this->allowAnyAccess) {
            return true;
        }

        if ($this->isPortal() && $authToken->getPortalId() !== $this->getPortal()->getId()) {
            $this->log->info("AUTH: Trying to login to portal with a token not related to portal.");

            return false;
        }

        if (!$this->isPortal() && $authToken->getPortalId()) {
            $this->log->info("AUTH: Trying to login to crm with a token related to portal.");

            return false;
        }

        return true;
    }

    private function processUserCheck(User $user, ?AuthLogRecord $authLogRecord): bool
    {
        if (!$user->isActive()) {
            $this->log
                ->info("AUTH: Trying to login as user '" . $user->getUserName() . "' which is not active.");

            $this->logDenied($authLogRecord, AuthLogRecord::DENIAL_REASON_INACTIVE_USER);

            return false;
        }

        if ($user->isSystem()) {
            $this->log
                ->info("AUTH: Trying to login to crm as a system user '{$user->getUserName()}'.");

            $this->logDenied($authLogRecord, AuthLogRecord::DENIAL_REASON_IS_SYSTEM_USER);

            return false;
        }

        if (!$user->isAdmin() && !$this->isPortal() && $user->isPortal()) {
            $this->log
                ->info("AUTH: Trying to login to crm as a portal user '" . $user->getUserName() . "'.");

            $this->logDenied($authLogRecord, AuthLogRecord::DENIAL_REASON_IS_PORTAL_USER);

            return false;
        }

        if ($this->isPortal() && !$user->isPortal()) {
            $this->log->info(
                "AUTH: Trying to login to portal as user '" . $user->getUserName() . "' which is not portal user.");

            $this->logDenied($authLogRecord, AuthLogRecord::DENIAL_REASON_IS_NOT_PORTAL_USER);

            return false;
        }

        if ($this->isPortal()) {
            $isPortalRelatedToUser = $this->entityManager
                ->getRDBRepository(Portal::ENTITY_TYPE)
                ->getRelation($this->getPortal(), 'users')
                ->isRelated($user);

            if (!$isPortalRelatedToUser) {
                $this->log->info(
                    "AUTH: Trying to login to portal as user '" . $user->getUserName() . "' ".
                    "which is portal user but does not belongs to portal."
                );

                $this->logDenied($authLogRecord, AuthLogRecord::DENIAL_REASON_USER_IS_NOT_IN_PORTAL);

                return false;
            }
        }

        return true;
    }

    private function processTwoFactor(Result $result, Request $request): Result
    {
        $user = $result->getUser();

        if (!$user) {
            throw new RuntimeException("No user.");
        }

        $method = $this->getUser2FAMethod($user);

        if (!$method) {
            return $result;
        }

        $login = $this->twoFactorLoginFactory->create($method);

        return $login->login($result, $request);
    }

    private function getUser2FAMethod(User $user): ?string
    {
        $userData = $this->getUserDataRepository()->getByUserId($user->getId());

        if (!$userData) {
            return null;
        }

        if (!$userData->getAuth2FA()) {
            return null;
        }

        $method = $userData->getAuth2FAMethod();

        if (!$method) {
            return null;
        }

        if (!in_array($method, $this->configDataProvider->getTwoFactorMethodList())) {
            return null;
        }

        return $method;
    }

    private function createAuthToken(User $user, Request $request, Response $response): AuthToken
    {
        $createSecret =
            $request->getHeader(self::HEADER_CREATE_TOKEN_SECRET) === 'true' &&
            !$this->configDataProvider->isAuthTokenSecretDisabled();

        $arrayData = [
            'hash' => $user->get('password'),
            'ipAddress' => $request->getServerParam('REMOTE_ADDR'),
            'userId' => $user->hasId() ? $user->getId() : null,
            'portalId' => $this->isPortal() ? $this->getPortal()->getId() : null,
            'createSecret' => $createSecret,
        ];

        $authToken = $this->authTokenManager->create(AuthTokenData::create($arrayData));

        if ($createSecret) {
            $this->setSecretInCookie($authToken->getSecret(), $response, $request);
        }

        if (
            $this->configDataProvider->preventConcurrentAuthToken() &&
            $authToken instanceof AuthTokenEntity
        ) {
            $concurrentAuthTokenList = $this->entityManager
                ->getRDBRepository(AuthTokenEntity::ENTITY_TYPE)
                ->select(['id'])
                ->where([
                    'userId' => $user->getId(),
                    'isActive' => true,
                    'id!=' => $authToken->getId(),
                ])
                ->find();

            foreach ($concurrentAuthTokenList as $concurrentAuthToken) {
                $concurrentAuthToken->set('isActive', false);

                $this->entityManager->saveEntity($concurrentAuthToken);
            }
        }

        return $authToken;
    }

    public function destroyAuthToken(string $token, Request $request, Response $response): bool
    {
        $authToken = $this->authTokenManager->get($token);

        if (!$authToken) {
            return false;
        }

        $this->authTokenManager->inactivate($authToken);

        if ($authToken->getSecret()) {
            $sentSecret = $request->getCookieParam(self::COOKIE_AUTH_TOKEN_SECRET);

            if ($sentSecret === $authToken->getSecret()) {
                $this->setSecretInCookie(null, $response);
            }
        }

        $method = $this->configDataProvider->getDefaultAuthenticationMethod();

        if ($this->logoutFactory->isCreatable($method)) {
            $logout = $this->logoutFactory->create($method);

            $result = $logout->logout($authToken, LogoutParams::create());

            $redirectUrl = $result->getRedirectUrl();

            if ($redirectUrl) {
                $response->setHeader(self::HEADER_LOGOUT_REDIRECT_URL, $redirectUrl);
            }
        }

        return true;
    }

    private function createAuthLogRecord(
        ?string $username,
        ?User $user,
        Request $request,
        ?string $authenticationMethod = null
    ): ?AuthLogRecord {

        if ($username === self::LOGOUT_USERNAME) {
            return null;
        }

        /** @var AuthLogRecord $authLogRecord */
        $authLogRecord = $this->entityManager->getNewEntity(AuthLogRecord::ENTITY_TYPE);

        $requestUrl =
            $request->getUri()->getScheme() . '://' .
            $request->getUri()->getHost() .
            $request->getUri()->getPath();

        if (!$username && $user) {
            $username = $user->getUserName();
        }

        $authLogRecord->set([
            'username' => $username,
            'ipAddress' => $request->getServerParam('REMOTE_ADDR'),
            'requestTime' => $request->getServerParam('REQUEST_TIME_FLOAT'),
            'requestMethod' => $request->getMethod(),
            'requestUrl' => $requestUrl,
            'authenticationMethod' => $authenticationMethod,
        ]);

        if ($this->isPortal()) {
            $authLogRecord->set('portalId', $this->getPortal()->getId());
        }

        if ($user) {
            $authLogRecord->set('userId', $user->hasId() ? $user->getId() : null);

            return $authLogRecord;
        }

        $authLogRecord->set('isDenied', true);
        $authLogRecord->set('denialReason', AuthLogRecord::DENIAL_REASON_CREDENTIALS);

        $this->entityManager->saveEntity($authLogRecord);

        return $authLogRecord;
    }

    private function logDenied(?AuthLogRecord $authLogRecord, string $denialReason): void
    {
        if (!$authLogRecord) {
            return;
        }

        $authLogRecord->set('denialReason', $denialReason);

        $this->entityManager->saveEntity($authLogRecord);
    }

    private function setSecretInCookie(?string $secret, Response $response, ?Request $request = null): void
    {
        $time = $secret ? strtotime('+1000 days') : 1;

        $value = $secret ?? 'deleted';

        $headerValue =
            self::COOKIE_AUTH_TOKEN_SECRET . '=' . urlencode($value) .
            '; path=/' .
            '; expires=' . gmdate('D, d M Y H:i:s T', $time) .
            '; HttpOnly' .
            '; SameSite=Lax';

        if ($request && self::isSecureRequest($request)) {
            $headerValue .= "; Secure";
        }

        $response->addHeader('Set-Cookie', $headerValue);
    }

    private static function isSecureRequest(Request $request): bool
    {
        $https = $request->getServerParam('HTTPS');

        if ($https === 'on') {
            return true;
        }

        $scheme = $request->getServerParam('REQUEST_SCHEME');

        if ($scheme === 'https') {
            return true;
        }

        $forwardedProto = $request->getServerParam('HTTP_X_FORWARDED_PROTO');

        if ($forwardedProto === 'https') {
            return true;
        }

        return false;
    }

    private function processFail(Result $result, AuthenticationData $data, Request $request): Result
    {
        $this->hookManager->processOnFail($result, $data, $request);

        return $result;
    }

    private function processSuccess(
        Result $result,
        AuthenticationData $data,
        Request $request,
        bool $byToken
    ): Result {

        if ($byToken) {
            $this->hookManager->processOnSuccessByToken($result, $data, $request);

            return $result;
        }

        $this->hookManager->processOnSuccess($result, $data, $request);

        return $result;
    }

    private function processSecondStepRequired(
        Result $result,
        AuthenticationData $data,
        Request $request
    ): Result {

        $this->hookManager->processOnSecondStepRequired($result, $data, $request);

        return $result;
    }

    private function getUserDataRepository(): UserDataRepository
    {
        /** @var UserDataRepository */
        return $this->entityManager->getRepository(UserData::ENTITY_TYPE);
    }

    private function getUsernameByAuthToken(AuthToken $authToken): ?string
    {
        /** @var ?User $user */
        $user = $this->entityManager
            ->getRDBRepository(User::ENTITY_TYPE)
            ->select(['userName'])
            ->where(['id' => $authToken->getUserId()])
            ->findOne();

        if (!$user) {
            return null;
        }

        return $user->getUserName();
    }

    /**
     * @return array{?User, (FailReason::*)|null}
     */
    private function getLoggedUser(Request $request, User $user): array
    {
        $username = $request->getHeader(self::HEADER_ANOTHER_USER);

        if (!$username) {
            return [$user, null];
        }

        if ($this->configDataProvider->isAnotherUserDisabled()) {
            return [null, FailReason::ANOTHER_USER_NOT_ALLOWED];
        }

        // Important check.
        if (!$user->isAdmin()) {
            return [null, FailReason::ANOTHER_USER_NOT_ALLOWED];
        }

        /** @var ?User $loggedUser */
        $loggedUser = $this->entityManager
            ->getRDBRepository(User::ENTITY_TYPE)
            ->where(['userName' => $username])
            ->findOne();

        if (!$loggedUser) {
            return [null, FailReason::ANOTHER_USER_NOT_FOUND];
        }

        if (!$loggedUser->isRegular()) {
            return [null, FailReason::ANOTHER_USER_NOT_ALLOWED];
        }

        return [$loggedUser, null];
    }
}
