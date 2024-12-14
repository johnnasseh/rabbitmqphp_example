#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('deploy_mysqlconnect.php');

function getNextVersion($bundleName) {
    $db = getDeployDB();
    $query = $db->prepare("SELECT MAX(version) AS max_version FROM Bundles WHERE name = ?");
    $query->bind_param('s', $bundleName);
    $query->execute();
    $result = $query->get_result();
    $row = $result->fetch_assoc();
    return ($row['max_version'] ?? 0) + 1;
}

function registerBundle($bundleName, $version, $status, $path) {
    $db = getDeployDB();
    $query = $db->prepare("INSERT INTO Bundles (name, version, status, path) VALUES (?, ?, ?, ?)");
    $query->bind_param('siss', $bundleName, $version, $status, $path);
    if ($query->execute()) {
        return ["status" => "success", "message" => "Bundle registered successfully"];
    } else {
        return ["status" => "error", "message" => "Failed to register bundle: " . $query->error];
    }
}

function fetchLatestBundle($bundleName, $status = 'new') {
    $db = getDeployDB();
    $query = $db->prepare("SELECT name, version, path FROM Bundles WHERE name = ? AND status = ? ORDER BY version DESC LIMIT 1");
    $query->bind_param('ss', $bundleName, $status);
    $query->execute();
    $result = $query->get_result();
    if ($row = $result->fetch_assoc()) {
        return ["status" => "success", "bundle" => $row];
    } else {
        return ["status" => "error", "message" => "No bundles found with name '$bundleName' and status '$status'"];
    }
}

function fetchPreviousPassedBundle($bundleName) {
    $db = getDeployDB();
    $query = $db->prepare("SELECT name, version, path FROM Bundles WHERE name = ? AND status = 'passed' ORDER BY version DESC LIMIT 1 OFFSET 1");
    $query->bind_param('s', $bundleName);
    $query->execute();
    $result = $query->get_result();
    if ($row = $result->fetch_assoc()) {
        return ["status" => "success", "bundle" => $row];
    } else {
        return ["status" => "error", "message" => "No previous passed bundles available for rollback"];
    }
}

function updateBundleStatus($bundleName, $version, $status) {
    $db = getDeployDB();
    $query = $db->prepare("UPDATE Bundles SET status = ? WHERE name = ? AND version = ?");
    $query->bind_param('ssi', $status, $bundleName, $version);
    if ($query->execute()) {
        return ["status" => "success", "message" => "Bundle status updated to '$status'"];
    } else {
        return ["status" => "error", "message" => "Failed to update bundle status: " . $query->error];
    }
}

function requestProcessor($request) {
    switch ($request['type']) {
        case 'get_next_version':
            $bundleName = $request['bundle_name'];
            $version = getNextVersion($bundleName);
            return ["status" => "success", "version" => $version];
        case 'register_bundle':
            return registerBundle(
                $request['bundle_name'],
                $request['version'],
                $request['status'],
                $request['path']
            );
        case 'fetch_latest_bundle':
            return fetchLatestBundle($request['bundle_name'], $request['status']);
        case 'fetch_previous_passed_bundle':
            return fetchPreviousPassedBundle($request['bundle_name']);
        case 'update_bundle_status':
            return updateBundleStatus(
                $request['bundle_name'],
                $request['version'],
                $request['status']
            );
        default:
            return ["status" => "error", "message" => "Invalid request type"];
    }
}

$server = new rabbitMQServer("testRabbitMQ.ini", "deploymentMQ");
$server->process_requests('requestProcessor');
?>
