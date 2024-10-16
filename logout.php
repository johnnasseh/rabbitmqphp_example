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

error_log("logout.php is being called");

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];

// rabbitmq client
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $msg = ["status" => "error", "message" => "No POST request made"];
    echo json_encode($msg);
    error_log("No POST request made");
    exit(0);
}

if (!isset($_POST['token'])) {
    error_log("Logout request missing token");
    $msg = ["status" => "error", "message" => "Missing token"];
    echo json_encode($msg);
    exit(0);
}

// get the token from the POST request
$token = htmlspecialchars(strip_tags($_POST['token']));

try {
    // decode jwt to get username
    $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
    $username = $decoded->data->username;

    // sending request to rabbitmq
    $rabbitRequest = array();
    $rabbitRequest['type'] = 'logout';
    $rabbitRequest['username'] = $username;

    // sending logout request to rabbitmq
    $response = $client->send_request($rabbitRequest, "auth_responses");

    if ($response['status'] == 'success') {
        // token cleared successfully, return success response
        echo json_encode(["status" => "success", "message" => "Logout successful."]);
    } else {
        // logout if rabbitmq fails
        echo json_encode(["status" => "fail", "message" => $response['message']]);
    }
} catch (Exception $e) {
    // decoding fail logging
    error_log("Token decoding failed: " . $e->getMessage());
    echo json_encode(["status" => "fail", "message" => "Invalid or expired token"]);
}

exit(0);
?>

