<?php
require_once("run.php");

$task = new cron();

if($_SERVER['argc'] != 3 && $_SERVER['argc'] != 4) {
    usage();
    exit;
}

$isVerbose = (in_array("-v", $_SERVER['argv']) || in_array("--verbose", $_SERVER['argv'])) ? true : false;
$argumentPosition = ($isVerbose) ? 2 : 1;
$isNumberArgumentValid = (($isVerbose && $_SERVER['argc'] == 4) || (!$isVerbose && $_SERVER['argc'] == 3)) ? true : false;

if(!$isNumberArgumentValid) {
    usage();
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
        usage();
        break;
        
}
function usage() {
    echo "\n";
    echo "Usage : php deleteUser.php <verbose> <type of removal> <value>\n";
    echo "<verbose> : optionnal, can be used with -v or --verbose\n";
    echo "<type of removal> : required, can be used with -i, -u or -e (--id, --username or --email)\n";
    echo "<value> : required, value about type of removal (either username, id or email)\n";
}


?>