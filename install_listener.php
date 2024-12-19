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
        echo "Error: Bundle not found in database for name '$bundleName' and version '$version'.\n";
        return null;
    }
}

function installBundle($bundleName, $version, $deployServer, $localPath, $installPath, $username, $sshKey) {
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
    $tempExtractPath = "/tmp/extracted_bundle";

    if (is_dir($tempExtractPath)) {
        exec("rm -rf $tempExtractPath");
    }
    mkdir($tempExtractPath, 0777, true);

    $extractCommand = "tar -xzvf $bundleFullPath -C $tempExtractPath";
    exec($extractCommand, $extractOutput, $extractReturnVar);
    if ($extractReturnVar !== 0) {
        return ["status" => "error", "message" => "Failed to extract bundle: " . implode("\n", $extractOutput)];
    }

    $syncCommand = "rsync -av --no-group $tempExtractPath/ $installPath/";
    exec($syncCommand, $syncOutput, $syncReturnVar);
    if ($syncReturnVar !== 0) {
        return ["status" => "error", "message" => "Failed to sync files: " . implode("\n", $syncOutput)];
    }

    unlink($bundleFullPath);
    exec("rm -rf $tempExtractPath");

    $restartCommand = "sudo systemctl restart apache2";
    exec($restartCommand, $restartOutput, $restartReturnVar);

    if ($restartReturnVar !== 0) {
        return ["status" => "error", "message" => "Failed to restart Apache: " . implode("\n", $restartOutput)];
    }

    echo "Bundle installed and Apache restarted successfully to $installPath.\n";
    return ["status" => "success", "message" => "Bundle installed and Apache restarted successfully to $installPath."];
}

function requestProcessor($request) {
    if ($request['type'] === 'install_bundle') {
        return installBundle(
            $request['bundle_name'],
            $request['version'],
            $request['deploy_server'],
            $request['local_path'],
            $request['install_path'],
            $request['username'],
            $request['ssh_key']
        );
    }
    return ["status" => "error", "message" => "Invalid request type"];
}

$server = new rabbitMQServer("testRabbitMQ.ini", "installMQ");
$server->process_requests('requestProcessor');
?>
