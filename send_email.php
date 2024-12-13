<?php
require_once('vendor/autoload.php');
require_once('rabbitMQLib.inc');
require('nav.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = $_POST['token'] ?? '';
	if (!$token) {
    	echo json_encode(["status" => "fail", "message" => "Token not provided"]);
    	exit;
	}

	try {
    	$decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
    	$username = $decoded->data->username;

    	$db = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);
    	if ($db->connect_error) {
        	error_log("Database connection failed: " . $db->connect_error);
        	echo json_encode(["status" => "fail", "message" => "Database connection failed"]);
        	exit;
    	}

    	$stmt = $db->prepare("
        	SELECT e.title, e.date_start, e.time_start, e.location, e.description, u.email
        	FROM User_Likes ul
        	JOIN Events e ON ul.event_id = e.event_id
        	JOIN Users u ON ul.id = u.id
        	WHERE u.username = ?
        	ORDER BY ul.like_date ASC
        	LIMIT 1
    	");
    	$stmt->bind_param('s', $username);
    	$stmt->execute();
    	$result = $stmt->get_result();

    	if ($result->num_rows === 0) {
        	echo json_encode(["status" => "fail", "message" => "No liked events found"]);
        	exit;
    	}

    	$event = $result->fetch_assoc();

    	$emailData = [
        	"type" => "send_email",
        	"email" => $event['email'],
        	"event_title" => $event['title'],
        	"event_details" => sprintf(
            	"Upcoming Event: %s\nDate: %s\nTime: %s\nLocation: %s\nDescription: %s",
            	$event['title'], $event['date_start'], $event['time_start'], $event['location'], $event['description']
        	)
    	];

    	$client = new rabbitMQClient("testRabbitMQ.ini", "emailLikesMQ");
    	$response = $client->send_request($emailData);

    	if ($response['status'] === 'success') {
        	echo json_encode(["status" => "success", "message" => "Email sent successfully"]);
    	} else {
        	echo json_encode(["status" => "fail", "message" => "Failed to send email"]);
    	}
	} catch (Exception $e) {
    	error_log("Token verification failed: " . $e->getMessage());
    	echo json_encode(["status" => "fail", "message" => "Invalid or expired token"]);
	}
} else {
	echo json_encode(["status" => "fail", "message" => "Invalid request method"]);
}
?>



