#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('mysqlconnect.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);
error_log("likes handler started");
$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];
$mydb = getDB();

function getLikedEvents($username) {
    global $mydb;

    $stmt = $mydb->prepare("
        SELECT Events.event_id, Events.title, Events.date_start, Events.time_start, Events.location, Events.address, 
               Events.description, Events.thumbnail, Events.link, Events.venue_name, Events.venue_reviews, Events.venue_link
        FROM Events
        JOIN User_Likes ON Events.event_id = User_Likes.event_id
        JOIN Users ON User_Likes.id = Users.id
        WHERE Users.username = ?
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    $likedEvents = [];
    while ($row = $result->fetch_assoc()) {
        $likedEvents[] = $row;
    }

        error_log("Liked events fetched for user '$username': " . json_encode($likedEvents));
    return ["status" => "success", "likedEvents" => $likedEvents];
}

function requestProcessor($request) {
    global $jwt_secret;

    error_log("Request received in likes_handler:");
    error_log(print_r($request, true));

    if (!isset($request['type'])) {
        return ["status" => "fail", "message" => "Invalid request type"];
    }
    switch ($request['type']) {
        case 'get_liked_events':
            if (!isset($request['username'])) {
                return ["status" => "fail", "message" => "Username not provided"];
            }
            $username = $request['username'];
            error_log("Fetching liked events for username: " . $username);
            return getLikedEvents($username);

        default:
            error_log("Unsupported request type: " . $request['type']);
            return ["status" => "fail", "message" => "Unsupported request type"];
    }
}

$server = new rabbitMQServer("testRabbitMQ.ini", "likesMQ");
$server->process_requests('requestProcessor');
?>

