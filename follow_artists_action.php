<?php
require_once('vendor/autoload.php');
require_once('rabbitMQLib.inc');
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];

if (isset($_COOKIE['token'])) {
    $token = $_COOKIE['token'];
    try {
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
        $user_data = (array)$decoded->data;
        $user_id = $user_data['username'];
    } catch (Exception $e) {
        echo "<p>Unauthorized access. Please log in.</p>";
        exit;
    }
} else {
    echo "<p>No valid token found. Please log in.</p>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $artist_id = $_POST['artist_id'];
    $artist_name = $_POST['artist_name'];

    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

    // Send request to follow the artist
    $request = [
        'type' => 'follow_artist',
        'user_id' => $user_id,
        'artist_id' => $artist_id,
        'artist_name' => $artist_name
    ];

    $response = $client->send_request($request);

    if ($response['status'] === 'success') {
        echo "<p>You are now following $artist_name!</p>";
    } else {
        echo "<p>Error following $artist_name: {$response['message']}</p>";
    }
}
?>
