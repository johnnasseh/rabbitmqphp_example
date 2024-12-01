<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function markLatestBundleAsPassed() {
    $client = new rabbitMQClient("testRabbitMQ.ini", "markingMQ");

    $request = [
        'type' => 'mark_latest_as_passed'
    ];

    $response = $client->send_request($request);

    if ($response['status'] === 'success') {
        echo "Bundle successfully marked as 'passed': " . $response['bundle_name'] . "\n";
    } else {
        echo "Failed to mark bundle as 'passed': " . $response['message'] . "\n";
    }
}

markLatestBundleAsPassed();
?>
