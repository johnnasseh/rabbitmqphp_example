<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function getBundleNameFromArgs($argv) {
    if (count($argv) < 2) {
        echo "Error: Provide a bundle name first.\n";
        echo "Usage: php bundler.php (bundle_name)\n";
        exit(1);
    }
    return $argv[1];
}

function calculateChecksums($directory) {
    $checksums = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $filePath = $file->getPathname();
            $checksums[$filePath] = hash_file('sha256', $filePath);
        }
    }
    return $checksums;
}

function getModifiedFilesByChecksum($directory, $checksumFile) {
    $modifiedFiles = [];
    $currentChecksums = calculateChecksums($directory);

    $previousChecksums = file_exists($checksumFile) ? json_decode(file_get_contents($checksumFile), true) : [];

    foreach ($currentChecksums as $filePath => $currentChecksum) {
        if (!isset($previousChecksums[$filePath]) || $currentChecksum !== $previousChecksums[$filePath]) {
            $modifiedFiles[] = $filePath;
        }
    }

    file_put_contents($checksumFile, json_encode($currentChecksums));

    return $modifiedFiles;
}

function getDependencies($file) {
    $dependencies = [];
    $extension = pathinfo($file, PATHINFO_EXTENSION);

    if ($extension === 'php') {
        $content = file_get_contents($file);
        preg_match_all('/(include|require|require_once)\s*[\'"](.*?)[\'"]/', $content, $matches);
        $dependencies = array_merge($dependencies, $matches[2]);
    }

    if ($extension === 'ini') {
        $content = parse_ini_file($file);
        foreach ($content as $key => $value) {
            if (is_string($value) && file_exists($value)) {
                $dependencies[] = $value;
            }
        }
    }

    return array_filter($dependencies, 'file_exists');
}

function createBundle($files, $finalBundleName) {
    $tempDir = '/tmp/bundle_temp/';
    if (is_dir($tempDir)) {
        exec("rm -rf {$tempDir}");
    }
    mkdir($tempDir, 0777, true);

    foreach ($files as $file) {
        $relativePath = str_replace('/home/matt/project/rabbitmqphp_example/', '', $file);
        $dest = $tempDir . $relativePath;
        $destDir = dirname($dest);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }
        copy($file, $dest);
    }

    $bundlePath = "/tmp/{$finalBundleName}.tar.gz";
    $command = "tar -czvf $bundlePath -C $tempDir .";
    exec($command, $output, $returnVar);

    exec("rm -rf {$tempDir}");

    if ($returnVar === 0) {
        echo "Bundle created: $bundlePath\n";
        return $bundlePath;
    } else {
        echo "Error creating bundle.\n";
        return null;
    }
}

function getNextVersionAndRegister($bundleName, $remotePath) {
    $client = new rabbitMQClient("testRabbitMQ.ini", "deploymentMQ");
    $request = [
        'type' => 'get_version_and_register',
        'bundle_name' => $bundleName,
        'path' => $remotePath
    ];
    $response = $client->send_request($request);
    if ($response['status'] === 'success') {
        return $response['version'];
    } else {
        echo "Error registering bundle: " . $response['message'] . "\n";
        exit(1);
    }
}

function transferBundleWithSCP($bundlePath, $remoteServer, $remotePath, $username, $sshKey) {
    $command = "scp -i $sshKey $bundlePath $username@$remoteServer:$remotePath";
    exec($command, $output, $returnVar);
    if ($returnVar === 0) {
        echo "Bundle successfully transferred to $remoteServer.\n";
        return true;
    } else {
        echo "Failed to transfer bundle: " . implode("\n", $output) . "\n";
        return false;
    }
}

$bundleName = getBundleNameFromArgs($argv);
// change directory for other vms
$projectDirectory = '/home/matt/project/rabbitmqphp_example';
$checksumFile = '/etc/deploy/checksums.json';

$modifiedFiles = getModifiedFilesByChecksum($projectDirectory, $checksumFile);
if (empty($modifiedFiles)) {
    echo "No modified files found.\n";
    exit;
}

$allFiles = $modifiedFiles;
foreach ($modifiedFiles as $file) {
    $dependencies = getDependencies($file);
    $allFiles = array_merge($allFiles, $dependencies);
}
$allFiles = array_unique($allFiles);

$remoteServer = '192.168.194.182';
$remotePath = '/var/deploy/bundles/';
$username = 'omarh';
$sshKey = '../../.ssh/id_rsa';

$version = getNextVersionAndRegister($bundleName, $remotePath);
$finalBundleName = "{$bundleName}_v{$version}";
$finalPath = "{$remotePath}{$finalBundleName}.tar.gz";

$bundlePath = createBundle($allFiles, $finalBundleName);
if ($bundlePath) {
    if (transferBundleWithSCP($bundlePath, $remoteServer, $remotePath, $username, $sshKey)) {
        echo "Bundle successfully registered and transferred: {$finalBundleName}\n";
    }
}
