#!/usr/bin/php
<?php
require_once('rabbitMQLib.inc');
require_once('vendor/autoload.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$env = parse_ini_file('.env');

function sendEmailNotification($email, $eventTitle, $eventDetails) {
	global $env;

	$mail = new PHPMailer(true);

	try {
    	$mail->isSMTP();
    	$mail->Host = $env['SMTP_HOST'];
    	$mail->SMTPAuth = true;
    	$mail->Username = $env['SMTP_USER'];
    	$mail->Password = $env['SMTP_PASS'];
    	$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    	$mail->Port = $env['SMTP_PORT'];

    	$mail->setFrom('notifications@eventpulse.com', 'EventPulse Notifications');
    	$mail->addAddress($email);

    	$mail->isHTML(true);
    	$mail->Subject = 'Upcoming Event Notification';
    	$mail->Body = "<p>{$eventDetails}</p>";
    	$mail->AltBody = $eventDetails;

    	$mail->send();
    	error_log("Email notification sent to $email for event '$eventTitle'");
    	return ["status" => "success", "message" => "Email sent successfully"];
	} catch (Exception $e) {
    	error_log("Failed to send email to $email. Error: " . $mail->ErrorInfo);
    	return ["status" => "fail", "message" => "Failed to send email"];
	}
}

function requestProcessor($request) {
	if ($request['type'] !== "send_email") {
    	return ["status" => "fail", "message" => "Invalid request type"];
	}

	$email = $request['email'];
	$eventTitle = $request['event_title'];
	$eventDetails = $request['event_details'];

	return sendEmailNotification($email, $eventTitle, $eventDetails);
}

$server = new rabbitMQServer("testRabbitMQ.ini", "emailLikesMQ");
$server->process_requests('requestProcessor');
?>
