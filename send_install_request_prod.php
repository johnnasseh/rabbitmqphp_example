<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

if ($argc < 3) {
    echo "Use: php send_install_request_qa.php (bundle_name) (version)\n";
    exit(1);
}

$bundleName = $argv[1];
$version = (int)$argv[2];

$client = new rabbitMQClient("testRabbitMQ.ini", "installprodMQ");

$request = [
    'type' => 'install_bundle',
    'bundle_name' => $bundleName,
    'version' => $version,
    'deploy_server' => '10.147.17.182',
    'local_path' => '/var/prod/bundles',
    'install_path' => '/var/www/html',
    'username' => 'omarh',
    'ssh_key' => '../../.ssh/id_rsa'
];

$response = $client->send_request($request);
echo "Response: " . print_r($response, true) . "\n";
?>
