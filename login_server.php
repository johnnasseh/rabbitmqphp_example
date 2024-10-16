#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once("mysqlconnect.php");
require 'vendor/autoload.php';

use Firebase\JWT\JWT;

$env = parse_ini_file('.env');


$mydb = getDB();
$jwt_key = $env['JWT_SECRET'];

var_dump($jwt_key);
var_dump($jwt);
function doLogin($username, $password)
{
	global $mydb, $jwt_key;

	$sql = "SELECT * FROM Users WHERE username = ?";
	$stmt = $mydb->prepare($sql);
	$stmt->bind_param("s", $username);
	$stmt->execute();
	$result = $stmt->get_result();

	if ($result->num_rows > 0) {
		$user = $result->fetch_assoc();

		if (password_verify($password, $user['password'])) {
			$payload = array(
				"iss" => 'IT490',
				"iat" => time(),
				"exp" => time() + 3600,
				"data" => array("username" => $user['username'],
					"email" => $user['email'])
			);

				$jwt = JWT::encode($payload, $jwt_key, 'HS256');

			return array(
				"status" => "success",
				"token" => $jwt, // return token
				"username" => $user['username'],
				"email" => $user['email']
			);
		} else {
			return array("status" => "fail", "message" => "Incorrect password.");
		}
	} else {
		return array("status" => "fail", "message" => "User does not exist.");
	}
}

function requestProcessor($request)
{
	echo "Received request".PHP_EOL;
	var_dump($request);

	if (!isset($request['type'])) {
		        return array("status" => "fail", "message" => "Invalid request type");
    }

    switch ($request['type']) {
        case "login":
            return doLogin($request['username'], $request['password']);
        default:
            return array("status" => "fail", "message" => "Unsupported request type");
    }
}

// starting rabbitmq server. listening to auth_requests and responding to auth_responses
$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");

echo "RabbitMQ Auth Server BEGIN".PHP_EOL;
$server->process_requests('requestProcessor', "testServer");
echo "RabbitMQ Auth Server END".PHP_EOL;
exit();
?>
