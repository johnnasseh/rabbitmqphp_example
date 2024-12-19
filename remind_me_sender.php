<?php
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('vendor/autoload.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];
$client = new rabbitMQClient("testRabbitMQ.ini", "emailLikesMQ");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = $_POST['token'] ?? '';

	if (!$token) {
    	echo json_encode(["status" => "fail", "message" => "No Token"]);
    	exit;
	}

	try {
    	$decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
    	$username = $decoded->data->username;

    	$request = [
        	"type" => "send_reminder",
        	"username" => $username,
    	];
    	$response = $client->send_request($request);
    	echo json_encode($response);
	} catch (Exception $e) {
    	error_log("Token verification failed: " . $e->getMessage());
    	echo json_encode(["status" => "fail", "message" => "Invalid or expired token"]);
	}
}
?>
