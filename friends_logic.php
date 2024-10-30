<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('vendor/autoload.php');
require_once('mysqlconnect.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];

$token = $_POST['token'] ?? '';

if ($token) {
    try {
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
        $username = $decoded->data->username;

        $db = getDB();

        // fetch incoming friend requests
        $incomingQuery = $db->prepare("SELECT username FROM FriendRequests WHERE requested_username = ? AND status = 'pending'");
        $incomingQuery->bind_param('s', $username);
        $incomingQuery->execute();
        $incomingResult = $incomingQuery->get_result();
        $incomingRequests = [];
        while ($row = $incomingResult->fetch_assoc()) {
            $incomingRequests[] = $row['username'];
        }
        error_log("Incoming Requests for $username: " . json_encode($incomingRequests));

        // fetch pending friend requests
        $pendingQuery = $db->prepare("SELECT requested_username FROM FriendRequests WHERE username = ? AND status = 'pending'");
        $pendingQuery->bind_param('s', $username);
        $pendingQuery->execute();
        $pendingResult = $pendingQuery->get_result();
        $pendingRequests = [];
        while ($row = $pendingResult->fetch_assoc()) {
            $pendingRequests[] = $row['requested_username'];
        }
        error_log("Pending Requests for $username: " . json_encode($pendingRequests));

        // fetch friends list
        $friendsQuery = $db->prepare("SELECT friend_username FROM Friends WHERE username = ?");
        $friendsQuery->bind_param('s', $username);
        $friendsQuery->execute();
        $friendsResult = $friendsQuery->get_result();
        $friends = [];
        while ($row = $friendsResult->fetch_assoc()) {
            $friends[] = $row['friend_username'];
        }
        error_log("Friends for $username: " . json_encode($friends));

        // outputs only JSON data
        echo json_encode([
            'status' => 'success',
            'incomingRequests' => $incomingRequests,
            'pendingRequests' => $pendingRequests,
            'friends' => $friends
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'fail', 'message' => 'Invalid or expired token']);
    }
} else {
    echo json_encode(['status' => 'fail', 'message' => 'Missing token']);
}
?>
