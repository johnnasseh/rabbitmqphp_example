<?php
// connects to the dmz's server script that fetches api data
$dmzUrl = "http://192.168.194.117/fetch_api_data.php";

// starts up curl 
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $dmzUrl,
    CURLOPT_RETURNTRANSFER => true,
]);

// executes request for dmz
$response = curl_exec($curl);
$error = curl_error($curl);

// checking for errors
if ($error) {
    echo "Error communicating with DMZ: $error";
} else {
    echo "Data received from DMZ:\n";
    echo $response;
}

curl_close($curl);
