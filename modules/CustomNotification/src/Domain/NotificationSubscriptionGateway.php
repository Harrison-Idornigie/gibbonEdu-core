<?php
namespace Gibbon\Module\CustomNotification\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\DataSet;

/**
 * Notification Subscription Gateway
 *
 * @version v23.0.00
 * @since   v23.0.00
 */
class NotificationSubscriptionGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'CustomNotificationSubscription';
    private static $primaryKey = 'id';
    private static $searchableColumns = ['eventType'];

    /**
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function querySubscriptions(QueryCriteria $criteria, $gibbonPersonID = null): DataSet
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'id',
                'gibbonPersonID',
                'eventType',
                'notifyBy',
                'studentID',
                'active',
                'timestamp',
                'student.preferredName as studentPreferredName',
                'student.surname as studentSurname'
            ])
            ->leftJoin('gibbonPerson as student', 'student.gibbonPersonID=CustomNotificationSubscription.studentID');

        if ($gibbonPersonID) {
            $query->where('CustomNotificationSubscription.gibbonPersonID = :gibbonPersonID')
                  ->bindValue('gibbonPersonID', $gibbonPersonID);
        }

        return $this->runQuery($query, $criteria);
    }

    /**
     * @param string $eventType
     * @param int|null $studentID
     * @return array
     */
    public function getSubscribedRecipients(string $eventType, ?int $studentID = null): array
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['gibbonPersonID', 'notifyBy'])
            ->where('eventType = :eventType')
            ->where('active = "Y"')
            ->bindValue('eventType', $eventType);

        if ($studentID) {
            $query->where('(studentID IS NULL OR studentID = :studentID)')
                  ->bindValue('studentID', $studentID);
        }

        return $this->runSelect($query)->fetchAll();
    }

    /**
     * @param array $data
     * @return bool
     */
    public function insertSubscription(array $data): bool
    {
        $query = $this
            ->newInsert()
            ->into($this->getTableName())
            ->cols($data);

        return $this->runInsert($query);
    }

    /**
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateSubscription(int $id, array $data): bool
    {
        $query = $this
            ->newUpdate()
            ->table($this->getTableName())
            ->cols($data)
            ->where('id = :id')
            ->bindValue('id', $id);

        return $this->runUpdate($query);
    }

    /**
     * @param int $id
     * @return bool
     */
    public function deleteSubscription(int $id): bool
    {
        $query = $this
            ->newDelete()
            ->from($this->getTableName())
            ->where('id = :id')
            ->bindValue('id', $id);

        return $this->runDelete($query);
    }
}
