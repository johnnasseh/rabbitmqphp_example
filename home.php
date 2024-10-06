<?php
session_start();
if (!isset($_SESSION['loggedin'])) {
    header("Location: index.html"); 
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Page</title>
</head>
<body>
    <h1>Welcome, <?php echo $_SESSION['username']; ?>!</h1>
    <p>Your email: <?php echo $_SESSION['email']; ?></p>
</body>
</html>
