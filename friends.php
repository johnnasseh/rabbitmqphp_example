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

$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

// check if post
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = $_POST['token'] ?? '';

    if (!$token) {
        http_response_code(401);
        echo json_encode(["status" => "fail", "message" => "Token not provided"]);
        exit;
    }

    try {
        // decode token to get username
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
        $username = $decoded->data->username; 

        // prepare the request for rabbit
        $rabbitRequest = [
            'type' => 'get_friends_data',
            'username' => $username
        ];

        // send request to rabbit and get the response
        $response = $client->send_request($rabbitRequest, "friends_data_responses");

        if ($response['status'] == 'success') {
            echo json_encode($response);
        } else {
            echo json_encode(["status" => "fail", "message" => $response['message']]);
        }
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(["status" => "fail", "message" => "Invalid or expired token"]);
    }
}
?>
