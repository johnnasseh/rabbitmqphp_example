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

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];
$hasdata_api_key = $env['HASDATA_API'];
$mydb = getDB();

function performSearch($query) {
	global $hasdata_api_key;
try {
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
		log_message("curl error #:" . $err);
		return ["status" => "fail", "message" => "API Request failed"];
	}

	$apiData = json_decode($response, true);
	        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message("Failed to decode JSON response from API: " . json_last_error_msg());
            return ["status" => "fail", "message" => "Failed to decode API response"];
        }
	return ["status" => "success", "data" => $apiData];
} catch (Exception $e) {
        log_message("Exception in performSearch: " . $e->getMessage());
        return ["status" => "fail", "message" => "Unexpected error during search"];
    }
}

function saveLikedEvents($username, $eventData) {
	global $mydb;

	try {
	    $stmt = $mydb->prepare("SELECT id FROM Users WHERE username = ?");
        if ($stmt === false) {
            log_message("Failed to prepare statement for fetching user ID: " . $mydb->error);
            return ["status" => "fail", "message" => "Database error"];
        }	   
 $stmt->bind_param("s", $username);
            if (!$stmt->execute()) {
            log_message("Failed to execute statement for fetching user ID for username '$username': " . $stmt->error);
            return ["status" => "fail", "message" => "Failed to fetch user ID"];
        }
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
	    log_message("User not found for username: $username");
        return ["status" => "fail", "message" => "User not found"];
    }
    $userId = $user['id'];
    $title = $eventData['title'];
    $date_start = !empty($eventData['date']['startDate']) ? date('Y-m-d', strtotime($eventData['date']['startDate'] . ' ' . date('Y'))) : null;
    $time_start = !empty($eventData['date']['when']) ? date('H:i:s', strtotime($eventData['date']['when'])) : null;
    $location = isset($eventData['address'][1]) ? $eventData['address'][1] : null; // City/State part
    $address = !empty($eventData['address']) ? implode(', ', $eventData['address']) : null;
    $description = $eventData['description'];
    $thumbnail = $eventData['thumbnail'];
    $link = $eventData['link'];
    $venue_name = $eventData['venue']['name'] ?? null;
    $venue_reviews = $eventData['venue']['reviews'] ?? null;
    $venue_link = $eventData['venue']['link'] ?? null;

    $eventInsert = $mydb->prepare("INSERT INTO Events (title, date_start, time_start, location, address, description, thumbnail, link, venue_name, venue_reviews, venue_link)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE event_id=LAST_INSERT_ID(event_id)");
           if ($eventInsert === false) {
            log_message("Failed to prepare statement for inserting event data: " . $mydb->error);
            return ["status" => "fail", "message" => "Database error"];
        }

    $eventInsert->bind_param("sssssssssis",
        $title,
        $date_start,
        $time_start,
        $location,
        $address,
        $description,
        $thumbnail,
        $link,
        $venue_name,
        $venue_reviews,
        $venue_link
    );
            if (!$eventInsert->execute()) {
            log_message("Failed to execute statement for inserting event data: " . $eventInsert->error);
            return ["status" => "fail", "message" => "Failed to insert event data"];
        }
    $eventId = $eventInsert->insert_id;

    $likeInsert = $mydb->prepare("INSERT IGNORE INTO User_Likes (id, event_id) VALUES (?, ?)");
           if ($likeInsert === false) {
            log_message("Failed to prepare statement for inserting into User_Likes: " . $mydb->error);
            return ["status" => "fail", "message" => "Database error"];
        }
    $likeInsert->bind_param("ii", $userId, $eventId);
            if (!$likeInsert->execute()) {
            log_message("Failed to execute statement for inserting into User_Likes: " . $likeInsert->error);
            return ["status" => "fail", "message" => "Failed to like event"];
        }
log_message("User '$username' liked event ID $eventId");
    return $likeInsert->affected_rows > 0 ? ["status" => "success"] : ["status" => "fail", "message" => "Event already liked"];
} catch (Exception $e) {
        log_message("Exception in saveLikedEvents: " . $e->getMessage());
        return ["status" => "fail", "message" => "Unexpected error while saving liked events"];
    }
}
    function requestProcessor($request) {
    global $jwt_secret;

    error_log("Request received in search_handler:");
    error_log(print_r($request, true));

    if (!isset($request['type'])) {
	    log_message("Invalid request: Missing 'type' field");
        return ["status" => "fail", "message" => "Invalid request type"];
    }
    try {
        switch ($request['type']) {
            case 'search':
                if (!isset($request['query'])) {
                    log_message("Error: Missing 'query' parameter in search request");
                    return ["status" => "fail", "message" => "Search query not provided"];
                }
                return performSearch($request['query']);

            case 'like':
                if (!isset($request['username']) || !isset($request['event'])) {
                    log_message("Error: Missing 'username' or 'event' in like request");
                    return ["status" => "fail", "message" => "Missing username or event data"];
                }
                return saveLikedEvents($request['username'], $request['event']);

            default:
                log_message("Unsupported request type: " . $request['type']);
                return ["status" => "fail", "message" => "Unsupported request type"];
        }
    } catch (Exception $e) {
        log_message("Exception in requestProcessor: " . $e->getMessage());
        return ["status" => "fail", "message" => "Error processing request"];
    }
}    
$server = new rabbitMQServer("testRabbitMQ.ini", "searchMQ");
$server->process_requests('requestProcessor');
?>
