<?php
namespace Gibbon\Module\API\OAuth2;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\CryptKey;
use DateInterval;
use Gibbon\Module\API\OAuth2\Repository\ClientRepository;
use Gibbon\Module\API\OAuth2\Repository\ScopeRepository;
use Gibbon\Module\API\OAuth2\Repository\AccessTokenRepository;
use Gibbon\Module\API\OAuth2\Repository\RefreshTokenRepository;
use Gibbon\Module\API\OAuth2\Repository\AuthCodeRepository;
use Gibbon\Module\API\OAuth2\Repository\UserRepository;

class OAuth2Provider
{
    private $privateKey;
    private $publicKey;
    private $encryptionKey;
    
    private $clientRepository;
    private $scopeRepository;
    private $accessTokenRepository;
    private $refreshTokenRepository;
    private $authCodeRepository;
    private $userRepository;

    public function __construct(
        string $privateKey,
        string $publicKey,
        string $encryptionKey,
        ClientRepository $clientRepository,
        ScopeRepository $scopeRepository,
        AccessTokenRepository $accessTokenRepository,
        RefreshTokenRepository $refreshTokenRepository,
        AuthCodeRepository $authCodeRepository,
        UserRepository $userRepository
    ) {
        $this->privateKey = new CryptKey($privateKey);
        $this->publicKey = new CryptKey($publicKey, null, false);
        $this->encryptionKey = $encryptionKey;
        
        $this->clientRepository = $clientRepository;
        $this->scopeRepository = $scopeRepository;
        $this->accessTokenRepository = $accessTokenRepository;
        $this->refreshTokenRepository = $refreshTokenRepository;
        $this->authCodeRepository = $authCodeRepository;
        $this->userRepository = $userRepository;
    }

    public function getAuthorizationServer(): AuthorizationServer
    {
        $server = new AuthorizationServer(
            $this->clientRepository,
            $this->accessTokenRepository,
            $this->scopeRepository,
            $this->privateKey,
            $this->encryptionKey
        );

        // Enable the client credentials grant
        $server->enableGrantType(
            new ClientCredentialsGrant(),
            new DateInterval('PT1H') // Access tokens will expire after 1 hour
        );

        // Enable the authorization code grant
        $server->enableGrantType(
            new AuthCodeGrant(
                $this->authCodeRepository,
                $this->refreshTokenRepository,
                new DateInterval('PT10M') // Authorization codes will expire after 10 minutes
            ),
            new DateInterval('PT1H') // Access tokens will expire after 1 hour
        );

        // Enable the refresh token grant
        $server->enableGrantType(
            new RefreshTokenGrant($this->refreshTokenRepository),
            new DateInterval('PT1H') // New access tokens will expire after 1 hour
        );

        return $server;
    }

    public function getResourceServer(): ResourceServer
    {
        return new ResourceServer(
            $this->accessTokenRepository,
            $this->publicKey
        );
    }
}
