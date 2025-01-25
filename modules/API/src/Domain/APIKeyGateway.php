<?php
namespace Gibbon\Module\API\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Aura\SqlQuery\Common\DeleteInterface;
use Aura\SqlQuery\Common\UpdateInterface;

class APIKeyGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'APIKey';
    private static $primaryKey = 'id';
    private static $searchableColumns = ['name'];

    public function queryAPIKeys(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'id',
                'name',
                'key',
                'token',
                'tokenExpiry',
                'dateCreated',
                'lastAccessed',
                'active',
                'permissions',
                'gibbonPersonID'
            ]);

        return $this->runQuery($query, $criteria);
    }

    public function getAPIKeyByKey(string $key)
    {
        $data = ['key' => $key];
        $sql = "SELECT * FROM APIKey WHERE `key`=:key";
        return $this->db()->selectOne($sql, $data);
    }

    public function getByToken(string $token)
    {
        $data = ['token' => $token];
        $sql = "SELECT * FROM APIKey WHERE token=:token";
        return $this->db()->selectOne($sql, $data);
    }

    public function updateLastAccessed(string $key)
    {
        $data = [
            'key' => $key,
            'lastAccessed' => date('Y-m-d H:i:s')
        ];
        $sql = "UPDATE APIKey SET lastAccessed=:lastAccessed WHERE `key`=:key";
        return $this->db()->update($sql, $data);
    }

    public function update($id, array $data): bool
    {
        $sql = "UPDATE APIKey SET ";
        $fields = [];
        foreach ($data as $field => $value) {
            $fields[] = "`$field`=:$field";
        }
        $sql .= implode(', ', $fields);
        $sql .= " WHERE id=:id";
        
        $data['id'] = $id;
        return $this->db()->update($sql, $data) !== false;
    }

    public function runUpdate(UpdateInterface $query): bool
    {
        return parent::runUpdate($query);
    }

    public function runDelete(DeleteInterface $query): bool
    {
        return parent::runDelete($query);
    }
}
