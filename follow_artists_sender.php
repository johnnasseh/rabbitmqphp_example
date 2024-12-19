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
    $query = $_POST['query'] ?? '';
    $artist_id = $_POST['artist_id'] ?? '';
    $artist_name = $_POST['artist_name'] ?? '';

    try {
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
        $username = $decoded->data->username;
    } catch (Exception $e) {
        echo json_encode(["status" => "fail", "message" => "Invalid or expired token"]);
        exit;
    }

    $request = ["type" => $type, "username" => $username];

    if ($type === 'search') {
        $request['query'] = $query;
    } elseif ($type === 'follow') {
        $request['artist_id'] = $artist_id;
        $request['artist_name'] = $artist_name;
    } else {
        echo json_encode(["status" => "fail", "message" => "Invalid request type"]);
        exit;
    }

    try {
        $response = $client->send_request($request);
        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode(["status" => "fail", "message" => "Server error occurred"]);
    }
}
?>
