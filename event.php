<?php
require_once('vendor/autoload.php');
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'] ?? '';
$client = new rabbitMQClient("testRabbitMQ.ini", "commentsMQ");

$token = $_POST['token'] ?? '';
$eventId = $_GET['event_id'] ?? null;

if (!$token || !$eventId) {
    echo json_encode(["status" => "fail", "message" => "Token or event ID not provided"]);
    exit;
}

// Decode JWT to get the username
try {
    $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
    $username = $decoded->data->username;
    $userId = $decoded->data->user_id;
} catch (Exception $e) {
    echo json_encode(["status" => "fail", "message" => "Invalid or expired token"]);
    exit;
}

// Fetch event details and comments
$request = [
    'type' => 'get_event_details',
    'event_id' => $eventId,
    'username' => $username
];

$response = $client->send_request($request);

if ($response['status'] === 'success') {
    $eventDetails = $response['event'];
    $comments = $response['comments'];
} else {
    echo "<p>Error: {$response['message']}</p>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Details</title>
</head>
<body>
    <h1><?php echo htmlspecialchars($eventDetails['title']); ?></h1>
    <p><?php echo htmlspecialchars($eventDetails['description']); ?></p>
    <h2>Comments</h2>
    <div id="comments">
        <?php foreach ($comments as $comment): ?>
            <p><strong><?php echo htmlspecialchars($comment['username']); ?></strong>: <?php echo htmlspecialchars($comment['comment']); ?></p>
        <?php endforeach; ?>
    </div>
    <form id="commentForm">
        <textarea id="commentText" placeholder="Leave a comment"></textarea>
        <button type="button" onclick="addComment()">Add Comment</button>
    </form>

    <script>
        function addComment() {
            const token = '<?php echo $token; ?>';
            const comment = document.getElementById("commentText").value;

            fetch('add_comment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `token=${encodeURIComponent(token)}&event_id=${<?php echo $eventId; ?>}&comment=${encodeURIComponent(comment)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    location.reload(); // Reload page to show the new comment
                } else {
                    alert("Failed to add comment: " + data.message);
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>

