<?php
require_once("run.php");
require_once("runMail.php");

$task = new cron();
$taskMail = new cronMail();
//$taskMail->deleteInactiveUsers(); // Do not execute it for now
$taskMail->remindSubscriptionsDaysLeft();
$task->deleteNotCompletedFiles();
$task->updateUpgrades();
?>
