#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('deploy_mysqlconnect.php');

function fetchBundlePath($bundleName, $version) {
    $db = getDeployDB();

    $query = $db->prepare("SELECT path FROM Bundles WHERE bundle_name = ? AND version = ?");
    $query->bind_param('si', $bundleName, $version);
    $query->execute();
    $result = $query->get_result();

    if ($row = $result->fetch_assoc()) {
        return $row['path'];
    } else {
        echo "Error: Bundle not found for name '$bundleName' and version '$version'.\n";
        return null;
    }
}

function installBundle($bundleName, $version, $deployServer, $localPath, $username, $sshKey) {
    $bundlePath = fetchBundlePath($bundleName, $version);
    if (!$bundlePath) {
        return ["status" => "error", "message" => "Bundle path not found in database."];
    }

    $command = "scp -i $sshKey $username@$deployServer:$bundlePath $localPath";
    exec($command, $output, $returnVar);
    if ($returnVar !== 0) {
        return ["status" => "error", "message" => "Failed to transfer bundle: " . implode("\n", $output)];
    }

    $bundleFileName = basename($bundlePath);
    $bundleFullPath = "{$localPath}/{$bundleFileName}";
    $extractCommand = "tar -xzvf $bundleFullPath -C ~/installtest";
    exec($extractCommand, $extractOutput, $extractReturnVar);
    if ($extractReturnVar !== 0) {
        return ["status" => "error", "message" => "Failed to extract bundle: " . implode("\n", $extractOutput)];
    }

    unlink($bundleFullPath);

    echo "Bundle installed successfully.\n";
    return ["status" => "success", "message" => "Bundle installed successfully."];
}

function requestProcessor($request) {
    if ($request['type'] === 'install_bundle') {
        return installBundle(
            $request['bundle_name'],
            $request['version'],
            $request['deploy_server'],
            $request['local_path'],
            $request['username'],
            $request['ssh_key']
        );
    }
    return ["status" => "error", "message" => "Invalid request type"];
}

// change to installprodMQ queue for prod
$server = new rabbitMQServer("testRabbitMQ.ini", "installMQ");
$server->process_requests('requestProcessor');
?>
