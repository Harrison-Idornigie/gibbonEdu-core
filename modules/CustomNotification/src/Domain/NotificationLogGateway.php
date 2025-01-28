<?php
namespace Gibbon\Module\CustomNotification\Domain;

use Aura\SqlQuery\Common\DeleteInterface;
use Aura\SqlQuery\Common\UpdateInterface;
use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\DataSet;

/**
 * Notification Log Gateway
 *
 * @version v23.0.00
 * @since   v23.0.00
 */
class NotificationLogGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'CustomNotificationLog';
    private static $primaryKey = 'id';
    private static $searchableColumns = ['eventType', 'recipientType', 'message'];

    protected function getDefaultFilterRules(QueryCriteria $criteria)
    {
        return [
            'eventType' => function ($query, $eventType) {
                return $query
                    ->where('CustomNotificationLog.eventType = :eventType')
                    ->bindValue('eventType', $eventType);
            },
            'status' => function ($query, $status) {
                return $query
                    ->where('CustomNotificationLog.status = :status')
                    ->bindValue('status', $status);
            },
            'dateStart' => function ($query, $dateStart) {
                return $query
                    ->where('DATE(CustomNotificationLog.timestamp) >= :dateStart')
                    ->bindValue('dateStart', $dateStart);
            },
            'dateEnd' => function ($query, $dateEnd) {
                return $query
                    ->where('DATE(CustomNotificationLog.timestamp) <= :dateEnd')
                    ->bindValue('dateEnd', $dateEnd);
            }
        ];
    }

    /**
     * @param DeleteInterface $query
     * @return bool
     */
    public function runDelete(DeleteInterface $query): bool
    {
        return parent::runDelete($query);
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
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryNotificationLogs(QueryCriteria $criteria): DataSet
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'CustomNotificationLog.id',
                'CustomNotificationLog.eventType',
                'CustomNotificationLog.recipientType',
                'CustomNotificationLog.recipientID',
                'CustomNotificationLog.notificationType',
                'CustomNotificationLog.status',
                'CustomNotificationLog.message',
                'CustomNotificationLog.error',
                'CustomNotificationLog.timestamp',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.username'
            ])
            ->leftJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID=CustomNotificationLog.recipientID');

        if ($criteria->hasFilter('eventType')) {
            $query->where('CustomNotificationLog.eventType=:eventType')
                  ->bindValue('eventType', $criteria->getFilterValue('eventType'));
        }

        if ($criteria->hasFilter('status')) {
            $query->where('CustomNotificationLog.status=:status')
                  ->bindValue('status', $criteria->getFilterValue('status'));
        }

        if ($criteria->hasFilter('dateStart')) {
            $query->where('DATE(CustomNotificationLog.timestamp) >= :dateStart')
                  ->bindValue('dateStart', $criteria->getFilterValue('dateStart'));
        }

        if ($criteria->hasFilter('dateEnd')) {
            $query->where('DATE(CustomNotificationLog.timestamp) <= :dateEnd')
                  ->bindValue('dateEnd', $criteria->getFilterValue('dateEnd'));
        }

        return $this->runQuery($query, $criteria);
    }
}
