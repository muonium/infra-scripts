<?php
define('ROOT', dirname(__DIR__));
define('NOVA', dirname(dirname(__DIR__)).'/nova');
use \config as conf;

require_once("../config/confDB.php");
require_once("../config/confMail.php");
require_once("../library/MVC/Mail.php");
class cronListLastUser {
    
    protected static $_sql;

    function __construct() {
        self::$_sql = new \PDO('mysql:host='.conf\confDB::host.';dbname='.conf\confDB::db,conf\confDB::user,conf\confDB::password);
    }

    function listMostRecentLoggedInUsers($anNumberOfLastUsers) {
        // List X most recent users (sort by date of login)
        $anNumberOfLastUsers = intval($anNumberOfLastUsers);
        $req = self::$_sql->prepare("SELECT id, login, email FROM users ORDER BY last_connection DESC LIMIT 0, :limit");
        $req->bindValue(':limit', $anNumberOfLastUsers, PDO::PARAM_INT);
        $req->execute();
        while($row = $req->fetch()) {
            echo "ID : ".$row['id'].", login : ".$row['login'].", email : ".$row['email']."\n";
        }  
    }
}

?>