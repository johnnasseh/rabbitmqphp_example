<?php
require_once('mysqlconnect.php'); 
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$jwt_secret = $_ENV['JWT_SECRET'] ?? '';
$smtp_host = $_ENV['SMTP_HOST'] ?? '';
$smtp_user = $_ENV['SMTP_USER'] ?? '';
$smtp_pass = $_ENV['SMTP_PASS'] ?? '';
$smtp_port = $_ENV['SMTP_PORT'] ?? '';

$mydb = getDB(); 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';

    if (!$token) {
        echo json_encode(["status" => "fail", "message" => "Token not provided"]);
        exit;
    }

    try {
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
        $username = $decoded->data->username;

        
        $stmt = $mydb->prepare("
            SELECT Events.name, Events.date, Events.venue_name, Users.email 
            FROM Events 
            JOIN User_Likes ON Events.event_id = User_Likes.event_id 
            JOIN Users ON User_Likes.user_id = Users.id 
            WHERE Users.username = ? 
            LIMIT 1
        ");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $likedEvent = $result->fetch_assoc();

        if (!$likedEvent) {
            echo json_encode(["status" => "fail", "message" => "No liked events found for the user"]);
            exit;
        }

        $email = $likedEvent['email'];
        $eventName = $likedEvent['name'];
        $eventDate = $likedEvent['date'];
        $eventVenue = $likedEvent['venue_name'];

        
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_user;
            $mail->Password = $smtp_pass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtp_port;

            $mail->setFrom($smtp_user, 'Event Notifications');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Your Upcoming Event!';
            $mail->Body = "Hello $username, <br> Don't forget your liked event: <strong>$eventName</strong> happening on <strong>$eventDate</strong> at <strong>$eventVenue</strong>.";

            $mail->send();
            echo json_encode(["status" => "success", "message" => "Email sent!"]);
        } catch (Exception $e) {
            error_log("Mailer Error: {$mail->ErrorInfo}");
            echo json_encode(["status" => "fail", "message" => "Email could not be sent: {$mail->ErrorInfo}"]);
        }
    } catch (Exception $e) {
        error_log("JWT Error: {$e->getMessage()}");
        echo json_encode(["status" => "fail", "message" => "Invalid or expired token"]);
    }
}
?>
