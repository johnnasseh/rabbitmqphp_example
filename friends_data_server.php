#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('mysqlconnect.php');

function requestProcessor($request) {
    if (!isset($request['type'])) {
        return ["status" => "error", "message" => "Invalid request"];
    }

    // process the 'get_freinds_data' request
    if ($request['type'] === 'get_friends_data') {
        return getFriendsData($request['username']);
    }

    if ($request['type'] === 'search_users') {
        return searchUsers($request['search_username']);
    }

    return ["status" => "error", "message" => "Unknown request type"];
}

function getFriendsData($username) {
    $db = getDB();

    // query for friends list
    $friendsQuery = $db->prepare("SELECT friend_username FROM Friends WHERE username = ?");
    $friendsQuery->bind_param('s', $username);
    $friendsQuery->execute();
    $friendsResult = $friendsQuery->get_result();

    $friends = [];
    while ($row = $friendsResult->fetch_assoc()) {
        $friends[] = $row['friend_username'];
    }

    // query for pending friend requests
    $pendingQuery = $db->prepare("SELECT requested_username FROM FriendRequests WHERE username = ? AND status = 'pending'");
    $pendingQuery->bind_param('s', $username);
    $pendingQuery->execute();
    $pendingResult = $pendingQuery->get_result();

    $pendingRequests = [];
    while ($row = $pendingResult->fetch_assoc()) {
        $pendingRequests[] = $row['requested_username'];
    }

    // query for incoming friend requests
    $incomingQuery = $db->prepare("SELECT username FROM FriendRequests WHERE requested_username = ? AND status = 'pending'");
    $incomingQuery->bind_param('s', $username);
    $incomingQuery->execute();
    $incomingResult = $incomingQuery->get_result();

    $incomingRequests = [];
    while ($row = $incomingResult->fetch_assoc()) {
        $incomingRequests[] = $row['username'];
    }

    return [
        "status" => "success",
        "friends" => $friends,
        "pendingRequests" => $pendingRequests,
        "incomingRequests" => $incomingRequests
    ];
}

function searchUsers($searchUsername) {
    $db = getDB();

    // query for searching users
    $query = $db->prepare("SELECT username FROM Users WHERE username LIKE CONCAT('%', ?, '%') LIMIT 10");
    $query->bind_param('s', $searchUsername);
    $query->execute();
    $result = $query->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    return [
        "status" => "success",
        "users" => $users
    ];
}

$server = new rabbitMQServer("testRabbitMQ.ini","friendsMQ");
$server->process_requests('requestProcessor');
