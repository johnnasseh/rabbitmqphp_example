#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('deploy_mysqlconnect.php');

function requestProcessor($request) {
    if (!isset($request['type'])) {
        return ["status" => "fail", "message" => "Invalid request"];
    }

    switch ($request['type']) {
        case 'deploy_bundle':
            return saveBundleToDB($request['filename'], $request['data']);
        default:
            return ["status" => "fail", "message" => "Unsupported request type"];
    }
}

function saveBundleToDB($filename, $zipData) {
    $db = getDeployDB();

    $bundlePath = '/var/deploy/bundles/' . $filename;
    file_put_contents($bundlePath, base64_decode($zipData));

    $insert = $db->prepare("INSERT INTO Bundles (bundle_name, status) VALUES (?, 'new')");
    $insert->bind_param("s", $filename);

    if ($insert->execute()) {
        return ["status" => "success", "message" => "Bundle saved to DB"];
    } else {
        return ["status" => "fail", "message" => "Error saving bundle to DB"];
    }
}

$server = new rabbitMQServer("testRabbitMQ.ini", "deploymentMQ");
$server->process_requests('requestProcessor');
?>
