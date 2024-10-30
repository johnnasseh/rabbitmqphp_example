<?php
require_once('vendor/autoload.php');
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'] ?? '';
$client = new rabbitMQClient("testRabbitMQ.ini", "likesMQ");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = $_POST['token'] ?? '';

    if (!$token) {
        echo json_encode(["status" => "fail", "message" => "Token not provided"]);
        exit;
    }

    try {
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
        $username = $decoded->data->username;

        $request = [
            'type' => 'get_liked_events',
            'username' => $username,
        ];

        $response = $client->send_request($request);
        if ($response['status'] === 'success') {
            echo json_encode(["status" => "success", "likedEvents" => $response['likedEvents']]);
        } else {
            echo json_encode(["status" => "fail", "message" => $response['message']]);
        }
    } catch (Exception $e) {
        echo json_encode(["status" => "fail", "message" => "Invalid or expired token"]);
    }
}
?>

