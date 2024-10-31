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

$mydb = getDB();
$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'] ?? '';
function getEventDetails($eventId) {
    global $mydb;
try {
    $eventQuery = $mydb->prepare("SELECT * FROM Events WHERE event_id = ?");
    $eventQuery->bind_param("i", $eventId);
    $eventQuery->execute();
    $eventResult = $eventQuery->get_result()->fetch_assoc();

    $commentQuery = $mydb->prepare("
        SELECT Comments.comment, Users.username 
        FROM Comments 
        JOIN Users ON Comments.user_id = Users.id 
        WHERE Comments.event_id = ?
    ");
    $commentQuery->bind_param("i", $eventId);
    $commentQuery->execute();
    $commentsResult = $commentQuery->get_result();

    $comments = [];
    while ($comment = $commentsResult->fetch_assoc()) {
        $comments[] = $comment;
    }

    return ["status" => "success", "event" => $eventResult, "comments" => $comments];
    } catch (Exception $e) {
        error_log("Error fetching event details: " . $e->getMessage());
        return ["status" => "fail", "message" => "Error fetching event details"];
    }
}
function addComment($userId, $eventId, $comment) {
    global $mydb;

   try {

    $stmt = $mydb->prepare("INSERT INTO Comments (event_id, user_id, comment) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $eventId, $userId, $comment);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        return ["status" => "success", "message" => "Comment added successfully"];
    } else {
        return ["status" => "fail", "message" => "Failed to add comment"];
    }
    } catch (Exception $e) {
        error_log("Error adding comment: " . $e->getMessage());
        return ["status" => "fail", "message" => "Error adding comment"];
    }
}
function requestProcessor($request) {
    global $jwt_secret;

    error_log("Request received in comments_handler:");
    error_log(print_r($request, true));

    if (!isset($request['type'])) {
        return ["status" => "fail", "message" => "Invalid request type"];
    }

    try {
        switch ($request['type']) {
            case 'get_event_details':
                if (!isset($request['event_id'])) {
                    throw new Exception("Event ID not provided");
                }
                return getEventDetails($request['event_id']);
case 'add_comment':
    if (!isset($request['token']) || !isset($request['event_id']) || !isset($request['comment'])) {
        throw new Exception("Missing token, event ID, or comment");
    }

   
    $decoded = JWT::decode($request['token'], new Key($jwt_secret, 'HS256'));
    $username = $decoded->data->username;

    $stmt = $mydb->prepare("SELECT id FROM Users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        throw new Exception("User not found");
    }

    $userId = $user['id']; 

    return addComment($userId, $request['event_id'], $request['comment']); 
	    default:
                throw new Exception("Unsupported request type");
        }
    } catch (Exception $e) {
        error_log("Error processing request: " . $e->getMessage());
        return ["status" => "fail", "message" => $e->getMessage()];
    }
}

$server = new rabbitMQServer("testRabbitMQ.ini", "commentsMQ");
$server->process_requests('requestProcessor');
?>

