<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright 2010, Gibbon Foundation
Gibbon, Gibbon Education Ltd. (Hong Kong)
*/

namespace Gibbon\Module\StudentTransfer\Domain;

use Gibbon\Domain\Gateway;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\DataSet;
use Gibbon\Contracts\Database\Connection;
use Aura\SqlQuery\Common\DeleteInterface;
use Aura\SqlQuery\Common\UpdateInterface;
use Gibbon\Contracts\Database\Result;

/**
 * Transfer Gateway
 *
 * @version v29
 * @since   v29
 */
class TransferGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonStudentTransferLog';
    private static $primaryKey = 'gibbonStudentTransferLogID';
    private static $searchableColumns = [
        'schoolNameFrom',
        'schoolNameTo',
        'status'
    ];
    
    /**
     * @var Connection
     */
    private $connection;

    /**
     * Create a new gateway instance using the supplied database connection.
     * 
     * @param Connection $db
     */
    public function __construct(Connection $db)
    {
        parent::__construct($db);
        $this->connection = $db;
    }

  /**
 * Queries all transfers with detailed information
 * @param QueryCriteria $criteria Query criteria for filtering and sorting 
 * @param int|null $gibbonSchoolYearID Optional school year ID filter
 * @return DataSet
 */
public function queryTransfers(QueryCriteria $criteria, $gibbonSchoolYearID = null): DataSet
{
    try {
        // Check which timestamp columns exist
        $hasTimestampCreated = $this->tableHasColumns($this->getTableName(), ['timestampCreated']);
        $hasExportTimestamp = $this->tableHasColumns($this->getTableName(), ['exportTimestamp']);

        $cols = [
            $this->getTableName().'.gibbonStudentTransferLogID',
            $this->getTableName().'.status',
            $this->getTableName().'.schoolNameFrom',
            $this->getTableName().'.schoolNameTo', 
            'gibbonPerson.surname',
            'gibbonPerson.preferredName',
            'gibbonPerson.username',
            'gibbonYearGroup.nameShort as yearGroup'
        ];

        // Only include timestamp columns if they exist
        if ($hasExportTimestamp) {
            $cols[] = $this->getTableName().'.exportTimestamp';
        }
        if ($hasTimestampCreated) {
            $cols[] = $this->getTableName().'.timestampCreated';
        }

        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols($cols)
            ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID='.$this->getTableName().'.gibbonPersonID')
            ->innerJoin('gibbonStudentEnrolment', 'gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->innerJoin('gibbonYearGroup', 'gibbonYearGroup.gibbonYearGroupID=gibbonStudentEnrolment.gibbonYearGroupID');

        if ($gibbonSchoolYearID != null) {
            $query->where('gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID')
                  ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);
        }

        // Handle sorting based on available columns
        if (!$criteria->hasSort()) {
            // Default sorting
            $criteria->sortBy($this->getTableName().'.status');
        } else {
            $sorts = $criteria->getSortBy();
            $validSorts = [];
            
            foreach ($sorts as $column => $direction) {
                // Skip timestamp columns if they don't exist
                if (($column == 'timestampCreated' && !$hasTimestampCreated) ||
                    ($column == 'exportTimestamp' && !$hasExportTimestamp)) {
                    continue;
                }
                
                // Add valid sort columns with proper table qualification
                if (in_array($column, ['status', 'schoolNameFrom', 'schoolNameTo'])) {
                    $validSorts[$this->getTableName().'.'.$column] = $direction;
                } elseif (in_array($column, ['surname', 'preferredName'])) {
                    $validSorts['gibbonPerson.'.$column] = $direction;
                } elseif ($column == 'yearGroup') {
                    $validSorts['gibbonYearGroup.nameShort'] = $direction;
                } elseif (($column == 'timestampCreated' && $hasTimestampCreated) ||
                         ($column == 'exportTimestamp' && $hasExportTimestamp)) {
                    $validSorts[$this->getTableName().'.'.$column] = $direction;
                }
            }
            
            // Apply valid sorts
            if (!empty($validSorts)) {
                foreach ($validSorts as $column => $direction) {
                    $criteria->sortBy($column, $direction);
                }
            } else {
                $criteria->sortBy($this->getTableName().'.status');
            }
        }

        return $this->runQuery($query, $criteria);
    } catch (\PDOException $e) {
        // Log error but return empty dataset
        error_log($e->getMessage());
        return new DataSet([]);
    }
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
     * @return array|null
     */
    public function getTransferByID($gibbonStudentTransferLogID)
    {
        $data = ['gibbonStudentTransferLogID' => $gibbonStudentTransferLogID];
        $sql = "SELECT * FROM {$this->getTableName()} WHERE gibbonStudentTransferLogID=:gibbonStudentTransferLogID";
        
        return $this->connection->selectOne($sql, $data);
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
                'gibbonPersonID',
                'surname',
                'preferredName',
                'username',
                'gibbonYearGroup.nameShort as yearGroup'
            ])
            ->innerJoin('gibbonStudentEnrolment', 'gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->innerJoin('gibbonYearGroup', 'gibbonYearGroup.gibbonYearGroupID=gibbonStudentEnrolment.gibbonYearGroupID')
            ->where('status=:status')
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
     * Check if a table exists in the database
     *
     * @param string $tableName
     * @return bool
     */
    public function tableExists($tableName)
    {
        $sql = "SHOW TABLES LIKE :tableName";
        $result = $this->connection->select($sql, ['tableName' => $tableName]);
        return !empty($result);
    }

    /**
     * Check if a table has all required columns
     *
     * @param string $tableName
     * @param array $columns
     * @return bool
     */
    public function tableHasColumns($tableName, $columns)
    {
        $sql = "SHOW COLUMNS FROM {$tableName}";
        $result = $this->connection->select($sql);
        $existingColumns = array_column($result->fetchAll(), 'Field');
        
        return empty(array_diff($columns, $existingColumns));
    }

    /**
     * Log a download attempt for security and audit purposes
     * 
     * @param string $transferID The transfer ID
     * @param array $data Download attempt data including:
     *                    - ipAddress: Client IP address
     *                    - userAgent: Client user agent
     *                    - timestamp: Attempt timestamp
     *                    - status: Download status (e.g. 'Success', 'Invalid Token')
     *                    - bytesTransferred: Optional bytes transferred
     *                    - fileSize: Optional total file size
     * @return bool True if logged successfully
     */
    public function logDownloadAttempt($transferID, array $data): bool
    {
        try {
            // Convert status to success boolean
            $success = isset($data['status']) && $data['status'] === 'Success' ? 1 : 0;
            
            $query = $this
                ->newInsert()
                ->into('gibbonStudentTransferDownloadLog')
                ->cols([
                    'gibbonStudentTransferLogID' => $transferID,
                    'ipAddress' => $data['ipAddress'] ?? '',
                    'userAgent' => $data['userAgent'] ?? '',
                    'timestamp' => $data['timestamp'] ?? date('Y-m-d H:i:s'),
                    'success' => $success
                ]);

            return $this->runInsert($query);
        } catch (\PDOException $e) {
            error_log('Failed to log download attempt: ' . $e->getMessage());
            return false;
        }
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
