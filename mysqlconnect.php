#!/usr/bin/php
<?php

$env = parse_ini_file('.env');

$db_host = $env['DB_HOST'];
$db_user = $env['DB_USER'];
$db_password = $env['DB_PASS'];
$db_name = $env['DB_NAME'];

$mydb = new mysqli($db_host, $db_user, $db_password, $db_name);

if ($mydb->errno != 0)
{
	echo "failed to connect to database: ". $mydb->error . PHP_EOL;
	exit(0);
}

echo "successfully connected to database".PHP_EOL;

$query = "select * from Users;";

$response = $mydb->query($query);
if ($mydb->errno != 0)
{
	echo "failed to execute query:".PHP_EOL;
	echo __FILE__.':'.__LINE__.":error: ".$mydb->error.PHP_EOL;
	exit(0);
}


?>
