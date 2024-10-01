#!/usr/bin/php
<?php

require_once 'mysqlconnect.php';
$mydb = getDB();
$query = "SELECT * FROM Roles";
$stmt = $mydb->prepare($query);
	
if ($stmt === false) {
	echo "error" . $mydb->error . PHP_EOL;
	exit(0);
}	
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
	while ($row = $result->fetch_assoc()) {

		print_r($row);
	}
} else {
	echo "No records" . PHP_EOL;
}

$stmt->close();
$mydb->close();
?>
