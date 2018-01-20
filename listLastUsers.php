<?php
require_once("runListLastUser.php");

$task = new cronListLastUser();

if($_SERVER['argc'] != 2) {
    usage();
    exit;
}
if(!is_numeric($_SERVER['argv'][1]) || $_SERVER['argv'][1] < 1) {
    usage();
    exit;
}
echo "\nLast ".$_SERVER['argv'][1].' users :'."\n\n";
$task->listMostRecentLoggedInUsers($_SERVER['argv'][1]);


function usage() {
    echo "\n";
    echo "Usage : php listLastUsers.php <value>\n";
    echo "<value> : required, number of accounts to show, must be positive.\n";
}

?>
