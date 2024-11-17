<?php
function getDeployDB() {
    $env = parse_ini_file('.env');

    $db_host = $env['DEPLOY_DB_HOST'];
    $db_user = $env['DEPLOY_DB_USER'];
    $db_password = $env['DEPLOY_DB_PASS'];
    $db_name = $env['DEPLOY_DB_NAME'];

    $db = new mysqli($db_host, $db_user, $db_password, $db_name);
    if ($db->errno != 0) {
        echo "Failed to connect to database: " . $db->error . PHP_EOL;
        exit(0);
    }

    return $db;
}
?>
