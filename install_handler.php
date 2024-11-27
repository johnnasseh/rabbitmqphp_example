#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('deploy_mysqlconnect.php');

function fetchLatestNewBundle() {
    $db = getDeployDB();

    $query = $db->prepare("SELECT bundle_name, bundle_path FROM Bundles WHERE status = 'new' ORDER BY bundle_id DESC LIMIT 1");
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
            'message' => 'No new bundles available',
        ];
    }
}

function requestProcessor($request) {
    if ($request['type'] === 'fetch_latest_bundle') {
        return fetchLatestNewBundle();
    }

    return ["status" => "error", "message" => "Invalid request type"];
}

$server = new rabbitMQServer("testRabbitMQ.ini", "installMQ");
$server->process_requests('requestProcessor');
?>
