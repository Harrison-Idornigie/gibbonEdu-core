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
use Gibbon\Contracts\Database\Connection;
use Aura\SqlQuery\Common\DeleteInterface;
use Aura\SqlQuery\Common\UpdateInterface;
use Gibbon\Contracts\Database\Result;

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
    
    private static $searchableColumns = ['gibbonPerson.surname', 'gibbonPerson.preferredName', 'gibbonPerson.username'];
    
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        parent::__construct($connection);
        $this->connection = $connection;
    }

    /**
     * Queries all transfers with detailed information
     * @param QueryCriteria $criteria
     * @param int|null $gibbonSchoolYearID
     * @return DataSet
     */
    public function queryTransfers(QueryCriteria $criteria, $gibbonSchoolYearID = null): DataSet
    {
        $query = $this
            ->newQuery()
            ->from(self::$tableName)
            ->cols([
                self::$tableName.'.gibbonStudentTransferLogID',
                self::$tableName.'.timestampCreated as timestampCreated',
                self::$tableName.'.status',
                self::$tableName.'.schoolNameFrom',
                self::$tableName.'.schoolNameTo',
                'gibbonPerson.surname',
                'gibbonPerson.preferredName',
                'gibbonPerson.username',
                'gibbonYearGroup.nameShort as yearGroup'
            ])
            ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID='.self::$tableName.'.gibbonPersonID')
            ->leftJoin('gibbonStudentEnrolment', 'gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonYearGroup', 'gibbonYearGroup.gibbonYearGroupID=gibbonStudentEnrolment.gibbonYearGroupID');

        if ($gibbonSchoolYearID != null) {
            $query->where('gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID')
                  ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);
        }

        $criteria->addFilterRules([
            'status' => function($query, $status) {
                return $query->where(self::$tableName.'.status = :status')
                           ->bindValue('status', $status);
            }
        ]);

        // Handle sorting with fully qualified column names in orderBy
        if ($criteria->hasSort('surname')) {
            $direction = $criteria->getSortBy('surname');
            $query->orderBy(['gibbonPerson.surname '.$direction]);
        } elseif ($criteria->hasSort('preferredName')) {
            $direction = $criteria->getSortBy('preferredName');
            $query->orderBy(['gibbonPerson.preferredName '.$direction]);
        } elseif ($criteria->hasSort('yearGroup')) {
            $direction = $criteria->getSortBy('yearGroup');
            $query->orderBy(['yearGroup '.$direction]);
        } elseif ($criteria->hasSort('timestampCreated')) {
            $direction = $criteria->getSortBy('timestampCreated');
            $query->orderBy([self::$tableName.'.timestampCreated '.$direction]);
        }

        return $this->runQuery($query, $criteria);
    }

    /**
     * Gets transfer data by type
     * @param int $gibbonStudentTransferLogID
     * @param string $dataType
     * @return array
     */
    public function getTransferData($gibbonStudentTransferLogID, $dataType = null): array
    {
        $query = $this
            ->newSelect()
            ->from('gibbonStudentTransferData')
            ->cols(['*'])
            ->where('gibbonStudentTransferLogID=:gibbonStudentTransferLogID');

        if ($dataType) {
            $query->where('category=:category')
                  ->bindValue('category', $dataType);
        }

        $query->bindValue('gibbonStudentTransferLogID', $gibbonStudentTransferLogID);

        return $this->runSelect($query)->fetchAll();
    }

    /**
     * Gets transfer attachments
     * @param int $gibbonStudentTransferLogID
     * @return array
     */
    public function getTransferAttachments($gibbonStudentTransferLogID): array
    {
        $query = $this
            ->newSelect()
            ->from('gibbonStudentTransferAttachment')
            ->cols(['*'])
            ->where('gibbonStudentTransferLogID=:gibbonStudentTransferLogID')
            ->bindValue('gibbonStudentTransferLogID', $gibbonStudentTransferLogID);

        return $this->runSelect($query)->fetchAll();
    }

    /**
     * Saves transfer data
     * @param int $gibbonStudentTransferLogID
     * @param string $category
     * @param array $data Key-value pairs of data
     * @return bool
     */
    public function saveTransferData($gibbonStudentTransferLogID, $category, array $data): bool
    {
        // Delete existing data for this category
        $query = $this
            ->newDelete()
            ->from('gibbonStudentTransferData')
            ->where('gibbonStudentTransferLogID=:gibbonStudentTransferLogID')
            ->where('category=:category')
            ->bindValue('gibbonStudentTransferLogID', $gibbonStudentTransferLogID)
            ->bindValue('category', $category);

        $this->runDelete($query);

        // Insert new data
        foreach ($data as $name => $value) {
            $query = $this
                ->newInsert()
                ->into('gibbonStudentTransferData')
                ->cols([
                    'gibbonStudentTransferLogID' => $gibbonStudentTransferLogID,
                    'category' => $category,
                    'name' => $name,
                    'value' => $value
                ]);

            if (!$this->runInsert($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Saves transfer attachment
     * @param int $gibbonStudentTransferLogID
     * @param string $name
     * @param string $type
     * @param string $path
     * @param int $size
     * @return bool
     */
    public function saveTransferAttachment($gibbonStudentTransferLogID, $name, $type, $path, $size): bool
    {
        $query = $this
            ->newInsert()
            ->into('gibbonStudentTransferAttachment')
            ->cols([
                'gibbonStudentTransferLogID' => $gibbonStudentTransferLogID,
                'name' => $name,
                'type' => $type,
                'path' => $path,
                'size' => $size
            ]);

        return $this->runInsert($query);
    }

    /**
     * Gets pending transfers for a school
     * @param int $gibbonSchoolID
     * @return array
     */
    public function getPendingTransfers($gibbonSchoolID): array
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonStudentTransferLog.*',
                'gibbonPerson.surname',
                'gibbonPerson.preferredName',
                'gibbonPerson.username',
                'schoolFrom.name as schoolNameFrom'
            ])
            ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID=gibbonStudentTransferLog.gibbonPersonID')
            ->leftJoin('gibbonSchool as schoolFrom', 'schoolFrom.gibbonSchoolID=gibbonStudentTransferLog.gibbonSchoolIDFrom')
            ->where('gibbonStudentTransferLog.gibbonSchoolIDTo = :gibbonSchoolID')
            ->where('gibbonStudentTransferLog.status = :status')
            ->bindValue('gibbonSchoolID', $gibbonSchoolID)
            ->bindValue('status', 'Pending');

        return $this->runSelect($query)->fetchAll();
    }

    /**
     * Gets a transfer record by ID
     * @param string $gibbonStudentTransferLogID
     * @return array
     */
    public function getTransferByID($gibbonStudentTransferLogID): array
    {
        // Clean and format the ID
        $gibbonStudentTransferLogID = preg_replace('/[^0-9]/', '', $gibbonStudentTransferLogID);
        if (!empty($gibbonStudentTransferLogID)) {
            $gibbonStudentTransferLogID = str_pad($gibbonStudentTransferLogID, 12, '0', STR_PAD_LEFT);
        }

        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('gibbonStudentTransferLogID=:gibbonStudentTransferLogID')
            ->bindValue('gibbonStudentTransferLogID', $gibbonStudentTransferLogID);

        return $this->runSelect($query)->fetch() ?? [];
    }

    /**
     * Inserts a new transfer record
     * @param array $data
     * @return bool
     */
    public function insert(array $data): bool
    {
        $data['timestampCreated'] = date('Y-m-d H:i:s');

        $query = $this
            ->newInsert()
            ->into($this->getTableName())
            ->cols($data);

        return $this->runInsert($query);
    }

    /**
     * Updates an existing transfer record
     * @param string $gibbonStudentTransferLogID
     * @param array $data
     * @return bool
     */
    public function update($gibbonStudentTransferLogID, array $data): bool
    {
        $data['timestampModified'] = date('Y-m-d H:i:s');

        $query = $this
            ->newUpdate()
            ->table($this->getTableName())
            ->cols($data)
            ->where('gibbonStudentTransferLogID=:gibbonStudentTransferLogID')
            ->bindValue('gibbonStudentTransferLogID', $gibbonStudentTransferLogID);

        return $this->runUpdate($query);
    }

    /**
     * Deletes a transfer record
     * @param string $gibbonStudentTransferLogID
     * @return bool
     */
    public function delete($gibbonStudentTransferLogID): bool
    {
        $query = $this
            ->newDelete()
            ->from($this->getTableName())
            ->where('gibbonStudentTransferLogID=:gibbonStudentTransferLogID')
            ->bindValue('gibbonStudentTransferLogID', $gibbonStudentTransferLogID);

        return $this->runDelete($query);
    }

    /**
     * Gets a list of active students for transfer
     * @param int $gibbonSchoolYearID
     * @return DataSet
     */
    public function selectActiveStudents($gibbonSchoolYearID): DataSet
    {
        $query = $this
            ->newSelect()
            ->from('gibbonPerson')
            ->cols([
                'gibbonPerson.gibbonPersonID',
                'gibbonPerson.surname',
                'gibbonPerson.preferredName',
                'gibbonPerson.username',
                'gibbonYearGroup.nameShort as yearGroup'
            ])
            ->innerJoin('gibbonStudentEnrolment', 'gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->innerJoin('gibbonYearGroup', 'gibbonYearGroup.gibbonYearGroupID=gibbonStudentEnrolment.gibbonYearGroupID')
            ->where('gibbonPerson.status=:status')
            ->where('gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID')
            ->bindValue('status', 'Full')
            ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->orderBy(['surname', 'preferredName']);

        $result = $this->runSelect($query);
        return new DataSet($result->fetchAll());
    }

    /**
     * Gets a list of schools for transfer
     * @return DataSet
     */
    public function selectSchools(): DataSet
    {
        // Get school name from settings
        $query = $this
            ->newSelect()
            ->from('gibbonSetting')
            ->cols(['value'])
            ->where('scope = :scope')
            ->where('name = :name')
            ->bindValue('scope', 'System')
            ->bindValue('name', 'organisationName');

        $result = $this->runSelect($query);
        $schoolName = $result->fetchColumn(0);

        // Create a dataset with current school
        $data = [
            ['name' => $schoolName]
        ];

        return new DataSet($data);
    }

    /**
     * Override runDelete to match parent's return type
     */
    protected function runDelete(DeleteInterface $query): bool
    {
        return parent::runDelete($query);
    }

    /**
     * Override runUpdate to match parent's return type
     */
    protected function runUpdate(UpdateInterface $query): bool
    {
        return parent::runUpdate($query);
    }
}
