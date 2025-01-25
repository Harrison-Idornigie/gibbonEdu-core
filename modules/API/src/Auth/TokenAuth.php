<?php
namespace Gibbon\Module\API\Auth;

use Gibbon\Module\API\Domain\APIKeyGateway;

class TokenAuth
{
    private $apiKeyGateway;
    private $tokenExpiry = 3600; // 1 hour default

    public function __construct(APIKeyGateway $apiKeyGateway)
    {
        $this->apiKeyGateway = $apiKeyGateway;
    }

    public function generateToken(string $apiKey): ?array
    {
        $keyData = $this->apiKeyGateway->getAPIKeyByKey($apiKey);
        if (!$keyData || $keyData['active'] !== 'Y') {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', time() + $this->tokenExpiry);

        $updated = $this->apiKeyGateway->update($keyData['id'], [
            'token' => $token,
            'tokenExpiry' => $expiry
        ]);

        return $updated ? [
            'token' => $token,
            'expires' => $expiry
        ] : null;
    }

    public function validateToken(string $token): bool
    {
        $keyData = $this->apiKeyGateway->getByToken($token);
        if (!$keyData || $keyData['active'] !== 'Y') {
            return false;
        }

        if (strtotime($keyData['tokenExpiry']) < time()) {
            return false;
        }

        return true;
    }

    public function revokeToken(string $token): bool
    {
        $keyData = $this->apiKeyGateway->getByToken($token);
        if (!$keyData) {
            return false;
        }

        return $this->apiKeyGateway->update($keyData['id'], [
            'token' => null,
            'tokenExpiry' => null
        ]);
    }
}
