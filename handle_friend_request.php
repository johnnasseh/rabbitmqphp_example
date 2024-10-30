<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('vendor/autoload.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];

$token = $_POST['token'] ?? '';
$friendUsername = $_POST['friend_username'] ?? '';
$action = $_POST['action'] ?? '';

if ($token && $friendUsername && $action) {
    try {
        // Decode JWT to verify user identity
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
        $username = $decoded->data->username;

        // sets up rabbit client
        $client = new rabbitMQClient("testRabbitMQ.ini", "friendsMQ");

        // makes request for rabbit
        $request = [
            'type' => $action,  // this is to send, accept, or decline friend requests
            'username' => $username,
            'friend_username' => $friendUsername
        ];

        // sends request to rabbit server
        $response = $client->send_request($request);

        // checks server response and makes it json
        if ($response['status'] === 'success') {
            echo json_encode(['status' => 'success', 'message' => $response['message']]);
        } else {
            echo json_encode(['status' => 'fail', 'message' => $response['message']]);
        }
    } catch (Exception $e) {
        // return error if token couldnt decode
        error_log("Token decoding error: " . $e->getMessage());
        echo json_encode(['status' => 'fail', 'message' => 'Invalid or expired token']);
    }
} else {
    // error for missing token
    error_log("Missing parameters: token, friend_username, or action.");
    echo json_encode(['status' => 'fail', 'message' => 'Missing parameters']);
}
?>
