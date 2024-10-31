<?php

$BASE_PATH = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
function get_url($dest)
{
    global $BASE_PATH;
    if (str_starts_with($dest, "/")) {
        //handle absolute path
        return $BASE_PATH .  $dest;
    }
    //handle relative path
    return "$BASE_PATH/$dest";
}
?>
