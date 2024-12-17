#!/usr/bin/php
<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('mysqlconnect.php');
require_once('vendor/autoload.php');
require_once('log_utils.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$mydb = getDB();
$jwt_key = $env['JWT_SECRET']; 
// register function: handles user registration
function doRegister($username, $password, $email)
{
    global $mydb, $jwt_key;

    // check if the username or email already exists in the Users table
    $sql = "SELECT * FROM Users WHERE username = ? OR email = ?";
    $stmt = $mydb->prepare($sql); // preparing sql statement to avoid sql injections
    $stmt->bind_param("ss", $username, $email); // binding parameters
    $stmt->execute(); // executing query
    $result = $stmt->get_result(); //getting results

    if ($result->num_rows > 0) {
	// logging error message
	log_message("Registration failed: Username or email already exists. Username: $username, Email: $email");    
    // return failure if username or email exists
        return ["status" => "fail", "message" => "Username or email already exists."];
    }

    // insert the new user into the database
    $sql = "INSERT INTO Users (username, password, email) VALUES (?, ?, ?)";
    $stmt = $mydb->prepare($sql);
    $stmt->bind_param("sss", $username, $password, $email);

    if ($stmt->execute()) {
        // create JWT payload with user info
        $payload = array(
            'iss' => 'IT490', //issuer
            'iat' => time(), // issued at 
            'exp' => time() + 3600,  // token valid for 1 hour
            'data' => array('username' => $username, 'email' => $email) // user data
        );

        // encode the JWT using secret key and hs256 alg
        $jwt = JWT::encode($payload, $jwt_key, 'HS256');

        // return success response with the token
        return array(
            "status" => "success",
            "username" => $username,
            "email" => $email,
            "token" => $jwt
        );
    } else {
	log_message("Registration failed: Failed to register user. Username: $username, Email: $email");
        // Return failure if registration fails
        return ["status" => "fail", "message" => "Failed to register user."];
    }
}

// login function: handles user login
function doLogin($username, $password)
{
    global $mydb, $jwt_key;

    // check if the user exists in the database
    $sql = "SELECT * FROM Users WHERE username = ?";
    $stmt = $mydb->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // fetch the user record
        $user = $result->fetch_assoc();

        // verify the password using password_verify()
        if (password_verify($password, $user['password'])) {
            // create JWT payload with user info
            $payload = array(
                "iss" => 'IT490',
                "iat" => time(),
                "exp" => time() + 3600, 
                "data" => array("username" => $user['username'], "email" => $user['email'])
            );

            // encode the JWT
            $jwt = JWT::encode($payload, $jwt_key, 'HS256');

            // return success response with the token
            return array(
                "status" => "success",
                "token" => $jwt,
                "username" => $user['username'],
                "email" => $user['email']
            );
	} else {
            // Return failure if password is incorrect
            return array("status" => "fail", "message" => "Incorrect password.");
        }
    } else {
	
        // Return failure if user does not exist
        return array("status" => "fail", "message" => "User does not exist.");
    }
}

// processes requests received from rabbitmq
function requestProcessor($request)
{ 	// print out received requests for debugging
    echo "Received request".PHP_EOL;
    var_dump($request);

    // make sure  the request has a valid 'type' field
    if (!isset($request['type'])) {
	log_message("Invalid request type received.");
        return array("status" => "fail", "message" => "Invalid request type");
    }

    // switch based on the request type (register or login)
    switch ($request['type']) {
        case "register":
            return doRegister($request['username'], $request['password'], $request['email']);  // Process registration

        case "login":
            return doLogin($request['username'], $request['password']);  // Process login

        default:
            return array("status" => "fail", "message" => "Unsupported request type");  // Unsupported request type
    }
}

// initialize rabbitmq server. listening to testServer queue
$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");
// process incoming requests using requestProcessor
$server->process_requests('requestProcessor');
?>

