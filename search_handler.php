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

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];
$hasdata_api_key = $env['HASDATA_API'];
$mydb = getDB();

function performSearch($query) {
	global $hasdata_api_key;

	$encodedQuery = urlencode($query);
	$curl = curl_init();
	// curl request
	
	curl_setopt_array($curl, [
       	CURLOPT_URL => "https://api.hasdata.com/scrape/google/events?q={$encodedQuery}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "x-api-key: $hasdata_api_key"
            ],
	]);

	$response = curl_exec($curl);
	$err = curl_error($curl);
	curl_close($curl);

	if ($err) {
		error_log("curl error #:" . $err);
		return ["status" => "fail", "message" => "API Request failed"];
	}

	$apiData = json_decode($response, true);
	return ["status" => "success", "data" => $apiData];
}

function saveLikedEvents($username, $eventData) {
	global $mydb;

	    $stmt = $mydb->prepare("SELECT id FROM Users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        return ["status" => "fail", "message" => "User not found"];
    }
    $userId = $user['id'];

	$eventInsert = $mydb->prepare("INSERT INTO Events (title, date_start, time_start, location, address, description, thumbnail, link, venue_name, venue_reviews, venue_link)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE event_id=LAST_INSERT_ID(event_id)");
    $eventInsert->bind_param("sssssssssis",
        $eventData['title'],
        $eventData['date_start'],
        $eventData['time_start'],
        $eventData['location'],
        $eventData['address'],
        $eventData['description'],
        $eventData['thumbnail'],
        $eventData['link'],
        $eventData['venue_name'],
        $eventData['venue_reviews'],
        $eventData['venue_link']
    );
    $eventInsert->execute();
    $eventId = $eventInsert->insert_id;

    //associate event with user in user_likes
        $likeInsert = $mydb->prepare("INSERT IGNORE INTO User_Likes (id, event_id) VALUES (?, ?)");
    $likeInsert->bind_param("ii", $userId, $eventId);
    $likeInsert->execute();

    return $likeInsert->affected_rows > 0 ? ["status" => "success"] : ["status" => "fail", "message" => "Event already liked"];
}
function requestProcessor($request) {
    global $jwt_secret;

    error_log("Request received in search_handler:");
    error_log(print_r($request, true));

    if (!isset($request['type'])) {
        return ["status" => "fail", "message" => "Invalid request type"];
    }

    switch ($request['type']) {
        case 'search':
            if (!isset($request['query'])) {
                return ["status" => "fail", "message" => "Search query not provided"];
            }
            return performSearch($request['query']);

        case 'like':
            if (!isset($request['username']) || !isset($request['event'])) {
                return ["status" => "fail", "message" => "Missing username or event data"];
            }
            return saveLikedEvents($request['username'], $request['event']);

        default:
            return ["status" => "fail", "message" => "Unsupported request type"];
    }
}
$server = new rabbitMQServer("testRabbitMQ.ini", "searchMQ");
$server->process_requests('requestProcessor');
?>
