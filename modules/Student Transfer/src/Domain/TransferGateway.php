<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\StudentTransfer\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\DataSet;

/**
 * Transfer Gateway
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class TransferGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonStudentTransferLog';
    private static $primaryKey = 'gibbonStudentTransferLogID';
    private static $searchableColumns = ['gibbonStudentTransferLog.gibbonStudentTransferLogID', 'gibbonStudentTransferLog.status'];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryTransfers(QueryCriteria $criteria): DataSet
    {
        $query = $this
            ->newQuery()
            ->distinct()
            ->from($this->getTableName())
            ->cols([
                'gibbonStudentTransferLog.gibbonStudentTransferLogID',
                'gibbonStudentTransferLog.status',
                'gibbonStudentTransferLog.timestampCreated',
                'gibbonStudentTransferLog.timestampModified',
                'gibbonStudentTransferLog.exportTimestamp',
                'gibbonStudentTransferLog.importTimestamp',
                'gibbonPerson.surname',
                'gibbonPerson.preferredName',
                'gibbonPerson.gibbonPersonID'
            ])
            ->leftJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID=gibbonStudentTransferLog.gibbonPersonID');

        $criteria->addFilterRules([
            'status' => function ($query, $status) {
                return $query
                    ->where('gibbonStudentTransferLog.status = :status')
                    ->bindValue('status', $status);
            }
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param string $id
     * @return array|false
     */
    public function getTransferByID(string $id): array|false
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStudentTransferLog.*',
                'gibbonPerson.surname',
                'gibbonPerson.preferredName'
            ])
            ->leftJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID=gibbonStudentTransferLog.gibbonPersonID')
            ->where('gibbonStudentTransferLog.gibbonStudentTransferLogID = :id')
            ->bindValue('id', $id);

        return $this->runSelect($query)->fetch();
    }

    /**
     * @param array $data
     * @return bool
     */
    public function insert(array $data): bool
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
    public function update(string $id, array $data): bool
    {
        $query = $this
            ->newUpdate()
            ->table($this->getTableName())
            ->cols($data)
            ->where('gibbonStudentTransferLogID = :id')
            ->bindValue('id', $id);

        return $this->runUpdate($query);
    }

    /**
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool
    {
        $query = $this
            ->newDelete()
            ->from($this->getTableName())
            ->where('gibbonStudentTransferLogID = :id')
            ->bindValue('id', $id);

        return $this->runDelete($query);
    }
}
