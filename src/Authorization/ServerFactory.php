<?php

namespace Whilesmart\EloquentClientCredentials\Authorization;

use DateInterval;
use League\OAuth2\Server\AuthorizationServer;
use Whilesmart\EloquentClientCredentials\Authorization\Grants\AuthCodeGrant;
use Whilesmart\EloquentClientCredentials\Authorization\Grants\RefreshTokenGrant;
use Whilesmart\EloquentClientCredentials\Authorization\Repositories\AccessTokenRepository;
use Whilesmart\EloquentClientCredentials\Authorization\Repositories\AuthCodeRepository;
use Whilesmart\EloquentClientCredentials\Authorization\Repositories\ClientRepository;
use Whilesmart\EloquentClientCredentials\Authorization\Repositories\RefreshTokenRepository;
use Whilesmart\EloquentClientCredentials\Authorization\Repositories\ScopeRepository;

class ServerFactory
{
    public function __construct(protected GrantContext $context) {}

    public function make(): AuthorizationServer
    {
        $config = config('client-credentials.authorization_code');

        $server = new AuthorizationServer(
            new ClientRepository,
            new AccessTokenRepository($this->context),
            new ScopeRepository($this->context),
            Keys::privateKey(),
            Keys::encryptionKey(),
        );

        $server->setDefaultScope(implode(' ', $config['default_scopes'] ?? []));

        $accessTokenLifetime = $this->seconds($config['access_token_lifetime'] ?? 3600);
        $refreshTokenLifetime = $this->seconds($config['refresh_token_lifetime'] ?? 2592000);
        $refreshTokens = new RefreshTokenRepository($this->context);

        $authCodeGrant = new AuthCodeGrant(
            new AuthCodeRepository($this->context),
            $refreshTokens,
            $this->seconds($config['code_lifetime'] ?? 600),
        );
        $authCodeGrant->setRefreshTokenTTL($refreshTokenLifetime);
        $server->enableGrantType($authCodeGrant, $accessTokenLifetime);

        $refreshGrant = new RefreshTokenGrant($refreshTokens, $this->context);
        $refreshGrant->setRefreshTokenTTL($refreshTokenLifetime);
        $server->enableGrantType($refreshGrant, $accessTokenLifetime);

        return $server;
    }

    protected function seconds(int $seconds): DateInterval
    {
        return new DateInterval("PT{$seconds}S");
    }
}
