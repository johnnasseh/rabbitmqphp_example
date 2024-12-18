#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('deploy_mysqlconnect.php');

function markBundleStatus($bundleName, $version, $status) {
    $validStatuses = ['passed', 'failed'];
    if (!in_array($status, $validStatuses)) {
        return ["status" => "error", "message" => "Invalid status. Use 'passed' or 'failed'."];
    }

    $db = getDeployDB();

    $query = $db->prepare("UPDATE Bundles SET status = ?, updated_at = NOW() WHERE bundle_name = ? AND version = ?");
    $query->bind_param('ssi', $status, $bundleName, $version);
    $query->execute();

    if ($query->affected_rows > 0) {
        return ["status" => "success", "message" => "Bundle marked as '$status' successfully."];
    } else {
        return ["status" => "error", "message" => "No matching bundle found or update failed."];
    }
}

function requestProcessor($request) {
    if ($request['type'] === 'mark_bundle') {
        return markBundleStatus($request['bundle_name'], $request['version'], $request['status']);
    }
    return ["status" => "error", "message" => "Invalid request type"];
}

$server = new rabbitMQServer("testRabbitMQ.ini", "markingMQ");
$server->process_requests('requestProcessor');
?>
