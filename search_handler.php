#!/usr/bin/php
<?php
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
$hasdata_api_key = $env['HASDATA_API'];

function performSearch($query) {
	global $hasdata_api_key;

	$encodedQuery = urlencode($query);
	$curl = curl_init();
	// curl request
	
	curl_setopt_array($curl, [
       	CURLOPT_URL => "https://api.hasdata.com/scrape/google/events?q={$encodedQuery}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "x-api-key: $hasdata_api_key"
            ],
	]);

	$response = curl_exec($curl);
	$err = curl_error($curl);
	curl_close($curl);

	if ($err) {
		error_log("curl error #:" . $err);
		return ["status" => "fail", "message" => "API Request failed"];
	}

	$apiData = json_decode($response, true);
	return ["status" => "success", "data" => $apiData];
}
function requestProcessor($request) {

    error_log("Request received in search_handler:");
    error_log(print_r($request, true));

       if ($request['type'] !== 'search' || !isset($request['query'])) {
        return ["status" => "fail", "message" => "Invalid request type or missing query"];
    }

   
    return performSearch($request['query']);
}


$server = new rabbitMQServer("testRabbitMQ.ini", "searchMQ");
$server->process_requests('requestProcessor');
?>
