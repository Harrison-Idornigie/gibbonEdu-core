<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

namespace Gibbon\Module\OpenAdminImport\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\DataSet;
use Aura\SqlQuery\Common\DeleteInterface;
use Aura\SqlQuery\Common\UpdateInterface;

/**
 * Import Log Gateway
 *
 * @version v29
 * @since   v29
 */
class ImportLogGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'openAdminImportLog';
    private static $primaryKey = 'openAdminImportLogID';
    private static $searchableColumns = ['title'];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryImportLog(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'openAdminImportLog.openAdminImportLogID',
                'openAdminImportLog.title',
                'openAdminImportLog.description',
                'openAdminImportLog.type',
                'openAdminImportLog.gibbonPersonID',
                'openAdminImportLog.timestamp',
                'openAdminImportLog.serialisedArray',
                'gibbonPerson.surname',
                'gibbonPerson.preferredName'
            ])
            ->leftJoin('gibbonPerson', 'openAdminImportLog.gibbonPersonID=gibbonPerson.gibbonPersonID');

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param string $id
     * @return array
     */
    public function getByID($id)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'openAdminImportLog.*',
                'gibbonPerson.surname',
                'gibbonPerson.preferredName'
            ])
            ->leftJoin('gibbonPerson', 'openAdminImportLog.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('openAdminImportLogID = :openAdminImportLogID')
            ->bindValue('openAdminImportLogID', $id);

        return $this->runSelect($query)->fetch();
    }

    /**
     * @param array $data
     * @return bool
     */
    public function insert($data)
    {
        $query = $this
            ->newInsert()
            ->into($this->getTableName())
            ->cols($data);

        return $this->runInsert($query);
    }

    /**
     * @param string $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data)
    {
        $query = $this
            ->newUpdate()
            ->table($this->getTableName())
            ->cols($data)
            ->where('openAdminImportLogID = :openAdminImportLogID')
            ->bindValue('openAdminImportLogID', $id);

        return $this->runUpdate($query);
    }

    /**
     * @param string $id
     * @return bool
     */
    public function delete($id)
    {
        $query = $this
            ->newDelete()
            ->from($this->getTableName())
            ->where('openAdminImportLogID = :openAdminImportLogID')
            ->bindValue('openAdminImportLogID', $id);

        return $this->runDelete($query);
    }

    /**
     * @param UpdateInterface $query
     * @return bool
     */
    public function runUpdate(UpdateInterface $query): bool
    {
        return parent::runUpdate($query);
    }

    /**
     * @param DeleteInterface $query
     * @return bool
     */
    public function runDelete(DeleteInterface $query): bool
    {
        return parent::runDelete($query);
    }
}
