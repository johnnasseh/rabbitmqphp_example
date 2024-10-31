
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('vendor/autoload.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'] ?? '';
$client = new rabbitMQClient("testRabbitMQ.ini", "searchMQ");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = $_POST['type'] ?? '';
    $token = $_POST['token'] ?? '';

    if (!$token) {
        echo json_encode(["status" => "fail", "message" => "Token not provided"]);
        exit;
    }

    try {
	    $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
	    $username = $decoded->data->username;
    } catch (Exception $e) {
        echo json_encode(["status" => "fail", "message" => "Invalid or expired token"]);
        exit;
    }
    $response = ["status" => "fail", "message" => "Invalid request"];

    if ($type === 'search') {
        // handle search request
        $query = $_POST['query'] ?? '';

        if (!$query) {
            echo json_encode(["status" => "fail", "message" => "Search query not provided"]);
            exit;
        }

        error_log("Received search query in search_sender: " . $query);

        $rabbitRequest = [
            'type' => 'search',
	    'query' => $query,
	    'username' => $username
        ];
        
    } elseif ($type === 'like') {
        // handle like request
	    
	$event = json_decode($_POST['event'], true);
        
        if (!$event) {
		echo json_encode(["status" => "fail", "message" => "Event data not provided"]);
            exit;
        }

        error_log("Received like request for event in search_sender: " . print_r($event, true));
        $rabbitRequest = [
            'type' => 'like',
            'username' => $username,
            'event' => $event
        ];

    } else {
        echo json_encode(["status" => "fail", "message" => "Invalid request type"]);
        exit;
    }
    try {
        $response = $client->send_request($rabbitRequest);
        if (isset($response['status']) && $response['status'] === 'success') {
            echo json_encode([
                "status" => "success",
                "message" => $response['message'] ?? "Request processed successfully",
                "data" => $response['data'] ?? null
            ]);
        } else {
            echo json_encode(["status" => "fail", "message" => $response['message'] ?? "Unknown error occurred"]);
        }
    } catch (Exception $e) {
        echo json_encode(["status" => "fail", "message" => "Server error occurred"]);
    }

    exit;
}
?>
