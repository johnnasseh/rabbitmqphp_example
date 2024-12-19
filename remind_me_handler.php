#!/usr/bin/php
<?php
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('mysqlconnect.php');
require_once('vendor/autoload.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmailNotification($email, $eventTitle, $eventDetails) {
	$env = parse_ini_file('.env');
	$mail = new PHPMailer(true);

	try {
    	$mail->isSMTP();
    	$mail->Host = $env['SMTP_HOST'];
    	$mail->SMTPAuth = true;
    	$mail->Username = $env['SMTP_USER'];
    	$mail->Password = $env['SMTP_PASS'];
    	$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    	$mail->Port = $env['SMTP_PORT'];

    	$mail->setFrom($env['SMTP_USER'], 'Event Notifications');
    	$mail->addAddress($email);

    	$mail->isHTML(true);
    	$mail->Subject = 'Upcoming Event Reminder';
    	$mail->Body = "<p>{$eventDetails}</p>";
    	$mail->AltBody = $eventDetails;

    	$mail->send();
    	error_log("Email sent to $email for event: $eventTitle");
    	return ["status" => "success", "message" => "Email sent successfully"];
	} catch (Exception $e) {
    	error_log("Email failed $email. Error: {$mail->ErrorInfo}");
    	return ["status" => "fail", "message" => "Email failed: {$mail->ErrorInfo}"];
	}
}

function requestProcessor($request) {
	if ($request['type'] !== 'send_reminder') {
    	return ["status" => "fail", "message" => "Invalid request type"];
	}

	$db = getDB();
	$stmt = $db->prepare("
    	SELECT e.title, e.date_start, e.time_start, e.venue_name, u.email
    	FROM User_Likes ul
    	JOIN Events e ON ul.event_id = e.event_id
    	JOIN Users u ON ul.id = u.id
    	WHERE u.username = ?
    	ORDER BY ul.liked_at ASC
    	LIMIT 1
	");
	$stmt->bind_param('s', $request['username']);
	$stmt->execute();
	$result = $stmt->get_result();
	$event = $result->fetch_assoc();

	if (!$event) {
    	return ["status" => "fail", "message" => "No liked events found"];
	}

	$email = $event['email'];
	$eventDetails = sprintf(
    	"Upcoming Event: %s\nDate: %s\nTime: %s\nVenue: %s",
    	$event['title'], $event['date_start'], $event['time_start'], $event['venue_name']
	);

	return sendEmailNotification($email, $event['title'], $eventDetails);
}

$server = new rabbitMQServer("testRabbitMQ.ini", "emailLikesMQ");
$server->process_requests('requestProcessor');
?>
