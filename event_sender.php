<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('vendor/autoload.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'] ?? '';
$client = new rabbitMQClient("testRabbitMQ.ini", "commentsMQ");

$type = $_POST['type'] ?? 'get_event_details';
$token = $_POST['token'] ?? '';
$eventId = $_POST['event_id'] ?? null;
$comment = $_POST['comment'] ?? '';

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

$request = [
    'type' => $type,
    'event_id' => $eventId,
    'username' => $username,
];

if ($type === 'add_comment') {
    $request['comment'] = $comment;
}

$response = $client->send_request($request);

if ($response['status'] === 'success') {
    echo json_encode(["status" => "success", "event" => $response['event'] ?? null, "comments" => $response['comments'] ?? null]);
} else {
    echo json_encode(["status" => "fail", "message" => $response['message']]);
}
?>

