#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('deploy_mysqlconnect.php');

function handleVersionAndRegister($bundleName, $remotePath) {
    $db = getDeployDB();

    $db->begin_transaction();
    $query = $db->prepare("SELECT MAX(version) AS max_version FROM Bundles WHERE bundle_name = ?");
    $query->bind_param('s', $bundleName);
    $query->execute();
    $result = $query->get_result();
    $row = $result->fetch_assoc();
    $version = ($row['max_version'] ?? 0) + 1;

    $pathWithVersion = "{$remotePath}{$bundleName}_v{$version}.tar.gz";

    $register = $db->prepare("INSERT INTO Bundles (bundle_name, version, status, path) VALUES (?, ?, 'new', ?)");
    $register->bind_param('sis', $bundleName, $version, $pathWithVersion);
    $register->execute();

    $db->commit();

    return ["status" => "success", "version" => $version];
}

function requestProcessor($request) {
    if ($request['type'] === 'get_version_and_register') {
        return handleVersionAndRegister($request['bundle_name'], $request['path']);
    }
    return ["status" => "error", "message" => "Invalid request type"];
}

$server = new rabbitMQServer("testRabbitMQ.ini", "deploymentMQ");
$server->process_requests('requestProcessor');
