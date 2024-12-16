#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);
error_log("log handler started", 4);
date_default_timezone_set('America/New_York');
$env = parse_ini_file('.env');
$machineId = $env['MACHINE_ID'] ?? 'UnknownMachine';
error_log("Machine ID loaded in log_handler: " . $machineId, 4);
$queueName = "log_" . $machineId;

error_log("Log handler started for machine: $machineId", 4);

function appendLogToFIle($message) {
	$logFile = '/etc/logs/logfile.log';
	$logMessage = "[" . date('m-d-Y H:i:s') . "] " . $message . PHP_EOL;
	    if (!is_writable($logFile)) {
        error_log("Log file is not writable: " . realpath($logFile), 4);
        return;
    }

    
    if (file_put_contents($logFile, $logMessage, FILE_APPEND) === false) {
        error_log("Failed to write to log file: " . realpath($logFile),4 );
    } else {
        error_log("Successfully written to log file: " . $logMessage, 4);
    }
}

function handleRequest($request) {
	global $machineId;
    error_log("Received log request on " . $GLOBALS['machineId'] .":", 4);
    error_log(print_r($request, true), 4);
   if (!isset($request['message'])) {
        error_log("Invalid log request received", 4);
        return;
   }
        $originMachine = $request['origin_machine'] ?? 'UnknownMachine';
    $originHostname = $request['origin_hostname'] ?? 'UnknownHost';
   $logMessage = "[Origin: {$originMachine}, Host: {$originHostname}] " . $request['message'];
    appendLogToFile($logMessage);
   error_log("Log message written to file: " . $logMessage, 4);
}
$server = new rabbitMQServer("testRabbitMQ.ini", "logsMQ");
//$server->set_queue($queueName);
while (true) {
    try {
        // dynamically bind the machine-specific queue
      //  $server->declare_queue($queueName, 'logExchange', 'fanout');
        $server->process_requests('handleRequest');
    } catch (Exception $e) {
        error_log("Error in log handler: " . $e->getMessage(), 4);
        sleep(1);
    }
}
?>
