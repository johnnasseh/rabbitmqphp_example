#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function installBundle($bundleName, $version, $deployServer, $deployPath, $localPath, $username, $sshKey) {
    $bundleFileName = "{$bundleName}_v{$version}.tar.gz";
    $remoteBundlePath = "{$deployPath}/{$bundleFileName}";
    $command = "scp -i $sshKey $username@$deployServer:$remoteBundlePath $localPath";
    exec($command, $output, $returnVar);
    if ($returnVar !== 0) {
        echo "Failed to transfer bundle: " . implode("\n", $output) . "\n";
        return false;
    }

    $bundleFullPath = "{$localPath}/{$bundleFileName}";
    $extractCommand = "tar -xzvf $bundleFullPath -C ../../project/rabbitmqphp_example";
    exec($extractCommand, $extractOutput, $extractReturnVar);
    if ($extractReturnVar !== 0) {
        echo "Failed to extract bundle: " . implode("\n", $extractOutput) . "\n";
        return false;
    }

    unlink($bundleFullPath);

    echo "Bundle installed successfully.\n";
    return true;
}

function requestProcessor($request) {
    if ($request['type'] === 'install_bundle') {
        return installBundle(
            $request['bundle_name'],
            $request['version'],
            $request['deploy_server'],
            $request['deploy_path'],
            $request['local_path'],
            $request['username'],
            $request['ssh_key']
        );
    }
    return ["status" => "error", "message" => "Invalid request type"];
}
// change to installprodMQ for prod
$server = new rabbitMQServer("testRabbitMQ.ini", "installMQ");
$server->process_requests('requestProcessor');
?>
