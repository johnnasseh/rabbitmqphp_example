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
	echo "<pre>" . print_r($_POST, true) . "</pre>";

    $token = $_POST['token'] ?? '';
    if (!$token) {
	http_response_code(401);
        echo json_encode(["status" => "fail", "message" => "Token not provided"]);
        exit;
    }

    try {
        // decode the token
        error_log("Token received: " . $token);
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
        $username = $decoded->data->username;
        $email = $decoded->data->email;
        error_log("Token decoded successfully. User: " . $username);

       
                echo json_encode([
            "status" => "success",
            "username" => $username,
            "email" => $email
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Token decoding failed: " . $e->getMessage());
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
	  console.log("full response data:", JSON.stringify(data, null, 2));
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
      // retreiving the token from localStorage
      window.onload = function(){
        const token = localStorage.getItem("token");
        console.log("token: ", token);
        // if no token is found, redirect to the login page
        if (!token) {
		console.error("Token not found in search.php. Redirecting to login.");
		window.location.href = "index.html";
	} else {
	fetch('search.php' , {
	method: 'POST',
	headers: {'Content-Type': 'application/x-www-form-urlencoded',	},
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
function performSearch(query) {
    fetch("search_sender.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `query=${encodeURIComponent(query)}`
    })
    .then(response => response.json())
    .then(data => {
        console.log("Search results:", data);
        if (data.status === "success" && data.data && data.data.eventsResults) {
            displayResults(data.data.eventsResults); 
	} else {
	    console.error("Error or unexpected data structure in response:", data);
            document.getElementById("results").innerHTML = "<p>Error: " + data.message + "</p>";
        }
    })
    .catch(error => console.error("Error during search:", error));
}
window.addEventListener("DOMContentLoaded", function() {
    const searchForm = document.getElementById("searchForm");
    if (!searchForm) {
        console.error("searchForm element is missing.");
        return;
    }

    searchForm.addEventListener("submit", function(e) {
        e.preventDefault();
        const query = document.getElementById("query")?.value;
        if (!query) {
            console.error("No search query provided.");
            return;
        }
        
        console.log("Submitting search query:", query);
        performSearch(query);  // Use performSearch function
    });
});
        function displayResults(events) {
            console.log("Events received in displayResults:", events);
            const resultsContainer = document.getElementById("results");
            resultsContainer.innerHTML = "";

            if (!events || !Array.isArray(events) || events.length === 0) {
                resultsContainer.innerHTML = "<p>No events found.</p>";
                return;
            }
            events.forEach((event, index) => {
                console.log(`Processing event #${index + 1}:`, event);
                const eventCard = `
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <img src="${event.thumbnail}" class="card-img-top" alt="Event image">
                            <div class="card-body">
                                <h5 class="card-title">${event.title}</h5>
                                <p class="card-text"><strong>Date:</strong> ${event.date.when}</p>
                                <p class="card-text"><strong>Location:</strong> ${event.address.join(', ')}</p>
                                <p class="card-text">${event.description}</p>
                                <a href="${event.link}" target="_blank" class="btn btn-primary">Get Tickets</a>
                            </div>
                        </div>
                    </div>`;
                resultsContainer.insertAdjacentHTML("beforeend", eventCard);      
            });
        }
</script>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                <h1 class="mb-4">EventPulse</h1>
                <form id="searchForm" class="d-flex mb-3" role="search">
                    <input class="form-control me-2" type="search" id="query"  placeholder="Search" aria-label="Search">
                    <button class="btn btn-outline-success" type="submit">Search</button>
                </form>
                <p class="lead">Search for any sports, concerts, or events near you!</p>
            </div>
        </div>

	<!-- results -->

        <div class="row justify-content-center mt-4">
            <div class="col-md-10">
                <div id="results" class="row gy-4">
                    <!-- results here -->
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
            
