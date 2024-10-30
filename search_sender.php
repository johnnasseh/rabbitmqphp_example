
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('vendor/autoload.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

$env = parse_ini_file('.env');

$client = new rabbitMQClient("testRabbitMQ.ini", "searchMQ");




if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $query = $_POST['query'] ?? '';
    
    if (!$query) {
        echo json_encode(["status" => "fail", "message" => "Search query not provided"]);
        exit;
    }

    error_log("Received search query in search_sender: " . $query);
    
    $rabbitRequest = [
        'type' => 'search',
       'query' => $query
    ];
    try {
     
        $response = $client->send_request($rabbitRequest);

               if (isset($response['status']) && $response['status'] === 'success') {
            echo json_encode(["status" => "success", "data" => $response['data']]);
        } else {
                    error_log("Unexpected response format from search_handler: " . print_r($response, true));
            echo json_encode(["status" => "fail", "message" => $response['message'] ?? "Unknown error occurred"]);
        }
    } catch (Exception $e) {
        // Catch any exceptions and return as JSON
        error_log("Error in search_sender: " . $e->getMessage());
        echo json_encode(["status" => "fail", "message" => "Server error occurred"]);
    }

    exit;
}
?>
