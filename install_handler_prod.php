#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('deploy_mysqlconnect.php');

function fetchLatestPassedBundle() {
    $db = getDeployDB();

    $query = $db->prepare("SELECT bundle_name, bundle_path FROM Bundles WHERE status = 'passed' ORDER BY bundle_id DESC LIMIT 1");
    $query->execute();
    $result = $query->get_result();

    if ($row = $result->fetch_assoc()) {
        return [
            'status' => 'success',
            'bundle' => $row,
        ];
    } else {
        return [
            'status' => 'error',
            'message' => 'No passed bundles available',
        ];
    }
}

function fetchPreviousPassedBundle() {
    $db = getDeployDB();

    $query = $db->prepare("SELECT bundle_name, bundle_path FROM Bundles WHERE status = 'passed' ORDER BY bundle_id DESC LIMIT 1 OFFSET 1");
    $query->execute();
    $result = $query->get_result();

    if ($row = $result->fetch_assoc()) {
        return [
            'status' => 'success',
            'bundle' => $row,
        ];
    } else {
        return [
            'status' => 'error',
            'message' => 'No previous passed bundles available for rollback',
        ];
    }
}

function requestProcessor($request) {
    if ($request['type'] === 'fetch_latest_passed_bundle') {
        return fetchLatestPassedBundle();
    } elseif ($request['type'] === 'fetch_previous_passed_bundle') {
        return fetchPreviousPassedBundle();
    }

    return ["status" => "error", "message" => "Invalid request type"];
}

$server = new rabbitMQServer("testRabbitMQ.ini", "installprodMQ");
$server->process_requests('requestProcessor');
?>
