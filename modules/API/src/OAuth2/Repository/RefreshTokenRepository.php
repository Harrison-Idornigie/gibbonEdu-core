<?php
namespace Gibbon\Module\API\OAuth2\Repository;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Gibbon\Module\API\OAuth2\Entity\RefreshTokenEntity;
use Gibbon\Domain\Gateway;

class RefreshTokenRepository extends Gateway implements RefreshTokenRepositoryInterface
{
    private static $tableName = 'gibbonOAuthRefreshToken';
    private static $primaryKey = 'id';
    private static $searchableColumns = ['identifier'];

    protected function getTableName() 
    {
        return self::$tableName;
    }

    protected function getTableSchema() 
    {
        return [
            'id' => ['type' => 'int', 'length' => 10, 'unsigned' => true, 'auto_increment' => true],
            'identifier' => ['type' => 'varchar', 'length' => 100],
            'accessTokenId' => ['type' => 'int', 'length' => 10, 'unsigned' => true],
            'expiryDateTime' => ['type' => 'datetime'],
            'revoked' => ['type' => 'tinyint', 'length' => 1, 'default' => 0]
        ];
    }

    protected function getSearchableColumns() 
    {
        return self::$searchableColumns;
    }

    public function getNewRefreshToken()
    {
        return new RefreshTokenEntity();
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity)
    {
        $this->insert([
            'identifier' => $refreshTokenEntity->getIdentifier(),
            'accessTokenId' => $refreshTokenEntity->getAccessToken()->getIdentifier(),
            'expiryDateTime' => $refreshTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
            'revoked' => 0
        ]);
    }

    public function revokeRefreshToken($tokenId)
    {
        $this->update(['revoked' => 1], ['identifier' => $tokenId]);
    }

    public function isRefreshTokenRevoked($tokenId)
    {
        $result = $this->db()->selectOne(
            "SELECT revoked FROM gibbonOAuthRefreshToken WHERE identifier = :identifier",
            ['identifier' => $tokenId]
        );
        return $result ? (bool)$result['revoked'] : true;
    }
}
