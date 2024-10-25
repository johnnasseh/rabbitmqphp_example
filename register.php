<?php
require_once('mysqlconnect.php');
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('vendor/autoload.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

// output buffering
ob_start();

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];

$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

if ($mydb->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $mydb->connect_error]));
}

// making sure the request is a POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $msg = ["status" => "error", "message" => "No POST request made"];
    echo json_encode($msg);
    exit(0);
}

// check that all paramters are provided in the POST DATA
if (!isset($_POST['uname']) || !isset($_POST['pword']) || !isset($_POST['email'])) {
    $msg = ["status" => "error", "message" => "Missing parameters"];
    echo json_encode($msg);
    exit(0);
}

// sanitizing the form inputs and getting data
$username = htmlspecialchars(strip_tags($_POST['uname']));
$password = htmlspecialchars(strip_tags($_POST['pword']));
$email = htmlspecialchars(strip_tags($_POST['email']));

// creating an array with the reg details to send to rabbitmq
$rabbitRequest = array();
$rabbitRequest['type'] = 'register'; // reg type
$rabbitRequest['username'] = $username; //sanitized username
$rabbitRequest['password'] = password_hash($password, PASSWORD_BCRYPT); //hashing password before sending
$rabbitRequest['email'] = $email; //sanitized email

try {
// reg request to rabbitmq and waiting for response
$response = $client->send_request($rabbitRequest, "auth_responses");

// rabbitmq response handling
if ($response['status'] == 'success') {
	// if registration was successful, create a JWT
    $payload = [
        'iss' => 'IT490', //issuer
        'iat' => time(), //issued at
        'exp' => time() + 3600, //expiration time
        'data' => [ // user data
            'username' => $response['username'],
            'email' => $response['email']
        ]
    ];
            // generate JWT token and encoding using the secret key and hs256 alg
        $jwt = JWT::encode($payload, $jwt_secret, 'HS256');

        // send success response with the JWT token
        echo json_encode(["status" => "success", "message" => "Registration successful!", "token" => $jwt]);
    } else {
        // if rabbitmq returns a failure
        echo json_encode(["status" => "fail", "message" => $response['message']]);
    }
} catch (Exception $e) {
    // handle any errors that occur during rabbitmq communication
    error_log("Error sending RabbitMQ request: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Server error. Please try again later."]);
}
ob_end_flush();
exit(0);
?>
