<?php
require_once('deploy_mysqlconnect.php');

function installLatestBundle() {
    $db = getDeployDB();

    $query = $db->prepare("SELECT bundle_name FROM Bundles WHERE status = 'new' ORDER BY id DESC LIMIT 1");
    $query->execute();
    $result = $query->get_result();

    if ($row = $result->fetch_assoc()) {
        $bundlePath = '/var/deploy/bundles/' . $row['bundle_name'];

        if (!file_exists($bundlePath)) {
            echo "Bundle file not found: $bundlePath\n";
            return;
        }

        $installPath = '../../project/rabbitmqphp_example';
        $zip = new ZipArchive();
        if ($zip->open($bundlePath) === TRUE) {
            $zip->extractTo($installPath);
            $zip->close();

            $update = $db->prepare("UPDATE Bundles SET status = 'installed' WHERE bundle_name = ?");
            $update->bind_param("s", $row['bundle_name']);
            $update->execute();

            echo "Bundle installed successfully.\n";
        } else {
            echo "Failed to extract bundle.\n";
        }
    } else {
        echo "No new bundles available.\n";
    }
}

installLatestBundle();
?>
