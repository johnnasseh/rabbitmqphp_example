#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('deploy_mysqlconnect.php');

function markLatestNewBundleAsPassed() {
    $db = getDeployDB();

    // fetches latest new bundle
    $query = $db->prepare("SELECT bundle_name FROM Bundles WHERE status = 'new' ORDER BY bundle_id DESC LIMIT 1");
    $query->execute();
    $result = $query->get_result();

    if ($row = $result->fetch_assoc()) {
        $bundleName = $row['bundle_name'];

        // updates status to passed 
        $update = $db->prepare("UPDATE Bundles SET status = 'passed' WHERE bundle_name = ?");
        $update->bind_param('s', $bundleName);

        if ($update->execute()) {
            return [
                'status' => 'success',
                'bundle_name' => $bundleName,
                'message' => "Bundle marked as 'passed'"
            ];
        } else {
            return [
                'status' => 'error',
                'message' => "Failed to update bundle status: " . $update->error
            ];
        }
    } else {
        return [
            'status' => 'error',
            'message' => 'No new bundles available to mark as passed'
        ];
    }
}

function markLatestNewBundleAsFailed() {
    $db = getDeployDB();

    // fetches latest new bundle
    $query = $db->prepare("SELECT bundle_name FROM Bundles WHERE status = 'new' ORDER BY bundle_id DESC LIMIT 1");
    $query->execute();
    $result = $query->get_result();

    if ($row = $result->fetch_assoc()) {
        $bundleName = $row['bundle_name'];

        // updates status to failed
        $update = $db->prepare("UPDATE Bundles SET status = 'failed' WHERE bundle_name = ?");
        $update->bind_param('s', $bundleName);

        if ($update->execute()) {
            return [
                'status' => 'success',
                'bundle_name' => $bundleName,
                'message' => "Bundle marked as 'failed'"
            ];
        } else {
            return [
                'status' => 'error',
                'message' => "Failed to update bundle status: " . $update->error
            ];
        }
    } else {
        return [
            'status' => 'error',
            'message' => 'No new bundles available to mark as failed'
        ];
    }
}

function requestProcessor($request) {
    if ($request['type'] === 'mark_latest_as_passed') {
        return markLatestNewBundleAsPassed();
    } elseif ($request['type'] === 'mark_latest_as_failed') {
        return markLatestNewBundleAsFailed();
    }

    return ["status" => "error", "message" => "Invalid request type"];
}

$server = new rabbitMQServer("testRabbitMQ.ini", "markingMQ");
$server->process_requests('requestProcessor');
?>
