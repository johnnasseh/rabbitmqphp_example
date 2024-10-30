<?php
// Loads and read  ENV file
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, null, true);
$dotenv->load();

// Example API enpoint and param
$apiUrl = "https://api.hasdata.com/scrape/google/events?q=Events%20in%20New%20York&location=Austin,Texas,United%20States";
$apiKey = $_ENV['HASDATA_API']; // Access API key directly from env

// Verifies API key
if (!$apiKey) {
	die("API key is not set. Please check your .env file.");
}

// starts  cURL
$curl = curl_init();
curl_setopt_array($curl, [
	CURLOPT_URL => $apiUrl,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HTTPHEADER => [
    	"x-api-key: $apiKey"
	],
]);

// Executes request
$response = curl_exec($curl);
$error = curl_error($curl);

// error checker
if ($error) {
	echo "cURL Error: $error";
} else {
	echo "Response received from API:\n";
	echo $response;

	$fileSavePath = __DIR__ . "/fetched_data.json";
	echo "\nSaving response to fetched_data.json at: $fileSavePath\n";

	if (file_put_contents($fileSavePath, $response) === false) {
    	echo "Failed to save the response to fetched_data.json";
	} else {
    	echo "Successfully saved to fetched_data.json.";
	}
}
curl_close($curl);
