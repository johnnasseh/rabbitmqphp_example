<?php
require_once('vendor/autoload.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$env = parse_ini_file('.env');

$jwt_secret = $env['JWT_SECRET'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = $_POST['token'] ?? '';

    if (!$token) {
        http_response_code(401);
        echo json_encode(["status" => "fail", "message" => "Token not provided"]);
        exit;
    }

    try {
	    // logging  token before decoded
	    error_log("Token received: " .$token);
	    // decoding the token using the secret key w/ hs256 alg
	    $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
	    // extracting user info from the token
	    $username = $decoded->data->username;
	    $email = $decoded->data->email;
		// logging successful token decoding
	    error_log("token decode successfull. user: " . $username);
	// responding with success and the decoded user info
        echo json_encode([
            "status" => "success",
            "username" => $username,
            "email" => $email
        ]);
        exit;
    } catch (Exception $e) {
	    // logging decoding fialure and responding 401
	error_log("token decoding failed: " . $e->getMessage());
        http_response_code(401);
        echo json_encode(["status" => "fail", "message" => "Invalid or expired token"]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>
<script>
function logout() {

	const token = localStorage.getItem("token");

	if (token) {
		fetch('logout.php', {
		method: 'POST',
			headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
		},
			body: 'token=' + encodeURIComponent(token),
		})
		.then(response => response.json())
		.then(data => {
		if (data.status === "success") {
			localStorage.removeItem("token");
			window.location.href = "index.html";
		} else {
			console.error('Logout fialed:', data.message);
		}
	})
		.catch(error => {
		console.error('Error:', error);
		});
	}else {
		window.location.href = "index.html";
	}
}
//	// remove the token from localStorage and redirect to login
//		localStorage.removeItem("token");
//		window.location.href = "index.html";
//	}
// retreiving the token from localStorage
window.onload = function(){
	const token = localStorage.getItem("token");
	console.log("token: ", token);
	// if no token is found, redirect to the login page
	if (!token) {
		window.location.href = "index.html";
	} else {
		// send the token to the server for validation
		fetch('home.php', {
		method: 'POST',
		headers: { 
			'Content-Type': 'application/x-www-form-urlencoded',
	},
		body: 'token=' + encodeURIComponent(token),
	})
		.then(response => response.json()) // parse the response as json
		.then(data => {
		// if the server responds with a failure, show an error message
		if (data.status === "fail") {
			document.getElementById("content").innerHTML = "<h1>Error: " + data.message + "</h1>";
		} else {
			// if successful, display the users name on the page
			document.getElementById("content").innerHTML = "<h1>Welcome " + data.username + "!</h1>";
		}
	})
		.catch(error => {
		console.error('Error:', error);
		document.body.innerHTML = "<h1>Error: unable to verify token<h1>";
			});
	}
}
</script>
</head>
<body>
    <h1>Welcome to the Home Page</h1>
<div id="content">
    <p>You have successfully logged in!</p>
<p>Welcome, <php echo htmlspecialchars($username); ?></p>	
</div>
    <a href="javascript:void(0)" onclick="logout()">Logout</a>
</body>
</html>

