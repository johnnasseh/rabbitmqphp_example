#!/usr/bin/php
<?php
session_start();

require_once('mysqlconnect.php');
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

if ($mydb->connect_error) {
    die("Connection failed: " . $mydb->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $msg = "No POST request made";
    echo json_encode($msg);
    exit(0);
}

if (!isset($_POST['type'])) {
    $msg = "Missing parameters";
    echo json_encode($msg);
    exit(0);
}

$request = $_POST;
$response = "Unsupported request type";

switch ($request["type"]) {
    case "login":
        if (!isset($request['uname']) || !isset($request['pword'])) {
            $response = "Missing username or password.";
            break;
        }
	$rabbitRequest = array();
	$rabbitRequest['type'] = 'login';
	$rabbitRequest['username'] = $request['uname'];
	$rabbitRequest['password'] = $request['pword'];

	// sending requests to rabbitmq through queue
	$response = $client->send_request($rabbitRequest, "auth_responses");

        if ($response['status'] == 'success') {
                $_SESSION['loggedin'] = true;
                $_SESSION['username'] = $response['username'];
                $_SESSION['email'] = $response['email'];
                $_SESSION['created'] = $response['created'];
                $response = "Login successful! Welcome, " . $response['username'] . ".";
            } else {
                $response = "Login failed. Incorrect password.";
            }
        break;

    case "logout":
        if (isset($_SESSION['loggedin'])) {
            session_unset();  
            session_destroy();  
            $response = "Logout successful. See you next time!";
        } else {
            $response = "You are not logged in.";
        }
        break;

    default:
        $response = "Unsupported request type";
        break;
}


echo json_encode($response);
exit(0);
?>

