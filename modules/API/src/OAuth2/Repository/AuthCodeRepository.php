<?php
namespace Gibbon\Module\API\OAuth2\Repository;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Gibbon\Module\API\OAuth2\Entity\AuthCodeEntity;
use Gibbon\Domain\Gateway;

class AuthCodeRepository extends Gateway implements AuthCodeRepositoryInterface
{
    private static $tableName = 'gibbonOAuthAuthCode';
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
            'clientId' => ['type' => 'int', 'length' => 10, 'unsigned' => true],
            'userIdentifier' => ['type' => 'varchar', 'length' => 100],
            'expiryDateTime' => ['type' => 'datetime'],
            'revoked' => ['type' => 'tinyint', 'length' => 1, 'default' => 0]
        ];
    }

    protected function getSearchableColumns() 
    {
        return self::$searchableColumns;
    }

    public function getNewAuthCode()
    {
        return new AuthCodeEntity();
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity)
    {
        $this->insert([
            'identifier' => $authCodeEntity->getIdentifier(),
            'clientId' => $authCodeEntity->getClient()->getIdentifier(),
            'userIdentifier' => $authCodeEntity->getUserIdentifier(),
            'expiryDateTime' => $authCodeEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
            'revoked' => 0
        ]);
    }

    public function revokeAuthCode($codeId)
    {
        $this->update(['revoked' => 1], ['identifier' => $codeId]);
    }

    public function isAuthCodeRevoked($codeId)
    {
        $result = $this->db()->selectOne(
            "SELECT revoked FROM gibbonOAuthAuthCode WHERE identifier = :identifier",
            ['identifier' => $codeId]
        );
        return $result ? (bool)$result['revoked'] : true;
    }
}
