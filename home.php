<?php
require_once('vendor/autoload.php');
require('nav.php');
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

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EventPulse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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
            });
        }
      }
      </script>
</head>
<body>
     <!-- Centered Title, Search Bar, and Text -->
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                <h1 class="mb-4">EventPulse</h1>
                <form class="d-flex mb-3" role="search">
                    <input class="form-control me-2" type="search" placeholder="Search" aria-label="Search">
                    <button class="btn btn-outline-success" type="submit">Search</button>
                </form>
                <p class="lead">Search for any sports, concerts, or events near you!</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
