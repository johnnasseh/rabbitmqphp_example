#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once "mysqlconnect.php";

$mydb = getDB();

function doLogin($username, $password)
{
	global $mydb;

	$username = $mydb->real_escape_string($username);
	$sql = "SELECT * FROM Users WHERE username='$username'";
	$stmt = $mydb->prepare($sql);
	$stmt->execute();
	$result = $stmt->get_result();

	if ($result->num_rows > 0) {
		$user = $result->fetch_assoc();

		if (password_verify($password, $user['password'])) {
			return array(
				"status" => "success",
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

function requestProessor($request)
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
$server = new rabbitMQServer("testRabbitMQ.ini", "auth_requests");

echo "RabbitMQ Auth Server BEGIN".PHP_EOL;
$server->process_requests('requestProcessor', "auth_responses");
echo "RabbitMQ Auth Server END".PHP_EOL;
exit();
?>
