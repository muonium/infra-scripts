<?php
require_once("runDeleteUser.php");

$task = new cronDeleteUser();

if($_SERVER['argc'] != 3) {
    usage();
    exit;
}

switch($_SERVER['argv'][1]) {
            
    case '-u':
    case '--username':
        $usernameValue = $_SERVER['argv'][2];
        $task->deleteByUsername($usernameValue);
        break;
        
    case '-e':
    case '--email':
        $emailValue = $_SERVER['argv'][2];
        $task->deleteByEmail($emailValue);
        break;
        
    case '-i':
    case '--id':
        $IDvalue = $_SERVER['argv'][2];
        $task->deleteByID($IDvalue);
        break;
        
    default:
        usage();
        break;
        
}

function usage() {
    echo "\n";
    echo "Usage : php deleteUser.php <type of removal> <value>\n";
    echo "<type of removal> : required, can be used with -i, -u or -e (--id, --username or --email)\n";
    echo "<value> : required, value about type of removal (either username, id or email)\n";
}


?>