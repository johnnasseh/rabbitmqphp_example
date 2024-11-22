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

function transferFileWithSCP($zipFilePath, $remoteServer, $remotePath, $username, $sshKey) {
    $command = "scp -i $sshKey $zipFilePath $username@$remoteServer:$remotePath";

    echo "Executing SCP command: $command\n";

    exec($command, $output, $returnVar);
    if ($returnVar === 0) {
        echo "File successfully transferred\n";
        return true;
    } else {
        echo "File failed to send, Error: " . implode("\n", $output) . "\n";
        return false;
    }
}

function sendMetadataToQueue($filename, $targetLocation) {
    $client = new rabbitMQClient("testRabbitMQ.ini", "deploymentMQ");

    $request = [
        'type' => 'deploy_metadata',
        'filename' => $filename,
        'location' => $targetLocation,
        'status' => 'new',
    ];

    $response = $client->send_request($request);

    if ($response['status'] === 'success') {
        echo "Metadata successfully sent to RabbitMQ.\n";
    } else {
        echo "Failed to send metadata to RabbitMQ: " . $response['message'] . "\n";
    }
    echo "RabbitMQ Response: " . print_r($response, true) . "\n";
}

// bundle values
$directoryToInclude = '../../project/rabbitmqphp_example/';
$bundleName = 'project_bundle_' . date('Ymd_His') . '.zip';

// scp values
// omars ip
$remoteServer = '192.168.194.182'; 
// path where bundle gets stored on omars vm
$remotePath = '/var/deploy/bundles/';
// omars user for ssh
$username = 'omarh';
// path to private ssh key
$sshKey = '../../.ssh/id_rsa';


if (createZip($directoryToInclude, $bundleName)) {
    echo "Bundle created: $bundleName\n";
    if (transferFileWithSCP($bundleName, $remoteServer, $remotePath, $username, $sshKey)) {
        sendMetadataToQueue($bundleName, $remotePath);
    }
    unlink($bundleName);
    echo "Local zip file removed.\n";
} else {
    echo "Error creating zip file.\n";
}
?>
