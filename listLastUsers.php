<?php
require_once("run.php");

$task = new cron();

if($_SERVER['argc'] != 2) {
    $task->printHelpListLastsUsers();
    exit;
}
if(!is_numeric($_SERVER['argv'][1]) || $_SERVER['argv'][1] < 1) {
    $task->printHelpListLastsUsers();
    exit;
}
echo "\nLast ".$_SERVER['argv'][1].' users :'."\n\n";
$task->listTenMostRecentLoggedInUsers($_SERVER['argv'][1]);

?>
