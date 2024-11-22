#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('vendor/autoload.php');
require_once('mysqlconnect.php');
require_once('log_utils.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

$mydb = getDB();
$env = parse_ini_file('.env');
if ($env === false) {
    error_log("Failed to load .env file");
    die("Critical error: Unable to load environment variables.");
}

function requestProcessor($request) {

	    if (!isset($request['type'])) {
        log_message("Error: Request missing 'type' field");
        return ["status" => "error", "message" => "Invalid request type"];
	    }

    switch ($request['type']) {
    case 'get_notifications':
	                if (!isset($request['user_id'])) {
                log_message("Error: Missing 'user_id' for 'get_notifications' request");
                return ["status" => "error", "message" => "Missing user ID"];
            }
            return getNotifications($request['user_id']);
        case 'create_notifications':
            return createUpcomingEventNotifications();
	default:
	     log_message("Error: Unsupported request type - " . $request['type']);
            return ["status" => "error", "message" => "Invalid request type"];
    }
}
// query that prepares notifications by going thru events and user_likes
function getNotifications($userId) {
	global $mydb;
	try {
    $query = $mydb->prepare("
        SELECT e.title, n.message, e.link, e.date_start, e.time_start
        FROM Notifications n
        JOIN Events e ON n.event_id = e.event_id
        WHERE n.user_id = ? AND e.date_start >= CURDATE()
        ORDER BY e.date_start, e.time_start
	");
            if ($query === false) {
            log_message("Failed to prepare statement for fetching notifications: " . $mydb->error);
            return ["status" => "fail", "message" => "Failed to prepare query for notifications."];
        }
    $query->bind_param('i', $userId);
            if (!$query->execute()) {
            log_message("Failed to execute query for notifications for user ID $userId: " . $query->error);
            return ["status" => "fail", "message" => "Failed to fetch notifications."];
        }
    $result = $query->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    return ["status" => "success", "notifications" => $notifications];
} catch (Exception $e) {
        log_message("Error in getNotifications for user ID $userId: " . $e->getMessage());
        return ["status" => "fail", "message" => "Error fetching notifications."];
    }
}

// query that creates the noti and sees if event happens within 7 days of current date
function createUpcomingEventNotifications() {
    global $mydb;
try {
    $query = $mydb->prepare("
        SELECT ul.id AS user_id, e.event_id, e.title, e.date_start, e.time_start,
               DATEDIFF(e.date_start, CURDATE()) AS days_until
        FROM Events e
        JOIN User_Likes ul ON e.event_id = ul.event_id
        WHERE e.date_start BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
	");
            if ($query === false) {
            log_message("Failed to prepare statement for fetching upcoming events: " . $mydb->error);
            return ["status" => "fail", "message" => "Failed to prepare query for upcoming events."];
        }
            if (!$query->execute()) {
            log_message("Failed to execute query for fetching upcoming events: " . $query->error);
            return ["status" => "fail", "message" => "Failed to fetch upcoming events."];
        }
    $result = $query->get_result();

    while ($event = $result->fetch_assoc()) {
        // Checks if a noti exists for user and event
        $checkNotif = $mydb->prepare("
            SELECT * FROM Notifications WHERE user_id = ? AND event_id = ?
	    ");
	            if ($checkNotif === false) {
                log_message("Failed to prepare statement for checking existing notification: " . $mydb->error);
                continue;
            }
        $checkNotif->bind_param('ii', $event['user_id'], $event['event_id']);
                    if (!$checkNotif->execute()) {
                log_message("Failed to execute query for checking existing notification for user ID {$event['user_id']} and event ID {$event['event_id']}: " . $checkNotif->error);
                continue;
            }
        $notifResult = $checkNotif->get_result();

        if ($notifResult->num_rows === 0) {
            $daysUntil = $event['days_until'];
            $message = "Upcoming event: " . $event['title'] . " starts in " . $daysUntil . " day" . ($daysUntil > 1 ? "s" : "") . " on " . $event['date_start'];
            $insertNotif = $mydb->prepare("
                INSERT INTO Notifications (user_id, event_id, message, created_at)
                VALUES (?, ?, ?, NOW())
		");

	                    if ($insertNotif === false) {
                    log_message("Failed to prepare statement for inserting notification: " . $mydb->error);
                    continue;
                }
            $insertNotif->bind_param('iis', $event['user_id'], $event['event_id'], $message);
                           if (!$insertNotif->execute()) {
                    log_message("Failed to insert notification for user ID {$event['user_id']} and event ID {$event['event_id']}: " . $insertNotif->error);
                } else {
                    log_message("Notification created for user ID {$event['user_id']} for event ID {$event['event_id']}: $message");
                }
            }
        }

        return ["status" => "success", "message" => "Notifications created for upcoming events"];

    } catch (Exception $e) {
        log_message("Error in createUpcomingEventNotifications: " . $e->getMessage());
        return ["status" => "fail", "message" => "Error creating notifications for upcoming events."];
    }
}

$server = new rabbitMQServer("testRabbitMQ.ini", "notificationsMQ");
$server->process_requests('requestProcessor');
?>
