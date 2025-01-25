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

$(document).ready(function() {
    // Handle subscription toggles
    $('.subscription-toggle').change(function() {
        var $toggle = $(this);
        var subscriptionId = $toggle.data('subscription-id');
        var isEnabled = $toggle.prop('checked');

        $.ajax({
            url: './modules/CustomNotification/notifications_subscriptionsProcess.php',
            type: 'POST',
            data: {
                subscriptionId: subscriptionId,
                enabled: isEnabled ? 'Y' : 'N'
            },
            success: function(response) {
                var data = JSON.parse(response);
                if (data.success) {
                    // Update UI to reflect the change
                    var $status = $toggle.closest('.subscription-item').find('.subscription-status');
                    $status.text(isEnabled ? 'Active' : 'Inactive');
                    $status.toggleClass('active', isEnabled);
                    $status.toggleClass('inactive', !isEnabled);
                } else {
                    // Revert the toggle if there was an error
                    $toggle.prop('checked', !isEnabled);
                    alert(data.error || 'An error occurred while updating your subscription.');
                }
            },
            error: function() {
                // Revert the toggle on network error
                $toggle.prop('checked', !isEnabled);
                alert('Could not connect to the server. Please try again.');
            }
        });
    });

    // Handle notification read/unread toggle
    $('.notification-item').click(function() {
        var $notification = $(this);
        var notificationId = $notification.data('notification-id');

        if ($notification.hasClass('unread')) {
            $.post('./modules/CustomNotification/notifications_markReadProcess.php', {
                notificationId: notificationId
            }, function(response) {
                var data = JSON.parse(response);
                if (data.success) {
                    $notification.removeClass('unread');
                }
            });
        }
    });
});
