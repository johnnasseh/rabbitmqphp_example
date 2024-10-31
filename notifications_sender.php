<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('vendor/autoload.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];
$client = new rabbitMQClient("testRabbitMQ.ini", "notificationsMQ");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = $_POST['token'] ?? '';

    if (!$token) {
        echo json_encode(["status" => "fail", "message" => "Token not provided"]);
        exit;
    }

    try {
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
        $userId = $decoded->data->id;

        $request = [
            'type' => 'get_notifications',
            'user_id' => $userId
        ];

        $response = $client->send_request($request);

        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode(["status" => "fail", "message" => "Invalid or expired token"]);
    }
}
