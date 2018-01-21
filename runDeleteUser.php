<?php
define('ROOT', dirname(__DIR__));
define('NOVA', dirname(dirname(__DIR__)).'/nova');
use \config as conf;

require_once(ROOT."/config/confDB.php");
require_once(ROOT."/config/confMail.php");
require_once(ROOT."/library/MVC/Mail.php");

class cronDeleteUser {
    
    protected static $_sql;

    function __construct() {
        self::$_sql = new \PDO('mysql:host='.conf\confDB::host.';dbname='.conf\confDB::db,conf\confDB::user,conf\confDB::password);
    }

    function deleteFolder($userDir) {
        if(!is_dir($userDir)) {
            echo "Error : $userDir must be a valid directory.";
            exit;
        }
        if(substr($userDir, strlen($userDir) - 1, 1) != '/') {
            $userDir .= '/';
        }
        $files = glob($userDir . '*', GLOB_MARK);
        foreach ($files as $file) {
            if(is_dir($file)) {
                self::deleteFolder($file);
            } else {
                unlink($file);
            }
        }
        rmdir($userDir);
    }

    function deleteUser($id_user) {
        if(!is_numeric($id_user)) return false;

        $req = self::$_sql->prepare("DELETE FROM users WHERE id = ?");
        $req->execute([$id_user]);
        $req = self::$_sql->prepare("DELETE FROM user_lostpass WHERE id_user = ?");
        $req->execute([$id_user]);
        $req = self::$_sql->prepare("DELETE FROM user_validation WHERE id_user = ?");
        $req->execute([$id_user]);
        $req = self::$_sql->prepare("DELETE FROM ban WHERE id_user = ?");
        $req->execute([$id_user]);
        $req = self::$_sql->prepare("DELETE FROM files WHERE id_owner = ?");
        $req->execute([$id_user]);
        $req = self::$_sql->prepare("DELETE FROM storage WHERE id_user = ?");
        $req->execute([$id_user]);
        $req = self::$_sql->prepare("DELETE FROM folders WHERE id_owner = ?");
        $req->execute([$id_user]);
        $req = self::$_sql->prepare("DELETE FROM upgrade WHERE id_user = ?");
        $req->execute([$id_user]);

        self::deleteFolder(NOVA.'/'.$id_user);

        return true;
    }

    function getIDbyUsername($anUsername) {
        $theRequest = self::$_sql->prepare('SELECT id FROM users WHERE login = :login');
        $theRequest->bindParam(':login', $anUsername, PDO::PARAM_STR);
        $theRequest->execute();
        $id = $theRequest->fetch();
        return $id['id'];
    }

    function getIDbyEmail($anEmail) {
        $theRequest = self::$_sql->prepare('SELECT id FROM users WHERE email = :email');
        $theRequest->bindParam(':email', $anEmail, PDO::PARAM_STR);
        $theRequest->execute();
        $id = $theRequest->fetch();
        return $id['id'];
    }

    function getMailFromID($anID) {
        $theRequest = self::$_sql->prepare('SELECT email FROM users WHERE id = :id');
        $theRequest->bindParam(':id', $anID, PDO::PARAM_INT);
        $theRequest->execute();
        $mail = $theRequest->fetch();
        return $mail;
    }

    function getUsernameFromID($anID) {
        $theRequest = self::$_sql->prepare('SELECT login FROM users WHERE id = :id');
        $theRequest->bindParam(':id', $anID, PDO::PARAM_INT);
        $theRequest->execute();
        $username = $theRequest->fetch();
        return $username;
    }

    function deleteByUsername($anUsername) {
        $id = self::getIDbyUsername($anUsername);
        self::deleteByID($id);
    }

    function deleteByEmail($anEmail) {
        $id = self::getIDbyEmail($anEmail);
        self::deleteByID($id);
    }

    function getInfos($anID) {
        $theRequest = self::$_sql->prepare('SELECT * FROM users WHERE id = :id');
        $theRequest->bindParam(':id', $anID, PDO::PARAM_INT);
        $theRequest->execute();
        $infos = $theRequest->fetchAll(\PDO::FETCH_ASSOC);
        if(isset($infos[0])) {
            return $infos[0];
        }
        else {
            return false;
        }
    }

    function deleteByID($anID) {
        $infos = self::getInfos($anID);
        if($infos === false) {
            echo "User not found in database.\n";
        } else {
            echo 'User (ID : '.$infos["id"].', username : '.$infos["login"].', email : '.$infos["email"].") deleted\n";
            self::deleteUser($anID);
        }
    }
}

?>