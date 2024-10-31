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
$jwt_secret = $env['JWT_SECRET'] ?? '';
$client = new rabbitMQClient("testRabbitMQ.ini", "commentsMQ");

error_log("post data received");
error_log(print_r($_POST, true));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
    error_log("Token decoded successfully. Username: $username");
} catch (Exception $e) {
    error_log("Invalid or expired token: " . $e->getMessage());
    echo json_encode(["status" => "fail", "message" => "Invalid or expired token"]);
    exit;
}

    if (!$eventId || !$comment) {
        error_log("Missing event ID or comment");
        echo json_encode(["status" => "fail", "message" => "Event ID and comment are required"]);
        exit;
    }

$rabbitRequest = [
    'type' => 'add_comment',
    'username' => $username,
    'event_id' => $eventId,
    'comment' => $comment
];
error_log("Sending request to RabbitMQ: " . print_r($rabbitRequest, true));
    try {
        $response = $client->send_request($rabbitRequest);
        if (isset($response['status']) && $response['status'] === 'success') {
            echo json_encode([
                "status" => "success",
                "message" => $response['message'] ?? "Comment added successfully"
            ]);
        } else {
            echo json_encode(["status" => "fail", "message" => $response['message'] ?? "Unknown error occurred"]);
        }
    } catch (Exception $e) {
        error_log("Error sending request to RabbitMQ: " . $e->getMessage());
        echo json_encode(["status" => "fail", "message" => "Server error occurred"]);
    }

    exit;
}
?>

