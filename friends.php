<?php
require_once('vendor/autoload.php');
require('nav.php');
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];
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
        echo json_encode([
            "status" => "success",
            "username" => $username
        ]);
        exit;
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
    <title>Friends</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body>
    <div class="container mt-5">
        <h1>Friends</h1>

        <h2>Send Friend Request</h2>
        <div class="form-group">
            <input type="text" id="searchInput" class="form-control" placeholder="Enter username">
            <button class="btn btn-primary mt-2" onclick="sendFriendRequest()">Send Request</button>
        </div>

        <h2>Incoming Friend Requests</h2>
        <ul class="list-group" id="incomingRequests"></ul>

        <h2>Pending Friend Requests</h2>
        <ul class="list-group" id="pendingRequests"></ul>

        <h2>Friends List</h2>
        <ul class="list-group" id="friendsList"></ul>
    </div>

    <script>
        window.onload = function() {
            const token = localStorage.getItem("token");
            if (!token) {
                alert("You must be logged in to view friend requests.");
                window.location.href = "index.html";
            } else {
                fetchFriendData();
            }
        };

        function fetchFriendData() {
            const token = localStorage.getItem("token");

            fetch('friends_sender.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `token=${encodeURIComponent(token)}&type=get_friends_data`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    displayIncomingRequests(data.incomingRequests);
                    displayPendingRequests(data.pendingRequests);
                    displayFriends(data.friends);
                } else {
                    alert(data.message);
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function sendFriendRequest() {
            const token = localStorage.getItem("token");
            const friendUsername = document.getElementById('searchInput').value;

            fetch('friends_sender.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `token=${encodeURIComponent(token)}&friend_username=${encodeURIComponent(friendUsername)}&type=send_request`
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                fetchFriendData();
            })
            .catch(error => console.error('Error:', error));
        }

        function displayIncomingRequests(incomingRequests) {
            const incomingList = document.getElementById('incomingRequests');
            incomingList.innerHTML = '';
            if (incomingRequests && incomingRequests.length > 0) {
                incomingRequests.forEach(request => {
                    incomingList.innerHTML += `<li class="list-group-item d-flex justify-content-between align-items-center">
                        ${request}
                        <button class="btn btn-success btn-sm" onclick="handleFriendRequest('${request}', 'accept')">Accept</button>
                        <button class="btn btn-danger btn-sm" onclick="handleFriendRequest('${request}', 'decline')">Decline</button>
                    </li>`;
                });
            } else {
                incomingList.innerHTML = '<li class="list-group-item">No incoming requests</li>';
            }
        }

        function displayPendingRequests(pendingRequests) {
            const pendingList = document.getElementById('pendingRequests');
            pendingList.innerHTML = '';
            if (pendingRequests && pendingRequests.length > 0) {
                pendingRequests.forEach(request => {
                    pendingList.innerHTML += `<li class="list-group-item">${request}</li>`;
                });
            } else {
                pendingList.innerHTML = '<li class="list-group-item">No pending requests</li>';
            }
        }

        function displayFriends(friends) {
            const friendsList = document.getElementById('friendsList');
            friendsList.innerHTML = '';
            if (friends && friends.length > 0) {
                friends.forEach(friend => {
                    friendsList.innerHTML += `<li class="list-group-item">${friend}</li>`;
                });
            } else {
                friendsList.innerHTML = '<li class="list-group-item">You have no friends added yet</li>';
            }
        }

        function handleFriendRequest(friendUsername, action) {
            const token = localStorage.getItem("token");

            fetch('friends_sender.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `token=${encodeURIComponent(token)}&friend_username=${encodeURIComponent(friendUsername)}&type=${action}`
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                fetchFriendData();
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>
