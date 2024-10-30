<?php
require_once('vendor/autoload.php');
require_once('rabbitMQLib.inc');
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Load .env file and JWT secret
$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];

if (isset($_COOKIE['token'])) {
    $token = $_COOKIE['token'];
    try {
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
        $user_data = (array)$decoded->data;
    } catch (Exception $e) {
        echo "<p>Unauthorized access. Please log in.</p>";
        exit;
    }
} else {
    echo "<p>No valid token found. Please log in.</p>";
    exit;
}

if (isset($_GET['query'])) {
    $query = urlencode($_GET['query']);
    $url = "https://musicbrainz.org/ws/2/artist?query=$query&fmt=json&limit=5";

    // Fetch artist data from MusicBrainz API
    $response = file_get_contents($url);
    if ($response === FALSE) {
        echo "<p>Failed to connect to the MusicBrainz API. Please try again later.</p>";
        exit;
    }

    $data = json_decode($response, true);
    if (!empty($data['artists'])) {
        echo "<h2>Search Results</h2>";
        foreach ($data['artists'] as $artist) {
            $artist_mbid = htmlspecialchars($artist['id']);
            $artist_name = htmlspecialchars($artist['name']);
            echo "<div><h3>$artist_name</h3>";

            // Show release information
            $releaseUrl = "https://musicbrainz.org/ws/2/artist/$artist_mbid?inc=release-groups&fmt=json";
            $releaseResponse = file_get_contents($releaseUrl);
            $releaseData = json_decode($releaseResponse, true);
            if (isset($releaseData['release-groups']) && !empty($releaseData['release-groups'])) {
                $topRelease = $releaseData['release-groups'][0];
                echo "<h4>Top Release:</h4>";
                echo "<p>" . htmlspecialchars($topRelease['title']) . " (" . htmlspecialchars($topRelease['primary-type']) . ")</p>";
            } else {
                echo "<p>No releases found for this artist.</p>";
            }

            // Form to follow artist
            echo "<form action='follow_artists_action.php' method='POST'>";
            echo "<input type='hidden' name='artist_id' value='$artist_mbid'>";
            echo "<input type='hidden' name='artist_name' value='$artist_name'>";
            echo "<button type='submit'>Follow $artist_name</button>";
            echo "</form></div><hr>";
        }
    } else {
        echo "<p>No artists found for \"$query\".</p>";
    }
}
?>



