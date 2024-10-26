#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('mysqlconnect.php');

function processFriendRequest($username, $friendUsername, $action) {
    $db = getDB();

    if ($action === 'accept') {
        // updates the friend request status to accepted
        $query = $db->prepare("UPDATE FriendRequests SET status = 'accepted' WHERE username = ? AND requested_username = ?");
        $query->bind_param('ss', $friendUsername, $username);
        if ($query->execute()) {
            // adds the new friend relationship to the Friends table
            $addFriend = $db->prepare("INSERT INTO Friends (username, friend_username) VALUES (?, ?), (?, ?)");
            $addFriend->bind_param('ssss', $username, $friendUsername, $friendUsername, $username);
            $addFriend->execute();
            return ["status" => "success", "message" => "Friend request accepted."];
        }
    } elseif ($action === 'decline') {
        // deletes the friend request from the FriendRequests table
        $query = $db->prepare("DELETE FROM FriendRequests WHERE username = ? AND requested_username = ?");
        $query->bind_param('ss', $friendUsername, $username);
        if ($query->execute()) {
            return ["status" => "success", "message" => "Friend request declined."];
        }
    }

    return ["status" => "error", "message" => "Failed to process friend request."];
}

// checks for post
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = $_POST['token'] ?? '';
    $friendUsername = $_POST['friend_username'] ?? '';
    $action = $_POST['action'] ?? '';

    try {
        $env = parse_ini_file('.env');
        $jwt_secret = $env['JWT_SECRET'];

        // decodes token for username
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
        $username = $decoded->data->username;

        // processes the friend request
        $response = processFriendRequest($username, $friendUsername, $action);
        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Invalid or expired token"]);
    }
}
?>
