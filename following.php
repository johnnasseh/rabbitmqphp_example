<?php
require_once('vendor/autoload.php');
//require('nav.php');
require_once('mysqlconnect.php');
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'] ?? '';


$followedArtists = [];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = $_POST['token'] ?? '';
	if (!$token) {
    	echo json_encode(["status" => "fail", "message" => "Token not provided"]);
    	exit;
	}

	try {
    	$decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
    	$username = $decoded->data->username;
	} catch (Exception $e) {
    	echo json_encode(["status" => "fail", "message" => "Invalid or expired token"]);
    	exit;
	}

	
	$db = getDB();
	$stmt = $db->prepare("
    	SELECT entity_id, name, entity_type, follow_date
    	FROM user_follows
    	WHERE user_id = (SELECT id FROM Users WHERE username = ?)
    	AND entity_type = 'artist'
	");
	$stmt->bind_param("s", $username);
	$stmt->execute();
	$result = $stmt->get_result();

	while ($row = $result->fetch_assoc()) {
    	$followedArtists[] = $row;
	}

	echo json_encode(["status" => "success", "artists" => $followedArtists]);
	exit;
}
?>

<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Followed Artists</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
	<div class="container mt-5">
    	<h1>Followed Artists</h1>
    	<ul id="artistList" class="list-group"></ul>
	</div>

	<script>
    	const token = localStorage.getItem("token");

    	function fetchFollowedArtists() {
        	fetch("following.php", {
            	method: "POST",
            	headers: { "Content-Type": "application/x-www-form-urlencoded" },
            	body: `token=${encodeURIComponent(token)}`
        	})
        	.then(response => response.json())
        	.then(data => {
            	if (data.status === "success") {
                	displayArtists(data.artists);
            	} else {
                	alert(`Error: ${data.message}`);
            	}
        	})
        	.catch(error => console.error("Error fetching followed artists:", error));
    	}

    	function displayArtists(artists) {
        	const artistList = document.getElementById("artistList");
        	artistList.innerHTML = "";

        	if (!artists || artists.length === 0) {
            	artistList.innerHTML = "<li class='list-group-item'>No followed artists found.</li>";
            	return;
        	}

        	artists.forEach(artist => {
            	const listItem = `
                	<li class="list-group-item">
                    	<strong>Name:</strong> ${artist.name} <br>
                    	<strong>ID:</strong> ${artist.entity_id} <br>
                    	<strong>Type:</strong> ${artist.entity_type} <br>
                    	<strong>Followed On:</strong> ${new Date(artist.follow_date).toLocaleString()}
                	</li>`;
            	artistList.insertAdjacentHTML("beforeend", listItem);
        	});
    	}

    	window.onload = fetchFollowedArtists;
	</script>
</body>
</html>
