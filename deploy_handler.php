#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('deploy_mysqlconnect.php');

function processBundle($filename, $location) {
    $db = getDeployDB();

    $query = $db->prepare("SELECT * FROM Bundles WHERE bundle_name = ?");
    $query->bind_param('s', $filename);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows === 0) {
        $status = 'new';
        $insert = $db->prepare("INSERT INTO Bundles (bundle_name, bundle_path, status) VALUES (?, ?, ?)");
        $bundlePath = $location . $filename;
        $insert->bind_param('sss', $filename, $bundlePath, $status);
        if ($insert->execute()) {
            echo "Bundle '$filename' added to Bundles table.\n";
        } else {
            echo "Failed to add bundle '$filename': " . $insert->error . "\n";
        }
    } else {
        echo "Bundle '$filename' already exists in Bundles table.\n";
    }
}

function requestProcessor($request) {
    if ($request['type'] === 'deploy_metadata') {
        $filename = $request['filename'];
        $location = $request['location'];
        processBundle($filename, $location);
        return ["status" => "success", "message" => "Bundle processed and recorded in the database"];
    }

    return ["status" => "error", "message" => "Invalid request type"];
}

$server = new rabbitMQServer("testRabbitMQ.ini", "deploymentMQ");
$server->process_requests('requestProcessor');
?>
