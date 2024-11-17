<?php
require_once('deploy_mysqlconnect.php');

function markBundleStatus($status) {
    if (!in_array($status, ['passed', 'failed'])) {
        echo "Invalid status: $status\n";
        return;
    }

    $db = getDeployDB();
    $query = $db->prepare("SELECT bundle_name FROM Bundles WHERE status = 'installed' ORDER BY id DESC LIMIT 1");
    $query->execute();
    $result = $query->get_result();

    if ($row = $result->fetch_assoc()) {
        $update = $db->prepare("UPDATE Bundles SET status = ? WHERE bundle_name = ?");
        $update->bind_param("ss", $status, $row['bundle_name']);
        $update->execute();

        echo "Bundle marked as $status.\n";
    } else {
        echo "No installed bundles available.\n";
    }
}

$status = $argv[1] ?? null;
if ($status) {
    markBundleStatus($status);
} else {
    echo "Usage: php mark_bundle_status.php <passed|failed>\n";
}
?>
