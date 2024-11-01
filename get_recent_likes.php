<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('mysqlconnect.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];
$mydb = getDB();

function getRecentLikedEvents($username) {
    global $mydb;

    $stmt = $mydb->prepare("
        SELECT Events.title
        FROM Events
        JOIN User_Likes ON Events.event_id = User_Likes.event_id
        JOIN Users ON User_Likes.id = Users.id
        WHERE Users.user_id = userid
        ORDER BY User_Likes.date_liked DESC
        LIMIT 4
    ");
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    $titles = [];
    while ($row = $result->fetch_assoc()) {
        $titles[] = $row['title'];
    }

    return ["status" => "success", "titles" => $titles];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = $_POST['token'] ?? '';

    if (!$token) {
        echo json_encode(["status" => "fail", "message" => "Token not provided"]);
        exit;
    }

    try {
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
        $username = $decoded->data->username;

        $response = getRecentLikedEvents($username);
        echo json_encode($response);

    } catch (Exception $e) {
        echo json_encode(["status" => "fail", "message" => "Invalid or expired token"]);
    }
} else {
    echo json_encode(["status" => "fail", "message" => "Invalid request method"]);
}
?>