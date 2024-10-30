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

$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

// Retrieve followed artists via RabbitMQ
$request = [
    'type' => 'get_followed_artists',
    'user_id' => $user_id
];

$response = $client->send_request($request);

if ($response['status'] === 'success' && !empty($response['artists'])) {
    echo "<ul>";
    foreach ($response['artists'] as $artist) {
        echo "<li>" . htmlspecialchars($artist['artist_name']) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>You are not following any artists yet.</p>";
}
?>

