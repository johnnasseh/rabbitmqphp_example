<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function fetchLatestInstalledBundle() {
    $client = new rabbitMQClient("testRabbitMQ.ini", "deploymentMQ");

    $request = [
        'type' => 'fetch_latest_installed_bundle'
    ];

    $response = $client->send_request($request);

    if ($response['status'] === 'success') {
        return $response['bundle'];
    } else {
        echo "Failed to fetch latest installed bundle: " . $response['message'] . "\n";
        return null;
    }
}

function updateBundleStatus($bundleId, $status) {
    $client = new rabbitMQClient("testRabbitMQ.ini", "deploymentMQ");

    $request = [
        'type' => 'update_bundle_status',
        'bundle_id' => $bundleId,
        'status' => $status
    ];

    $response = $client->send_request($request);

    if ($response['status'] === 'success') {
        echo "Bundle status updated to '$status'.\n";
    } else {
        echo "Failed to update bundle status: " . $response['message'] . "\n";
    }
}

$latestBundle = fetchLatestInstalledBundle();
if ($latestBundle) {
    echo "Latest installed bundle:\n";
    echo "ID: " . $latestBundle['bundle_id'] . "\n";
    echo "Name: " . $latestBundle['bundle_name'] . "\n";

    $status = readline("Enter 'pass' or 'fail' to mark bundle: ");

    if (strtolower($status) === 'pass') {
        updateBundleStatus($latestBundle['bundle_id'], 'passed');
    } elseif (strtolower($status) === 'fail') {
        updateBundleStatus($latestBundle['bundle_id'], 'failed');
    } else {
        echo "Invalid input. No changes made.\n";
    }
}
?>
