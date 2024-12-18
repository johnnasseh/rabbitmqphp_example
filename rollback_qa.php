<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('deploy_mysqlconnect.php');

function getLatestPassedVersion($bundleName) {
    $db = getDeployDB();
    
    $query = $db->prepare("SELECT version FROM Bundles WHERE bundle_name = ? AND status = 'passed' ORDER BY version DESC LIMIT 1");
    $query->bind_param('s', $bundleName);
    $query->execute();
    $result = $query->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['version'];
    } else {
        return null;
    }
}

if ($argc < 2) {
    echo "Use: php rollback_qa.php (bundle_name)\n";
    exit(1);
}

$bundleName = $argv[1];
$latestVersion = getLatestPassedVersion($bundleName);

if ($latestVersion === null) {
    echo "Error: No 'passed' version found for bundle '$bundleName'.\n";
    exit(1);
}

$client = new rabbitMQClient("testRabbitMQ.ini", "installMQ");
$request = [
    'type' => 'install_bundle',
    'bundle_name' => $bundleName,
    'version' => $latestVersion,
    'deploy_server' => '10.242.1.158',
    'local_path' => '/var/qa/bundles',
    'username' => 'omarh',
    'ssh_key' => '../../.ssh/id_rsa',
];

$response = $client->send_request($request);
echo "Response: " . print_r($response, true) . "\n";
?>
