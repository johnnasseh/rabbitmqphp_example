<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('mysqlconnect.php');

$mydb = getDB();

function searchArtists($query) {
    $url = "https://musicbrainz.org/ws/2/artist?query=" . urlencode($query) . "&fmt=json";
    $response = file_get_contents($url);
    $data = json_decode($response, true);

    $artists = [];
    foreach ($data['artists'] as $artist) {
        $artists[] = [
            'id' => $artist['id'],
            'name' => $artist['name'],
            'country' => $artist['country'] ?? 'Unknown'
        ];
    }
    return ["status" => "success", "artists" => $artists];
}

function followArtist($username, $artist_id, $artist_name) {
    global $mydb;

    $stmt = $mydb->prepare("INSERT IGNORE INTO followed_artists (username, artist_id, artist_name) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $artist_id, $artist_name);
    if ($stmt->execute()) {
        return ["status" => "success", "message" => "Artist followed successfully"];
    } else {
        return ["status" => "fail", "message" => "Failed to follow artist"];
    }
}

function requestProcessor($request) {
    if (!isset($request['type'])) {
        return ["status" => "fail", "message" => "Invalid request type"];
    }

    switch ($request['type']) {
        case 'search':
            return searchArtists($request['query']);
        case 'follow_artist':
            return followArtist($request['username'], $request['artist_id'], $request['artist_name']);
        default:
            return ["status" => "fail", "message" => "Unsupported request type"];
    }
}

$server = new rabbitMQServer("testRabbitMQ.ini", "followMQ");
$server->process_requests('requestProcessor');
?>
