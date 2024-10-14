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

// rabbitmq client
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");


// check if post
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $msg = ["status" => "error", "message" => "No POST request made"];
    echo json_encode($msg);
    exit(0);
}

// check if username and password fields are in the POST request
if (!isset($_POST['uname']) || !isset($_POST['pword'])) {
    error_log("LOgin request missing username or password");
    $msg = ["status" => "error", "message" => "Missing username or password"];
    echo json_encode($msg);
    exit(0);
} else {
	// log received username and password for debugging
	error_log("username: " . $_POST['uname']);
	error_log("password: " . $_POST['pword']);
}

// getting data from the login form and saniziting
$username = htmlspecialchars(strip_tags($_POST['uname']));
$password = htmlspecialchars(strip_tags($_POST['pword']));

// creating and sending request to rabbitmq
$rabbitRequest = array();
$rabbitRequest['type'] = 'login';
$rabbitRequest['username'] = $username;
$rabbitRequest['password'] = $password;

// sending login request to rabbitmq
$response = $client->send_request($rabbitRequest, "auth_responses");

// rabbitmq response handling
if ($response['status'] == 'success') {

	$payload = [
		'iss' => 'IT490', // isuer
		'iat' => time(), // issued at
		'exp' => time() + 3600, //expiration time
		'data' => [
			'username' => $response['username'], // username in payload
			'email' => $response['email'] // email in payload
		]
	];

	// encoding payload to generate jwt with secret key and  hs256 alg
	$jwt = JWT::encode($payload, $jwt_secret, 'HS256');

	// returning success message with generated jwt
    echo json_encode(["status" => "success", "message" => "Login successful! Welcome, " . $response['username'] . ".", "token" => $jwt]);
} else {
    echo json_encode(["status" => "fail", "message" => $response['message']]);
}

exit(0);
?>

