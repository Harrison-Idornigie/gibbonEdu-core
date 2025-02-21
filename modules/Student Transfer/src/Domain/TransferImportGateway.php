<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
*/

namespace Gibbon\Module\StudentTransfer\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\DataSet;
use Aura\SqlQuery\Common\DeleteInterface;
use Aura\SqlQuery\Common\UpdateInterface;

/**
 * Transfer Import Gateway
 *
 * @version v29
 * @since   v29
 */
class TransferImportGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonStudentTransferImport';
    private static $primaryKey = 'gibbonStudentTransferImportID';
    private static $searchableColumns = [];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryImports(QueryCriteria $criteria): DataSet
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonStudentTransferImportID',
                'gibbonSchoolYearID',
                'gibbonPersonIDCreated',
                'status',
                'schoolNameFrom',
                'metadata',
                'studentData',
                'duplicates',
                'importProgress',
                'timestampCreated',
                'timestampModified',
                'importTimestamp'
            ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get import data by ID
     *
     * @param string $gibbonStudentTransferImportID
     * @return array|false
     */
    public function getByID($gibbonStudentTransferImportID)
    {
        $data = ['gibbonStudentTransferImportID' => $gibbonStudentTransferImportID];
        $sql = "SELECT * FROM gibbonStudentTransferImport WHERE gibbonStudentTransferImportID=:gibbonStudentTransferImportID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Update import progress
     *
     * @param string $gibbonStudentTransferImportID
     * @param array $progress
     * @return bool
     */
    public function updateProgress($gibbonStudentTransferImportID, array $progress): bool
    {
        $data = [
            'gibbonStudentTransferImportID' => $gibbonStudentTransferImportID,
            'importProgress' => json_encode($progress),
            'timestampModified' => date('Y-m-d H:i:s')
        ];

        $sql = "UPDATE gibbonStudentTransferImport 
                SET importProgress=:importProgress, timestampModified=:timestampModified 
                WHERE gibbonStudentTransferImportID=:gibbonStudentTransferImportID";

        return $this->db()->update($sql, $data);
    }

    /**
     * @inheritDoc
     */
    public function runDelete(DeleteInterface $query): bool
    {
        return parent::runDelete($query);
    }

    /**
     * @inheritDoc
     */
    public function runUpdate(UpdateInterface $query): bool
    {
        return parent::runUpdate($query);
    }
}
