<?php
namespace Gibbon\Module\API\OAuth2\Repository;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Gibbon\Module\API\OAuth2\Entity\ScopeEntity;

class ScopeRepository implements ScopeRepositoryInterface
{
    private $scopes = [
        'students:read' => 'Read student data',
        'students:write' => 'Write student data',
        'classes:read' => 'Read class data',
        'classes:write' => 'Write class data',
    ];

    public function getScopeEntityByIdentifier($identifier)
    {
        if (array_key_exists($identifier, $this->scopes)) {
            $scope = new ScopeEntity();
            $scope->setIdentifier($identifier);
            return $scope;
        }
        return null;
    }

    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null
    ) {
        // For now, return all requested scopes that exist
        return array_filter($scopes, function ($scope) {
            return array_key_exists($scope->getIdentifier(), $this->scopes);
        });
    }
}
