#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('mysqlconnect.php');
require_once('log_utils.php');

function requestProcessor($request) {
    if (!isset($request['type'])) {
        log_message("Error: Invalid request format - missing 'type'.");
        return ["status" => "error", "message" => "Invalid request"];
    }

    // debugging to find request type
    log_message("Processing request type: " . $request['type']);

    switch ($request['type']) {
        case 'get_friends_data':
            return getFriendsData($request['username']);
        case 'send_request':
            return sendFriendRequest($request['username'], $request['friend_username']);
        case 'accept':
            return acceptFriendRequest($request['username'], $request['friend_username']);
        case 'decline':
            return declineFriendRequest($request['username'], $request['friend_username']);
        default:
            error_log("Unknown request type: " . $request['type']);
            return ["status" => "error", "message" => "Unknown request type"];
    }
}

function getFriendsData($username) {
    $db = getDB();

    // retrieves friends list
    $friendsQuery = $db->prepare("SELECT friend_username FROM Friends WHERE username = ?");
    $friendsQuery->bind_param('s', $username);
    $friendsQuery->execute();
    $friendsResult = $friendsQuery->get_result();

    $friends = [];
    while ($row = $friendsResult->fetch_assoc()) {
        $friends[] = $row['friend_username'];
    }
    error_log("Friends for $username: " . json_encode($friends));

    // retrieves pending friend requests
    $pendingQuery = $db->prepare("SELECT requested_username FROM FriendRequests WHERE username = ? AND status = 'pending'");
    $pendingQuery->bind_param('s', $username);
    $pendingQuery->execute();
    $pendingResult = $pendingQuery->get_result();

    $pendingRequests = [];
    while ($row = $pendingResult->fetch_assoc()) {
        $pendingRequests[] = $row['requested_username'];
    }
    error_log("Pending Requests for $username: " . json_encode($pendingRequests));

    // retrieves incoming friend requests
    $incomingQuery = $db->prepare("SELECT username FROM FriendRequests WHERE requested_username = ? AND status = 'pending'");
    $incomingQuery->bind_param('s', $username);
    $incomingQuery->execute();
    $incomingResult = $incomingQuery->get_result();

    $incomingRequests = [];
    while ($row = $incomingResult->fetch_assoc()) {
        $incomingRequests[] = $row['username'];
    }
    error_log("Incoming Requests for $username: " . json_encode($incomingRequests));

    return [
        "status" => "success",
        "friends" => $friends,
        "pendingRequests" => $pendingRequests,
        "incomingRequests" => $incomingRequests
    ];
}

function sendFriendRequest($username, $friendUsername) {
	$db = getDB();
	try {
    $query = $db->prepare("INSERT INTO FriendRequests (username, requested_username, status) VALUES (?, ?, 'pending')");
           if ($query === false) {
            log_message("Error preparing friend request query: " . $db->error);
            throw new Exception("Error preparing friend request query");
        }
    $query->bind_param('ss', $username, $friendUsername);

    if ($query->execute()) {
        return ["status" => "success", "message" => "Friend request sent"];
    } else {
        log_message("Error executing friend request for username $username to $friendUsername: " . $query->error);
        throw new Exception("Failed to send friend request");
    }
} catch (Exception $e) {
        log_message("Error in sendFriendRequest: " . $e->getMessage());
        return ["status" => "fail", "message" => "Error sending friend request"];
    }
}

function acceptFriendRequest($username, $friendUsername) {
    $db = getDB();
    
    // updates the friend request to accepted
    $updateRequest = $db->prepare("UPDATE FriendRequests SET status = 'accepted' WHERE username = ? AND requested_username = ?");
    $updateRequest->bind_param('ss', $friendUsername, $username);
    
    // insert mutual friendship entries into friends table
    $insertFriend1 = $db->prepare("INSERT INTO Friends (username, friend_username) VALUES (?, ?)");
    $insertFriend2 = $db->prepare("INSERT INTO Friends (username, friend_username) VALUES (?, ?)");
    $insertFriend1->bind_param('ss', $username, $friendUsername);
    $insertFriend2->bind_param('ss', $friendUsername, $username);

    if ($updateRequest->execute() && $insertFriend1->execute() && $insertFriend2->execute()) {
        return ["status" => "success", "message" => "Friend request accepted"];
    } else {
        error_log("Error accepting friend request: " . $updateRequest->error . " | " . $insertFriend1->error . " | " . $insertFriend2->error);
        return ["status" => "fail", "message" => "Failed to accept friend request"];
    }
}

function declineFriendRequest($username, $friendUsername) {
    $db = getDB();
    
    // updates the friend request to declined
    $declineRequest = $db->prepare("UPDATE FriendRequests SET status = 'declined' WHERE username = ? AND requested_username = ?");
    $declineRequest->bind_param('ss', $friendUsername, $username);

    if ($declineRequest->execute()) {
        return ["status" => "success", "message" => "Friend request declined"];
    } else {
        error_log("Error declining friend request: " . $declineRequest->error);
        return ["status" => "fail", "message" => "Failed to decline friend request"];
    }
}

// initializes rabbit server
$server = new rabbitMQServer("testRabbitMQ.ini", "friendsMQ");
error_log("Starting RabbitMQ server on friendsMQ queue...");
$server->process_requests('requestProcessor');
error_log("RabbitMQ server processing completed.");
?>
