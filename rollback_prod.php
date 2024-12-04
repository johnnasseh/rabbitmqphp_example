<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function transferBundleFromDeploy($bundleName, $deployServer, $deployPath, $prodPath, $username, $sshKey) {
    $command = "scp -i $sshKey $username@$deployServer:$deployPath/$bundleName $prodPath";
    echo "Executing SCP command: $command\n";

    exec($command, $output, $returnVar);
    if ($returnVar === 0) {
        echo "Bundle successfully transferred to Production: $bundleName\n";
        return true;
    } else {
        echo "Failed to transfer bundle: " . implode("\n", $output) . "\n";
        return false;
    }
}

function installBundleOnProd($bundleName, $prodPath) {
    // install directory in prod vm
    $installPath = '/var/prod_environment';
    $bundlePath = $prodPath . '/' . $bundleName;

    $zip = new ZipArchive();
    if ($zip->open($bundlePath) === TRUE) {
        $zip->extractTo($installPath);
        $zip->close();
        unlink($bundlePath);
        echo "Bundle rolled back and installed successfully on Production.\n";
        return true;
    } else {
        echo "Failed to extract bundle on Production.\n";
        return false;
    }
}

function fetchPreviousPassedBundle() {
    $client = new rabbitMQClient("testRabbitMQ.ini", "installMQ");

    $request = [
        'type' => 'fetch_previous_passed_bundle',
    ];

    $response = $client->send_request($request);

    if ($response['status'] === 'success') {
        return $response['bundle'];
    } else {
        echo "Failed to fetch previous passed bundle metadata: " . $response['message'] . "\n";
        return null;
    }
}

// scp things
// deploy vm ip
$deployServer = '192.168.194.182'; 
$deployPath = '/var/deploy/bundles';
$prodPath = '/var/prod/bundles';
// ssh username for deploy
$username = 'omarh';
// ssh key path
$sshKey = '../../.ssh/id_rsa';

$previousBundle = fetchPreviousPassedBundle();

if ($previousBundle) {
    echo "Previous bundle to rollback: " . $previousBundle['bundle_name'] . "\n";

    if (transferBundleFromDeploy($previousBundle['bundle_name'], $deployServer, $deployPath, $prodPath, $username, $sshKey)) {
        if (installBundleOnProd($previousBundle['bundle_name'], $prodPath)) {
            echo "Rollback successful.\n";
        } else {
            echo "Failed to install previous bundle on Production.\n";
        }
    }
}
?>
