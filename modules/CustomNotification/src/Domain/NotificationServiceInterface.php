<?php
/*
Gibbon: the flexible, open school platform
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

namespace Gibbon\Module\CustomNotification\Domain;

/**
 * NotificationServiceInterface
 *
 * @version v23
 * @since   v23
 */
interface NotificationServiceInterface
{
    /**
     * Send a notification to specified recipients
     *
     * @param string $eventType
     * @param array $recipients Array of recipient IDs
     * @param string $message
     * @param array $data Additional data for the notification
     * @return bool
     */
    public function sendNotification(string $eventType, array $recipients, string $message, array $data = []): bool;

    /**
     * Get notifications for a user
     *
     * @param string $gibbonPersonID
     * @param array $filters Optional filters
     * @return array
     */
    public function getNotificationsForUser(string $gibbonPersonID, array $filters = []): array;

    /**
     * Mark a notification as read
     *
     * @param string $notificationId
     * @param string $gibbonPersonID
     * @return bool
     */
    public function markNotificationAsRead(string $notificationId, string $gibbonPersonID): bool;

    /**
     * Get user's notification preferences
     *
     * @param string $gibbonPersonID
     * @return array
     */
    public function getUserPreferences(string $gibbonPersonID): array;

    /**
     * Update user's notification preferences
     *
     * @param string $gibbonPersonID
     * @param array $preferences
     * @return bool
     */
    public function updateUserPreferences(string $gibbonPersonID, array $preferences): bool;
}
