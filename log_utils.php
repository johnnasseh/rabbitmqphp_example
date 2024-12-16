<?php

$logServer = new rabbitMQClient("testRabbitMQ.ini", "logsMQ");

function log_message($message)
{
	global $logServer;
	$env = parse_ini_file('.env');
	$machineId = $env['MACHINE_ID'] ?? 'UnknownMachine';
	$hostname = gethostname();

    $logRequest = array(
        'type' => 'log',
        'message' => "[{$hostname}, {$machineId}] " . $message,
        'timestamp' => date('m-d-Y H:i:s'),
    );

    try {
        $logServer->publish($logRequest);
        error_log("Log message published: " . json_encode($logRequest), 4);
    } catch (Exception $e) {
        error_log("Failed to publish log message: " . $e->getMessage(), 4);
    }
}

?>

