<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function markLatestBundleAsFailed() {
    $client = new rabbitMQClient("testRabbitMQ.ini", "markingMQ");

    $request = [
        'type' => 'mark_latest_as_failed'
    ];

    $response = $client->send_request($request);

    if ($response['status'] === 'success') {
        echo "Bundle successfully marked as 'failed': " . $response['bundle_name'] . "\n";
    } else {
        echo "Failed to mark bundle as 'failed': " . $response['message'] . "\n";
    }
}

markLatestBundleAsFailed();
?>
