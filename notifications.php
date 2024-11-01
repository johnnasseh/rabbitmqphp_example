<?php
require_once('vendor/autoload.php');
require('nav.php');
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$env = parse_ini_file('.env');
$jwt_secret = $env['JWT_SECRET'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <script>
        function loadNotifications() {
            const token = localStorage.getItem("token");
            if (!token) {
                alert("You must be logged in to view notifications.");
                window.location.href = "index.html";
                return;
            }

            fetch('notifications_sender.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `token=${encodeURIComponent(token)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    displayNotifications(data.notifications);
                } else {
                    alert("Error: " + data.message);
                }
            })
            .catch(error => console.error('Error fetching notifications:', error));
        }

        function displayNotifications(notifications) {
            const container = document.getElementById('notifications');
            container.innerHTML = '';

            if (notifications.length === 0) {
                container.innerHTML = '<p>No notifications at this time.</p>';
                return;
            }

            notifications.forEach(notification => {
                const notificationItem = `
                    <div class="alert alert-info">
                        <h5>${notification.title}</h5>
                        <p>${notification.message}</p>
                        <p><a href="${notification.link}" target="_blank">More Details</a></p>
                        <p>Event Date: ${notification.date_start} ${notification.time_start}</p>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', notificationItem);
            });
        }

        document.addEventListener('DOMContentLoaded', loadNotifications);
    </script>
</head>
<body>
    <div class="container mt-5">
        <h1>Your Notifications</h1>
        <div id="notifications"></div>
    </div>
</body>
</html>
