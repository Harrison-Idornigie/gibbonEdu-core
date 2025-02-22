<?php
/*
Gibbon: the flexible, open school platform
Copyright 2010, Ross Parker

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
 * Import Gateway
 *
 * @version v29
 * @since   v29
 */
class ImportGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'oafsImportLog';
    private static $primaryKey = 'oafsImportLogID';
    private static $searchableColumns = ['importType', 'oafsImportLog.status'];

    /**
     * Records an import attempt in the log
     *
     * @param string $importType
     * @param string $status
     * @param array $data
     * @return bool
     */
    public function logImport(string $importType, string $status, array $data = []): bool
    {
        $data = [
            'importType' => $importType,
            'status' => $status,
            'recordCount' => $data['recordCount'] ?? 0,
            'successCount' => $data['success'] ?? 0,
            'errorCount' => $data['errors'] ?? 0,
            'messages' => is_array($data['messages'] ?? null) ? json_encode($data['messages']) : null,
            'gibbonPersonIDCreated' => $data['gibbonPersonIDCreated'] ?? null,
            'timestampCreated' => date('Y-m-d H:i:s')
        ];

        return $this->insert($data);
    }

    /**
     * Gets the most recent import logs
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryImportLogs(QueryCriteria $criteria): DataSet
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'oafsImportLogID',
                'importType',
                'oafsImportLog.status',
                'recordCount',
                'successCount', 
                'errorCount',
                'messages',
                'timestampCreated',
                'gibbonPerson.title',
                'gibbonPerson.surname',
                'gibbonPerson.preferredName'
            ])
            ->leftJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID=oafsImportLog.gibbonPersonIDCreated');

        return $this->runQuery($query, $criteria);
    }

    /**
     * Override TableAware methods to match QueryableGateway return types
     */
    public function runUpdate(UpdateInterface $query): bool
    {
        return parent::runUpdate($query);
    }

    public function runDelete(DeleteInterface $query): bool
    {
        return parent::runDelete($query);
    }
}
