<?php
require_once('vendor/autoload.php');
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'] ?? '';
$client = new rabbitMQClient("testRabbitMQ.ini", "recommendationsMQ");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	$input = json_decode(file_get_contents('php://input'), true);
	$token = $input['token'] ?? '';

	if (!$token) {
    	echo json_encode(["status" => "fail", "message" => "Token not provided"]);
    	exit;
	}

	try {
    	$decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
    	$username = $decoded->data->username;

    	$request = [
        	'type' => 'get_recommendations',
        	'username' => $username,
    	];

    	$response = $client->send_request($request);
    	echo json_encode($response);
	} catch (Exception $e) {
    	echo json_encode(["status" => "fail", "message" => "Invalid or expired token"]);
	}
}
?>

