#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('vendor/autoload.php');
require_once('mysqlconnect.php');
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

function requestProcessor($request) {
    switch ($request['type']) {
        case 'get_notifications':
            return getNotifications($request['user_id']);
        case 'create_notifications':
            return createUpcomingEventNotifications();
        default:
            return ["status" => "error", "message" => "Invalid request type"];
    }
}
// query that prepares notifications by going thru events and user_likes
function getNotifications($userId) {
    $db = getDB();
    $query = $db->prepare("
        SELECT e.title, n.message, e.link, e.date_start, e.time_start
        FROM Notifications n
        JOIN Events e ON n.event_id = e.event_id
        WHERE n.user_id = ? AND e.date_start >= CURDATE()
        ORDER BY e.date_start, e.time_start
    ");
    $query->bind_param('i', $userId);
    $query->execute();
    $result = $query->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    return ["status" => "success", "notifications" => $notifications];
}

// query that creates the noti and sees if event happens within 7 days of current date
function createUpcomingEventNotifications() {
    $db = getDB();

    $query = $db->prepare("
        SELECT ul.id AS user_id, e.event_id, e.title, e.date_start, e.time_start,
               DATEDIFF(e.date_start, CURDATE()) AS days_until
        FROM Events e
        JOIN User_Likes ul ON e.event_id = ul.event_id
        WHERE e.date_start BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ");
    $query->execute();
    $result = $query->get_result();

    while ($event = $result->fetch_assoc()) {
        // Checks if a noti exists for user and event
        $checkNotif = $db->prepare("
            SELECT * FROM Notifications WHERE user_id = ? AND event_id = ?
        ");
        $checkNotif->bind_param('ii', $event['user_id'], $event['event_id']);
        $checkNotif->execute();
        $notifResult = $checkNotif->get_result();

        if ($notifResult->num_rows === 0) {
            $daysUntil = $event['days_until'];
            $message = "Upcoming event: " . $event['title'] . " starts in " . $daysUntil . " day" . ($daysUntil > 1 ? "s" : "") . " on " . $event['date_start'];
            $insertNotif = $db->prepare("
                INSERT INTO Notifications (user_id, event_id, message, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $insertNotif->bind_param('iis', $event['user_id'], $event['event_id'], $message);
            $insertNotif->execute();
        }
    }

    return ["status" => "success", "message" => "Notifications created for upcoming events"];
}

$server = new rabbitMQServer("testRabbitMQ.ini", "notificationsMQ");
$server->process_requests('requestProcessor');
?>
