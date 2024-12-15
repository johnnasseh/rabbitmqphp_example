<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function getBundleNameFromArgs($argv) {
    if (count($argv) < 2) {
        echo "Error: Please provide a bundle name as the first argument.\n";
        echo "Usage: php bundler.php <bundle_name>\n";
        exit(1);
    }
    return $argv[1];
}

function getModifiedFiles() {
    $command = "git diff --name-only HEAD~1";
    exec($command, $output, $returnVar);
    if ($returnVar === 0) {
        return $output;
    } else {
        echo "Error fetching modified files: " . implode("\n", $output) . "\n";
        return [];
    }
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

    if ($extension === 'html') {
        $content = file_get_contents($file);
        preg_match_all('/<link[^>]+href=["\']([^"\']+)["\']/', $content, $linkMatches);
        $dependencies = array_merge($dependencies, $linkMatches[1]);

        preg_match_all('/<script[^>]+src=["\']([^"\']+)["\']/', $content, $scriptMatches);
        $dependencies = array_merge($dependencies, $scriptMatches[1]);

        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/', $content, $imgMatches);
        $dependencies = array_merge($dependencies, $imgMatches[1]);
    }

    return array_filter($dependencies, 'file_exists');
}

function createBundle($files, $bundleName) {
    $tempDir = '/tmp/bundle_temp/';
    if (is_dir($tempDir)) {
        exec("rm -rf {$tempDir}");
    }
    mkdir($tempDir, 0777, true);

    foreach ($files as $file) {
        $dest = $tempDir . $file;
        $destDir = dirname($dest);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }
        copy($file, $dest);
    }

    $bundlePath = "/tmp/{$bundleName}.tar.gz";
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

function registerAndVersionBundle($bundleName, $path) {
    $client = new rabbitMQClient("testRabbitMQ.ini", "deploymentMQ");
    $request = [
        'type' => 'get_version_and_register',
        'bundle_name' => $bundleName,
        'path' => $path,
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

$modifiedFiles = getModifiedFiles();
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

// scp values
$remoteServer = '192.168.194.182';
$remotePath = '/var/deploy/bundles/';
$username = 'omarh';
$sshKey = '../../.ssh/id_rsa';

$bundlePath = createBundle($allFiles, $bundleName);
if ($bundlePath) {
    if (transferBundleWithSCP($bundlePath, $remoteServer, $remotePath, $username, $sshKey)) {
        $version = registerAndVersionBundle($bundleName, $remotePath . basename($bundlePath));
        echo "Bundle successfully registered with version: $version\n";
    }
}
?>
