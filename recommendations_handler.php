#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('mysqlconnect.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);


$env = parse_ini_file('.env');
$apiKey = $env['HASDATA_API']; 
$mydb = getDB();

function getRecommendations($username)
{
	global $mydb, $apiKey;

	
	$stmt = $mydb->prepare("
    	SELECT Events.venue_name
    	FROM Events
    	JOIN User_Likes ON Events.event_id = User_Likes.event_id
    	JOIN Users ON User_Likes.id = Users.id
    	WHERE Users.username = ?
    	LIMIT 1
	");
	$stmt->bind_param("s", $username);
	$stmt->execute();
	$result = $stmt->get_result();
	$likedEvent = $result->fetch_assoc();

	if (!$likedEvent) {
    	return ["status" => "success", "recommendations" => []]; 
	}

	$venueName = $likedEvent['venue_name'];
	error_log("Fetching recommendations for venue: $venueName");

	
	$url = "https://api.hasdata.com/scrape/google/events?q=" . urlencode($venueName);
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, ["x-api-key: $apiKey"]);
	$response = curl_exec($ch);
	curl_close($ch);

	error_log("API Response: " . $response); 
	$apiEvents = json_decode($response, true);

	
	if (!isset($apiEvents['eventsResults']) || empty($apiEvents['eventsResults'])) {
    	error_log("No events found in API response");
    	return ["status" => "success", "recommendations" => []];
	}

	
	$recommendations = [];
	foreach ($apiEvents['eventsResults'] as $event) {
    	$recommendations[] = [
        	'title' => $event['title'],
        	'date' => $event['date']['when'] ?? '',
        	'address' => implode(", ", $event['address'] ?? []),
        	'thumbnail' => $event['thumbnail'] ?? '',
        	'link' => $event['link'] ?? '',
        	'description' => $event['description'] ?? ''
    	];
	}

	error_log("Recommendations: " . json_encode($recommendations)); 
	return ["status" => "success", "recommendations" => $recommendations];
}

function requestProcessor($request)
{
	if (!isset($request['type']) || $request['type'] !== 'get_recommendations') {
    	return ["status" => "fail", "message" => "Invalid request type"];
	}

	if (!isset($request['username'])) {
    	return ["status" => "fail", "message" => "Username not provided"];
	}

	$username = $request['username'];
	return getRecommendations($username);
}

$server = new rabbitMQServer("testRabbitMQ.ini", "recommendationsMQ");
$server->process_requests('requestProcessor');
?>



