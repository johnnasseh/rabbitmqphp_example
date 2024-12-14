<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

function getBundleNameFromArgs($argv) {
    if (count($argv) < 2) {
        echo "Error: provide bundle name\n";
        echo "Example: php bundler.php (bundle_name)\n";
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

    $dependencies = array_filter(array_map(function($path) use ($file) {
        $resolvedPath = realpath(dirname($file) . '/' . $path);
        return file_exists($resolvedPath) ? $resolvedPath : null;
    }, $dependencies));

    return array_unique($dependencies);
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

function getNextVersion($bundleName) {
    $client = new rabbitMQClient("testRabbitMQ.ini", "deploymentMQ");
    $request = [
        'type' => 'get_next_version',
        'bundle_name' => $bundleName,
    ];
    $response = $client->send_request($request);
    return $response['version'] ?? 1;
}

function registerBundle($bundleName, $version, $path) {
    $client = new rabbitMQClient("testRabbitMQ.ini", "deploymentMQ");
    $request = [
        'type' => 'register_bundle',
        'bundle_name' => $bundleName,
        'version' => $version,
        'status' => 'new',
        'path' => $path,
    ];
    $response = $client->send_request($request);
    if ($response['status'] === 'success') {
        echo "Bundle registered successfully.\n";
    } else {
        echo "Failed to register bundle: " . $response['message'] . "\n";
    }
}

function transferBundleWithSCP($bundlePath, $remoteServer, $remotePath, $username, $sshKey) {
    $command = "scp -i $sshKey $bundlePath $username@$remoteServer:$remotePath";
    echo "Executing SCP command: $command\n";

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

$version = getNextVersion($bundleName);
$finalBundleName = "{$bundleName}_v{$version}";

$bundlePath = createBundle($allFiles, $finalBundleName);

if ($bundlePath) {
    // scp values
    $remoteServer = '192.168.194.182';
    $remotePath = '/var/deploy/bundles/';
    $username = 'omarh';
    $sshKey = '../../.ssh/id_rsa'; 

    if (transferBundleWithSCP($bundlePath, $remoteServer, $remotePath, $username, $sshKey)) {
        registerBundle($bundleName, $version, $remotePath . $finalBundleName . '.tar.gz');
    }
}
?>
