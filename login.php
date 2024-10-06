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
    echo json_encode(['status' => 'error', 'msg' => $msg]);
    exit(0);
}

if (!isset($_POST['type'])) {
    $msg = "Missing parameters";
    echo json_encode(['status' => 'error', 'msg' => $msg]);
    exit(0);
}

$request = $_POST;
$response = ['status' => 'error', 'msg' => 'Unsupported request type'];

switch ($request["type"]) {
    case "login":
        if (!isset($request['uname']) || !isset($request['pword'])) {
            $response['msg'] = "Missing username or password.";
            break;
        }
        
        $rabbitRequest = array();
        $rabbitRequest['type'] = 'login';
        $rabbitRequest['username'] = $request['uname'];
        $rabbitRequest['password'] = $request['pword'];

        // sending requests to rabbitmq through queue
        $rabbitResponse = $client->send_request($rabbitRequest, "auth_responses");

        if ($rabbitResponse['status'] == 'success') {
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $rabbitResponse['username'];
            $_SESSION['email'] = $rabbitResponse['email'];
            $_SESSION['created'] = $rabbitResponse['created'];
            $response = [
                'status' => 'success',
                'msg' => "Login successful! Welcome, " . $rabbitResponse['username'] . ".",
                'redirect' => 'home.php'
            ];
        } else {
            $response['msg'] = "Login failed. Incorrect password.";
        }
        break;

    case "logout":
        if (isset($_SESSION['loggedin'])) {
            session_unset();  
            session_destroy();  
            $response = ['status' => 'success', 'msg' => "Logout successful. See you next time!"];
        } else {
            $response['msg'] = "You are not logged in.";
        }
        break;

    default:
        $response['msg'] = "Unsupported request type";
        break;
}

echo json_encode($response);
exit(0);
?>


