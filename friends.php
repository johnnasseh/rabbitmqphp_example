<?php:
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('vendor/autoload.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];

$client = new rabbitMQClient("testRabbitMQ.ini", "friendsMQ");

$token = $_POST['token'] ?? '';

if ($token) {
    try {
        // decode token to get username
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
        $username = $decoded->data->username;

        // prepare the request for rabbit
        $rabbitRequest = [
            'type' => 'get_friends_data',
            'username' => $username,
        ];

        // send request to rabbit and get the response
        $response = $client->send_request($rabbitRequest, "friends_data_responses");

        if ($response['status'] === 'success') {
            $friends = $response['friends'];
            $pendingRequests = $response['pendingRequests'];
            $incomingRequests = $response['incomingRequests'];
        } else {
            echo json_encode(["status" => "fail", "message" => $response['message']]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(["status" => "fail", "message" => "Invalid or expired token"]);
        exit;
    }
} else {
    echo json_encode(["status" => "fail", "message" => "Token not provided"]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Friends</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <script>
        function handleFriendRequest(friendUsername, action) {
            const token = localStorage.getItem("token");
            if (!token) {
                alert("You must be logged in to perform this action.");
                return;
            }

            fetch('handle_friend_request.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `token=${encodeURIComponent(token)}&friend_username=${encodeURIComponent(friendUsername)}&action=${action}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    alert(data.message);
                    location.reload();
                } else {
                    alert("Failed to handle request: " + data.message);
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</head>
<body>
    <?php include('nav.php'); ?>
    <div class="container mt-5">
        <h1>Friends</h1>

        <h2>Incoming Friend Requests</h2>
        <ul class="list-group">
            <?php if (!empty($incomingRequests)) : ?>
                <?php foreach ($incomingRequests as $request) : ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?php echo htmlspecialchars($request); ?>
                        <div>
                            <button class="btn btn-success btn-sm" onclick="handleFriendRequest('<?php echo $request; ?>', 'accept')">Accept</button>
                            <button class="btn btn-danger btn-sm" onclick="handleFriendRequest('<?php echo $request; ?>', 'decline')">Decline</button>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php else : ?>
                <li class="list-group-item">No incoming requests</li>
            <?php endif; ?>
        </ul>

        <h2>Pending Friend Requests</h2>
        <ul class="list-group">
            <?php if (!empty($pendingRequests)) : ?>
                <?php foreach ($pendingRequests as $request) : ?>
                    <li class="list-group-item"><?php echo htmlspecialchars($request); ?></li>
                <?php endforeach; ?>
            <?php else : ?>
                <li class="list-group-item">No pending requests</li>
            <?php endif; ?>
        </ul>

        <h2>Friends List</h2>
        <ul class="list-group">
            <?php if (!empty($friends)) : ?>
                <?php foreach ($friends as $friend) : ?>
                    <li class="list-group-item"><?php echo htmlspecialchars($friend); ?></li>
                <?php endforeach; ?>
            <?php else : ?>
                <li class="list-group-item">You have no friends added yet</li>
            <?php endif; ?>
        </ul>
    </div>
</body>
</html>
