#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('mysqlconnect.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);
error_log("log handler started");
date_default_timezone_set('America/New_York');

function appendLogToFIle($message) {
	$logFile = '/etc/logs/logfile.log';
	$logMessage = "[" . date('m-d-Y H:i:s') . "] " . $message . PHP_EOL;
	    if (!is_writable($logFile)) {
        error_log("Log file is not writable: " . realpath($logFile));
        return;
    }

    
    if (file_put_contents($logFile, $logMessage, FILE_APPEND) === false) {
        error_log("Failed to write to log file: " . realpath($logFile));
    } else {
        error_log("Successfully written to log file: " . $logMessage);
    }
}

function handleRequest($request) {
    error_log("Received log request:");
    error_log(print_r($request, true));
   if (!isset($request['message'])) {
        error_log("Invalid log request received");
        return;
    }
   $logMessage = $request['message'];
    appendLogToFile($logMessage);
   error_log("Log message written to file: " . $logMessage);
}

$server = new rabbitMQServer("testRabbitMQ.ini", "logsMQ");

$server->channel->queue_declare('log_requests', false, true, false, false);
$server->channel->queue_bind('log_requests', 'logExchange');

$server->process_requests('handleRequest');
?>

