<?php
require_once('vendor/autoload.php');
require('nav.php');
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'] ?? '';
$username = "";


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	$token = $_POST['token'] ?? '';
	if (!$token) {
    	http_response_code(401);
    	echo json_encode(["status" => "fail", "message" => "Token not provided"]);
    	exit;
	}

	try {
    	$decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
    	$username = $decoded->data->username;
	} catch (Exception $e) {
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
	<title>Follow Artists</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
	<div class="container mt-5">
    	<h1>Follow Artists</h1>
    	<form id="searchForm" class="d-flex mb-3" role="search">
        	<input class="form-control me-2" type="search" id="query" placeholder="Search Artist" aria-label="Search">
        	<button class="btn btn-outline-success" type="submit">Search</button>
    	</form>

    	<div id="results" class="row gy-4"></div>
	</div>

	<script>
    	const token = localStorage.getItem("token");

    	function performSearch(query) {
        	fetch("follow_artists_sender.php", {
            	method: "POST",
            	headers: { "Content-Type": "application/x-www-form-urlencoded" },
            	body: `type=search&query=${encodeURIComponent(query)}&token=${encodeURIComponent(token)}`
        	})
        	.then(response => response.json())
        	.then(data => {
            	if (data.status === "success") {
                	displayResults(data.artists);
            	} else {
                	document.getElementById("results").innerHTML = `<p>Error: ${data.message}</p>`;
            	}
        	})
        	.catch(error => console.error("Error during search:", error));
    	}

    	function displayResults(artists) {
        	const resultsContainer = document.getElementById("results");
        	resultsContainer.innerHTML = "";

        	if (!artists || artists.length === 0) {
            	resultsContainer.innerHTML = "<p>No artists found.</p>";
            	return;
        	}

        	artists.forEach(artist => {
            	const artistCard = `
                	<div class="col-md-4">
                    	<div class="card shadow-sm">
                        	<div class="card-body">
                            	<h5 class="card-title">${artist.name}</h5>
                            	<p class="card-text">${artist.disambiguation || ''}</p>
                            	<button class="btn btn-primary" onclick="followArtist('${artist.id}', '${artist.name}')">Follow</button>
                        	</div>
                    	</div>
                	</div>`;
            	resultsContainer.insertAdjacentHTML("beforeend", artistCard);
        	});
    	}

    	function followArtist(artistId, artistName) {
        	fetch("follow_artists_sender.php", {
            	method: "POST",
            	headers: { "Content-Type": "application/x-www-form-urlencoded" },
            	body: `type=follow&artist_id=${encodeURIComponent(artistId)}&artist_name=${encodeURIComponent(artistName)}&token=${encodeURIComponent(token)}`
        	})
        	.then(response => response.json())
        	.then(data => {
            	if (data.status === "success") {
                	alert("Artist followed successfully!");
            	} else {
                	alert(`Error: ${data.message}`);
            	}
        	})
        	.catch(error => console.error("Error during follow request:", error));
    	}

    	document.getElementById("searchForm").addEventListener("submit", function(e) {
        	e.preventDefault();
        	const query = document.getElementById("query").value;
        	performSearch(query);
    	});
	</script>
</body>
</html>



