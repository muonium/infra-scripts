<?php
require_once("run.php");

$task = new cron();

if($_SERVER['argc'] != 3 && $_SERVER['argc'] != 4) {
    $task->printHelpDeleteUser();
    exit;
}

$isVerbose = (in_array("-v", $_SERVER['argv']) || in_array("--verbose", $_SERVER['argv'])) ? true : false;

if(in_array("-u", $_SERVER['argv']) || in_array("--username", $_SERVER['argv'])) {
    //delete by username
    
    $position = array_search("-u", $_SERVER['argv']);
    if($position === FALSE) {
        $position = array_search("--username", $_SERVER['argv']);
    }
    try {
        $usernameValue = $_SERVER['argv'][$position+1];
        $task->deleteByUsername($usernameValue, $isVerbose);
    } catch(OutOfBoundsException $e) {
        $task->printHelpDeleteUser();
    }
    exit;
}

if(in_array("-e", $_SERVER['argv']) || in_array("--email", $_SERVER['argv'])) {
    //delete by email
    
    $position = array_search("-e", $_SERVER['argv']);
    if($position === FALSE) {
        $position = array_search("--email", $_SERVER['argv']);
    }
    try {
        $emailValue = $_SERVER['argv'][$position+1];
        $task->deleteByEmail($emailValue, $isVerbose);
    } catch(OutOfBoundsException $e) {
        $task->printHelpDeleteUser();
    }
    exit;
}

if(in_array("-i", $_SERVER['argv']) || in_array("--id", $_SERVER['argv'])) {
    //delete by ID
    
    $position = array_search("-i", $_SERVER['argv']);
    if($position === FALSE) {
        $position = array_search("--id", $_SERVER['argv']);
    }
    try {
        $IDvalue = $_SERVER['argv'][$position+1];
        $task->deleteByID($IDvalue, $isVerbose);
    } catch(OutOfBoundsException $e) {
        $task->printHelpDeleteUser();
    }
    exit;
}

$task->printHelpDeleteUser();


?>