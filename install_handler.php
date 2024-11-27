#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('deploy_mysqlconnect.php');

function fetchLatestNewBundle() {
    $db = getDeployDB();

    $query = $db->prepare("SELECT bundle_name, bundle_path FROM Bundles WHERE status IN ('new', 'installed') ORDER BY bundle_id DESC LIMIT 1");
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

function updateBundleStatus($bundleName, $status) {
    $db = getDeployDB();

    $query = $db->prepare("UPDATE Bundles SET status = ? WHERE bundle_name = ?");
    $query->bind_param('ss', $status, $bundleName);
    if ($query->execute()) {
        return ["status" => "success", "message" => "Bundle status updated to '$status'"];
    } else {
        return ["status" => "error", "message" => "Failed to update bundle status: " . $query->error];
    }
}

function requestProcessor($request) {
    if ($request['type'] === 'fetch_latest_bundle') {
        return fetchLatestNewBundle();
    } elseif ($request['type'] === 'update_bundle_status') {
        return updateBundleStatus($request['bundle_name'], $request['status']);
    }


    return ["status" => "error", "message" => "Invalid request type"];
}

$server = new rabbitMQServer("testRabbitMQ.ini", "installMQ");
$server->process_requests('requestProcessor');
?>
