<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

if ($argc < 3) {
    echo "Use: php fail_bundle.php (bundle_name) (version)\n";
    exit(1);
}

$bundleName = $argv[1];
$version = (int)$argv[2];

$client = new rabbitMQClient("testRabbitMQ.ini", "markingMQ");
$request = [
    'type' => 'mark_bundle',
    'bundle_name' => $bundleName,
    'version' => $version,
    'status' => 'failed'
];

$response = $client->send_request($request);
echo "Response: " . print_r($response, true) . "\n";
?>
