<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function transferBundleFromDeploy($bundleName, $deployServer, $deployPath, $qaPath, $username, $sshKey) {
    $command = "scp -i $sshKey $username@$deployServer:$deployPath/$bundleName $qaPath";
    echo "Executing SCP command: $command\n";

    exec($command, $output, $returnVar);
    if ($returnVar === 0) {
        echo "Bundle successfully transferred to QA: $bundleName\n";
        return true;
    } else {
        echo "Failed to transfer bundle: " . implode("\n", $output) . "\n";
        return false;
    }
}

function installBundleOnQA($bundleName, $qaPath) {
    // install directory on qa
    $installPath = '/var/bundletest';
    $bundlePath = $qaPath . '/' . $bundleName;

    $zip = new ZipArchive();
    if ($zip->open($bundlePath) === TRUE) {
        $zip->extractTo($installPath);
        $zip->close();
        unlink($bundlePath);
        echo "Bundle rolled back and installed successfully on QA.\n";
        return true;
    } else {
        echo "Failed to extract bundle on QA.\n";
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
$qaPath = '/var/qa/bundles';
// ssh username for deploy
$username = 'omarh';
// ssh key path
$sshKey = '../../.ssh/id_rsa';

$previousBundle = fetchPreviousPassedBundle();

if ($previousBundle) {
    echo "Previous bundle to rollback: " . $previousBundle['bundle_name'] . "\n";

    if (transferBundleFromDeploy($previousBundle['bundle_name'], $deployServer, $deployPath, $qaPath, $username, $sshKey)) {
        if (installBundleOnQA($previousBundle['bundle_name'], $qaPath)) {
            echo "Rollback successful on QA.\n";
        } else {
            echo "Failed to install previous bundle on QA.\n";
        }
    }
}
?>
