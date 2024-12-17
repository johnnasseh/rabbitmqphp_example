<?php

$logServer = new rabbitMQClient("testRabbitMQ.ini", "logsMQ");

function log_message($message)
{
	global $logServer;
	$env = parse_ini_file('.env');
	$machineId = $env['MACHINE_ID'] ?? 'UnknownMachine';
	error_log("Machine ID loaded in log_utils: " . $machineId, 4);
	$hostname = gethostname() ?: 'UnknownHost';

	error_log("log_message() called. Machine ID: {$machineId}, Hostname: {$hostname}", 4);

    $logRequest = array(
        'type' => 'log',
        'message' => $message,
	'timestamp' => date('m-d-Y H:i:s'),
	        'origin_machine' => $machineId,
        'origin_hostname' => $hostname,
    );

    try {
        $logServer->publish($logRequest);
        error_log("Log message published: " . json_encode($logRequest), 4);
    } catch (Exception $e) {
        error_log("Failed to publish log message: " . $e->getMessage(), 4);
    }
}

?>

