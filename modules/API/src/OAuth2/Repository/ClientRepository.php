<?php
namespace Gibbon\Module\API\OAuth2\Repository;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use Gibbon\Module\API\OAuth2\Entity\ClientEntity;
use Gibbon\Domain\Gateway;

class ClientRepository extends Gateway implements ClientRepositoryInterface
{
    private static $tableName = 'gibbonOAuthClient';
    private static $primaryKey = 'id';
    private static $searchableColumns = ['name', 'identifier'];

    protected function getTableName() 
    {
        return self::$tableName;
    }

    protected function getTableSchema() 
    {
        return [
            'id' => ['type' => 'int', 'length' => 10, 'unsigned' => true, 'auto_increment' => true],
            'identifier' => ['type' => 'varchar', 'length' => 100],
            'name' => ['type' => 'varchar', 'length' => 100],
            'secret' => ['type' => 'varchar', 'length' => 100],
            'redirectUri' => ['type' => 'text'],
            'grantTypes' => ['type' => 'varchar', 'length' => 100],
            'scopes' => ['type' => 'text'],
            'active' => ['type' => 'enum', 'options' => ['Y', 'N'], 'default' => 'Y'],
            'gibbonPersonID' => ['type' => 'int', 'length' => 10, 'unsigned' => true],
            'dateCreated' => ['type' => 'datetime'],
            'lastAccessed' => ['type' => 'datetime', 'null' => true]
        ];
    }

    protected function getSearchableColumns() 
    {
        return self::$searchableColumns;
    }

    protected function countAll() 
    {
        return $this->db()->selectOne("SELECT COUNT(*) as count FROM " . $this->getTableName())->fetch()['count'];
    }

    public function getClientEntity($clientIdentifier): ?ClientEntityInterface
    {
        $data = ['identifier' => $clientIdentifier];
        $sql = "SELECT * FROM gibbonOAuthClient WHERE identifier=:identifier AND active='Y'";
        $result = $this->db()->selectOne($sql, $data);

        if (!$result) {
            return null;
        }

        return new ClientEntity(
            $result['identifier'],
            $result['name'],
            $result['redirectUri'] ? explode(',', $result['redirectUri']) : [],
            $result['grantTypes'] ? explode(',', $result['grantTypes']) : ['client_credentials'],
            true
        );
    }

    public function validateClient($clientIdentifier, $clientSecret, $grantType): bool
    {
        $data = [
            'identifier' => $clientIdentifier,
            'secret' => $clientSecret,
            'grantType' => $grantType
        ];

        $sql = "SELECT * FROM gibbonOAuthClient 
                WHERE identifier=:identifier 
                AND secret=:secret 
                AND active='Y'
                AND FIND_IN_SET(:grantType, grantTypes)";
        
        return (bool) $this->db()->selectOne($sql, $data);
    }
}
