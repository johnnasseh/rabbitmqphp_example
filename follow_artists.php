<?php
require_once('vendor/autoload.php');
require('nav.php');
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'] ?? '';
$username = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';

    if (!$token) {
        echo json_encode(["status" => "fail", "message" => "Authorization token missing. Please log in."]);
        exit;
    }

    try {
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
        $username = $decoded->data->username;
    } catch (Exception $e) {
        echo json_encode(["status" => "fail", "message" => "Invalid or expired token. Please log in."]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Artists</title>
    <script>
        function searchArtists() {
            const query = document.getElementById("artistQuery").value;
            const token = localStorage.getItem("token");

            if (!query) {
                alert("Please enter an artist name to search.");
                return;
            }

            fetch('follow_artists_sender.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `type=search&token=${encodeURIComponent(token)}&query=${encodeURIComponent(query)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    displaySearchResults(data.artists);
                } else {
                    alert("Error: " + data.message);
                }
            })
            .catch(error => console.error("Error during search:", error));
        }

        function displaySearchResults(artists) {
            const resultsContainer = document.getElementById("searchResults");
            resultsContainer.innerHTML = "";

            artists.forEach(artist => {
                const artistElement = document.createElement("div");
                artistElement.innerHTML = `
                    <p><strong>Artist:</strong> ${artist.name} | <strong>Country:</strong> ${artist.country || 'N/A'}</p>
                    <button onclick="followArtist('${artist.id}', '${artist.name}')">Follow</button>
                `;
                resultsContainer.appendChild(artistElement);
            });
        }

        function followArtist(artistId, artistName) {
            const token = localStorage.getItem("token");

            fetch('follow_artists_sender.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `type=follow&token=${encodeURIComponent(token)}&artist_id=${encodeURIComponent(artistId)}&artist_name=${encodeURIComponent(artistName)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    alert("Artist followed successfully!");
                } else {
                    alert("Error following artist: " + data.message);
                }
            })
            .catch(error => console.error("Error during follow request:", error));
        }
    </script>
</head>
<body>
    <h1>Search for Artists</h1>
    <input type="text" id="artistQuery" placeholder="Enter artist name">
    <button onclick="searchArtists()">Search</button>
    <div id="searchResults">Search results will appear here.</div>
</body>
</html>



