<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('vendor/autoload.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'] ?? '';
$client = new rabbitMQClient("testRabbitMQ.ini", "followMQ");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $token = $_POST['token'] ?? '';

    if (!$token) {
        echo json_encode(["status" => "fail", "message" => "Token not provided"]);
        exit;
    }

    try {
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
        $username = $decoded->data->username;
    } catch (Exception $e) {
        echo json_encode(["status" => "fail", "message" => "Invalid or expired token"]);
        exit;
    }

    if ($type === 'search') {
        $query = $_POST['query'] ?? '';
        
        if (!$query) {
            echo json_encode(["status" => "fail", "message" => "Search query not provided"]);
            exit;
        }

        // Search MusicBrainz API for artists
        $url = "https://musicbrainz.org/ws/2/artist?query=" . urlencode($query) . "&fmt=json";
        $options = [
            'http' => [
                'header' => "User-Agent: YourAppName/1.0\r\n"
            ]
        ];
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        
        if ($response === FALSE) {
            echo json_encode(["status" => "fail", "message" => "Error retrieving data from MusicBrainz"]);
            exit;
        }

        $artistData = json_decode($response, true);
        $artists = array_map(function($artist) {
            return [
                'id' => $artist['id'],
                'name' => $artist['name'],
                'country' => $artist['country'] ?? 'N/A'
            ];
        }, $artistData['artists']);

        echo json_encode(["status" => "success", "artists" => $artists]);
        
    } elseif ($type === 'follow') {
        $artist_id = $_POST['artist_id'] ?? '';
        $artist_name = $_POST['artist_name'] ?? '';

        if (empty($artist_id) || empty($artist_name)) {
            echo json_encode(["status" => "fail", "message" => "Artist information missing"]);
            exit;
        }

        $rabbitRequest = [
            'type' => 'follow_artist',
            'username' => $username,
            'artist_id' => $artist_id,
            'artist_name' => $artist_name
        ];

        try {
            $response = $client->send_request($rabbitRequest);
            echo json_encode($response);
        } catch (Exception $e) {
            echo json_encode(["status" => "fail", "message" => "Failed to follow artist"]);
        }
    } else {
        echo json_encode(["status" => "fail", "message" => "Invalid request type"]);
    }
    exit;
}
?>
