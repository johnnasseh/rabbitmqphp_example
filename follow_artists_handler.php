#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('mysqlconnect.php');

function requestProcessor($request) {
	if (!isset($request['type'])) {
    	return ["status" => "error", "message" => "Invalid request type"];
	}

	switch ($request['type']) {
    	case 'search':
        	$query = urlencode($request['query']);
        	$url = "https://musicbrainz.org/ws/2/artist?query=$query&fmt=json&limit=5";
        	$response = file_get_contents($url, false, stream_context_create([
            	"http" => ["header" => "User-Agent: YourAppName/1.0\r\n"]
        	]));
        	$data = json_decode($response, true);
        	$artists = array_map(fn($artist) => [
            	"id" => $artist['id'], "name" => $artist['name'], "disambiguation" => $artist['disambiguation'] ?? ''
        	], $data['artists'] ?? []);
        	return ["status" => "success", "artists" => $artists];

    	case 'follow':
        	$username = $request['username'];
        	$artist_id = $request['artist_id'];
        	$artist_name = $request['artist_name'];
        	$db = getDB();

        	try {
            	$stmt = $db->prepare("INSERT INTO user_follows (user_id, entity_id, entity_type, follow_date) VALUES ((SELECT id FROM Users WHERE username = ?), ?, 'artist', NOW())");
            	$stmt->bind_param("ss", $username, $artist_id);
            	$stmt->execute();

            	if ($stmt->affected_rows > 0) {
                	return ["status" => "success", "message" => "Artist followed"];
            	} else {
                	return ["status" => "fail", "message" => "Error following artist or already followed"];
            	}
        	} catch (mysqli_sql_exception $e) {
            	error_log("Database error: " . $e->getMessage());
            	return ["status" => "error", "message" => "Database error occurred"];
        	}

    	default:
        	return ["status" => "error", "message" => "Unknown request type"];
	}
}

$server = new rabbitMQServer("testRabbitMQ.ini", "followMQ");
$server->process_requests('requestProcessor');
