<?php
namespace Gibbon\Module\API\OAuth2\Repository;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Gibbon\Module\API\OAuth2\Entity\AccessTokenEntity;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;

class AccessTokenRepository extends QueryableGateway implements AccessTokenRepositoryInterface
{
    use TableAware;

    private static $tableName = 'gibbonOAuthAccessToken';
    private static $primaryKey = 'id';
    private static $searchableColumns = ['identifier'];

    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null)
    {
        $accessToken = new AccessTokenEntity();
        $accessToken->setClient($clientEntity);
        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }
        $accessToken->setUserIdentifier($userIdentifier);
        return $accessToken;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity)
    {
        $data = [
            'identifier' => $accessTokenEntity->getIdentifier(),
            'clientId' => $accessTokenEntity->getClient()->getIdentifier(),
            'userIdentifier' => $accessTokenEntity->getUserIdentifier(),
            'expiryDateTime' => $accessTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
            'revoked' => 0
        ];

        $this->db()->insert($this->getTableName())
             ->cols($data)
             ->execute();
    }

    public function revokeAccessToken($tokenId)
    {
        $this->db()->update($this->getTableName())
             ->cols(['revoked' => 1])
             ->where('identifier = :identifier')
             ->bindValue('identifier', $tokenId)
             ->execute();
    }

    public function isAccessTokenRevoked($tokenId)
    {
        $result = $this->db()->selectOne(
            "SELECT revoked FROM " . $this->getTableName() . " WHERE identifier = :identifier",
            ['identifier' => $tokenId]
        );
        return $result ? (bool)$result['revoked'] : true;
    }
}
