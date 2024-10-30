<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('vendor/autoload.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];
$client = new rabbitMQClient("testRabbitMQ.ini", "friendsMQ");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = $_POST['type'] ?? '';
    $token = $_POST['token'] ?? '';
    $friend_username = $_POST['friend_username'] ?? '';

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

    $response = ["status" => "fail", "message" => "Invalid request"];

    if (in_array($type, ['get_friends_data', 'send_request', 'accept', 'decline'])) {
        $request = [
            'type' => $type,
            'username' => $username,
            'friend_username' => $friend_username
        ];
        try {
            $response = $client->send_request($request);
        } catch (Exception $e) {
            $response = ["status" => "fail", "message" => "Server error occurred"];
        }
    }

    echo json_encode($response);
    exit;
}
