<?php
require_once("run.php");

$task = new cron();

if($_SERVER['argc'] != 3 && $_SERVER['argc'] != 4) {
    $task->printHelpDeleteUser();
    exit;
}

$isVerbose = (in_array("-v", $_SERVER['argv']) || in_array("--verbose", $_SERVER['argv'])) ? true : false;
$argumentPosition = ($isVerbose) ? 2 : 1;
$isNumberArgumentValid = (($isVerbose && $_SERVER['argc'] == 4) || (!$isVerbose && $_SERVER['argc'] == 3)) ? true : false;

if(!$isNumberArgumentValid) {
    $task->printHelpDeleteUser();
    exit;
}

switch($_SERVER['argv'][$argumentPosition]) {
            
    case '-u':
    case '--username':
        $usernameValue = $_SERVER['argv'][$argumentPosition+1];
        $task->deleteByUsername($usernameValue, $isVerbose);
        break;
        
    case '-e':
    case '--email':
        $emailValue = $_SERVER['argv'][$argumentPosition+1];
        $task->deleteByEmail($emailValue, $isVerbose);
        break;
        
    case '-i':
    case '--id':
        $IDvalue = $_SERVER['argv'][$argumentPosition+1];
        $task->deleteByID($IDvalue, $isVerbose);
        break;
        
    default:
        $task->printHelpDeleteUser();
        break;
        
}


?>