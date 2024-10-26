<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('vendor/autoload.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];

$client = new rabbitMQClient("testRabbitMQ.ini", "friendsMQ");

$token = $_POST['token'] ?? '';
$friends = [];
$pendingRequests = [];
$incomingRequests = [];

if ($token) {
    try {
        // Decode token to get username
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
        $username = $decoded->data->username;

        // Prepare the request for rabbit
        $rabbitRequest = [
            'type' => 'get_friends_data',
            'username' => $username,
        ];

        // Send request to rabbit and get the response
        $response = $client->send_request($rabbitRequest, "friends_data_responses");

        if ($response['status'] === 'success') {
            $friends = $response['friends'];
            $pendingRequests = $response['pendingRequests'];
            $incomingRequests = $response['incomingRequests'];
        } else {
            echo json_encode(["status" => "fail", "message" => $response['message']]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(["status" => "fail", "message" => "Invalid or expired token"]);
        exit;
    }
} else {
    echo json_encode(["status" => "fail", "message" => "Token not provided"]);
    exit;
}

// Handle user search
if (isset($_POST['search_username'])) {
    $searchUsername = $_POST['search_username'];

    // Search request for rabbit
    $rabbitRequest = [
        'type' => 'search_users',
        'search_username' => $searchUsername,
    ];

    // Send request to rabbit and get response
    $searchResponse = $client->send_request($rabbitRequest, "search_users_responses");

    if ($searchResponse['status'] === 'success') {
        echo json_encode(['status' => 'success', 'users' => $searchResponse['users']]);
        exit;
    } else {
        echo json_encode(['status' => 'fail', 'message' => 'User not found']);
        exit;
    }
}
