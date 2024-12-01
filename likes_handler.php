#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('mysqlconnect.php');
require_once('vendor/autoload.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];
$mydb = getDB();

function getLikedEvents($username) {
    global $mydb;

    $stmt = $mydb->prepare("
        SELECT Events.event_id, Events.title, Events.date_start, Events.time_start, Events.location, Events.address, 
               Events.description, Events.thumbnail, Events.link, Events.venue_name, Events.venue_reviews, Events.venue_link
        FROM Events
        JOIN User_Likes ON Events.event_id = User_Likes.event_id
        JOIN Users ON User_Likes.id = Users.id
        WHERE Users.username = ?
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    $likedEvents = [];
    while ($row = $result->fetch_assoc()) {
        $likedEvents[] = $row;
    }

    error_log("Liked events fetched for user '$username': " . json_encode($likedEvents));
    return ["status" => "success", "likedEvents" => $likedEvents];
}

function sendEmailNotification($email, $eventTitle) {
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

        $mail->setFrom('notifications@yourapp.com', 'YourApp Notifications');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Event Liked Notification';
        $mail->Body = "<p>Thank you for liking the event: <strong>{$eventTitle}</strong>!</p>";
        $mail->AltBody = "Thank you for liking the event: {$eventTitle}!";

        $mail->send();
        error_log("Email notification sent to $email for event '$eventTitle'");
    } catch (Exception $e) {
        error_log("Email notification failed: " . $mail->ErrorInfo);
    }
}

function requestProcessor($request) {
    global $jwt_secret, $mydb;

    error_log("Request received in likes_handler:");
    error_log(print_r($request, true));

    if (!isset($request['type'])) {
        return ["status" => "fail", "message" => "Invalid request type"];
    }

    switch ($request['type']) {
        case 'get_liked_events':
            if (!isset($request['username'])) {
                return ["status" => "fail", "message" => "Username not provided"];
            }
            $username = $request['username'];
            error_log("Fetching liked events for username: " . $username);
            return getLikedEvents($username);

        case 'like_event':
            if (!isset($request['username']) || !isset($request['event_id']) || !isset($request['event_title'])) {
                return ["status" => "fail", "message" => "Incomplete like data provided"];
            }
            $username = $request['username'];
            $eventId = $request['event_id'];
            $eventTitle = $request['event_title'];

            $stmt = $mydb->prepare("INSERT INTO User_Likes (id, event_id) VALUES ((SELECT id FROM Users WHERE username = ?), ?)");
            $stmt->bind_param("si", $username, $eventId);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                error_log("User '$username' liked event '$eventId' ('$eventTitle')");

                $stmtEmail = $mydb->prepare("SELECT email FROM Users WHERE username = ?");
                $stmtEmail->bind_param("s", $username);
                $stmtEmail->execute();
                $result = $stmtEmail->get_result();
                $user = $result->fetch_assoc();

                if ($user && isset($user['email'])) {
                    sendEmailNotification($user['email'], $eventTitle);
                }

                return ["status" => "success", "message" => "Event liked and email notification sent"];
            } else {
                error_log("Failed to like event for user '$username'");
                return ["status" => "fail", "message" => "Failed to like event"];
            }

        default:
            error_log("Unsupported request type: " . $request['type']);
            return ["status" => "fail", "message" => "Unsupported request type"];
    }
}

$server = new rabbitMQServer("testRabbitMQ.ini", "likesMQ");
$server->process_requests('requestProcessor');
?>
