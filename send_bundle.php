<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function createZip($directory, $bundleName) {
    $zip = new ZipArchive();
    if ($zip->open($bundleName, ZipArchive::CREATE) === TRUE) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($directory) + 1); 
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();
        return true;
    } else {
        return false;
    }
}

function sendBundleToQueue($zipFilePath) {
    // encodes the binary into base64
    $zipData = base64_encode(file_get_contents($zipFilePath));

    // make rabbit client
    $client = new rabbitMQClient("testRabbitMQ.ini", "deploymentQueue");

    $request = [
        'type' => 'deploy_bundle',
        'filename' => basename($zipFilePath),
        'data' => $zipData,
    ];

    $response = $client->send_request($request);

    if ($response['status'] === 'success') {
        echo "Bundle successfully sent to deployment queue.\n";
    } else {
        echo "Failed to send bundle to deployment queue: " . $response['message'] . "\n";
    }
}

$directoryToInclude = '/project/rabbitmqphp_example';
// placeholder name we can change it once table has been made for deploy db
$bundleName = 'project_bundle_' . date('Ymd_His') . '.zip';

if (createZip($directoryToInclude, $bundleName)) {
    echo "Bundle created: $bundleName\n";
    sendBundleToQueue($bundleName);
    unlink($bundleName);
    echo "Local zip file removed.\n";
} else {
    echo "Error creating zip file.\n";
}
?>
