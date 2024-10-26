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
$friendUsername = $_POST['friend_username'] ?? '';
$action = $_POST['action'] ?? '';

if ($token && $friendUsername && $action) {
    try {
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
        $username = $decoded->data->username;

        $db = getDB();

        if ($action === 'send_request') {
            // makes the request pending
            $query = $db->prepare("INSERT INTO FriendRequests (username, requested_username, status) VALUES (?, ?, 'pending')");
            $query->bind_param('ss', $username, $friendUsername);

            if ($query->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Friend request sent']);
            } else {
                echo json_encode(['status' => 'fail', 'message' => 'Failed to send friend request']);
            }
        } elseif ($action === 'accept') {
            // updates to accepted
            $updateRequest = $db->prepare("UPDATE FriendRequests SET status = 'accepted' WHERE username = ? AND requested_username = ?");
            $updateRequest->bind_param('ss', $friendUsername, $username);

            // inserts friends
            $insertFriend1 = $db->prepare("INSERT INTO Friends (username, friend_username) VALUES (?, ?)");
            $insertFriend2 = $db->prepare("INSERT INTO Friends (username, friend_username) VALUES (?, ?)");
            $insertFriend1->bind_param('ss', $username, $friendUsername);
            $insertFriend2->bind_param('ss', $friendUsername, $username);

            if ($updateRequest->execute() && $insertFriend1->execute() && $insertFriend2->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Friend request accepted']);
            } else {
                echo json_encode(['status' => 'fail', 'message' => 'Failed to accept friend request']);
            }
        } elseif ($action === 'decline') {
            // updates to declined
            $declineRequest = $db->prepare("UPDATE FriendRequests SET status = 'declined' WHERE username = ? AND requested_username = ?");
            $declineRequest->bind_param('ss', $friendUsername, $username);

            if ($declineRequest->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Friend request declined']);
            } else {
                echo json_encode(['status' => 'fail', 'message' => 'Failed to decline friend request']);
            }
        } else {
            echo json_encode(['status' => 'fail', 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'fail', 'message' => 'Invalid or expired token']);
    }
} else {
    echo json_encode(['status' => 'fail', 'message' => 'Missing parameters']);
}
?>
