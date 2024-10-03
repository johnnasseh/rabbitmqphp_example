#!/usr/bin/php
<?php
session_start();

require_once "mysqlconnect.php";
$mydb = getDB();

$conn = new mysqli('192.168.194.225', 'dbconnect', 'IT490CONNECT225', 'IT490');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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

        $username = $conn->real_escape_string($request['uname']);
        $password = $request['pword'];  

        $sql = "SELECT * FROM users WHERE username='$username'";
	$stmt = $mydb->prepare($sql);
	$stmt->execute;
	$result = $stmt->getresult();

        if ($result->num_rows > 0) {
           
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['loggedin'] = true;
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['created'] = $user['created'];
                $response = "Login successful! Welcome, " . $user['username'] . ".";
            } else {
                $response = "Login failed. Incorrect password.";
            }
        } else {
            $response = "Login failed. User does not exist.";
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
$conn->close();  
exit(0);
?>

