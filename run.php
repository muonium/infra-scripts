<?php
define('ROOT', dirname(__DIR__));
define('NOVA', dirname(dirname(__DIR__)).'/nova');
use \config as conf;
use \library\MVC as l;

require_once("../config/confDB.php");
require_once("../config/confMail.php");
require_once("../library/MVC/Mail.php");

// run.php contains the cron class
// Scripts are not called here
// This page is included by other files which calls cron class

class cron {
	protected static $_sql;
	private $_mail;

	// Delete inactive users after x days
	private $_inactiveUserDeleteDelay = 180;

	// Send a mail to an inactive user after x days
	private $_inactiveUserMailDelay = 150;

	function __construct() {
		self::$_sql = new \PDO('mysql:host='.conf\confDB::host.';dbname='.conf\confDB::db,conf\confDB::user,conf\confDB::password);
		$this->_mail = new l\Mail();
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
    
	function deleteInactiveUsers() {
		// Run every day
		// Query for selecting inactive users to delete
		$req = self::$_sql->prepare("SELECT id FROM users WHERE last_connection < ?");
		$req->execute([time() - $this->_inactiveUserDeleteDelay*86400]);

		$i = 0;
		while($row = $req->fetch(PDO::FETCH_ASSOC)) {
			if($this->deleteUser(intval($row['id']))) $i++;
		}

		// Call the notifier to log the event.
		shell_exec("bash notifier.sh inactive_users --force");

		// Query for selecting inactive users to send a mail
		$req = self::$_sql->prepare("SELECT login, email FROM users WHERE last_connection >= ? AND last_connection <= ?");

		// Select timestamp of the day x days ago today at 00:00:00 and 23:59:59
		$mailDayFirst = strtotime('today midnight', strtotime('-'.$this->_inactiveUserMailDelay.' days'));
		$mailDayLast = strtotime('tomorrow midnight', $mailDayFirst) - 1;

		// The mail will be sent once time, when the user reaches x days of inactivity
		$req->execute([$mailDayFirst, $mailDayLast]);

		// Subject of the mail
		$this->_mail->_subject = "Muonium - You are inactive";

		$i = 0;
		while($row = $req->fetch(PDO::FETCH_ASSOC)) {
			$this->_mail->_to = $row['email'];
			$this->_mail->_message = "Hi ".$row['login'].",<br>This email is sent because you are inactive for
				".$this->_inactiveUserMailDelay." days.<br>Your account will be deleted in
				".($this->_inactiveUserDeleteDelay - $this->_inactiveUserMailDelay)." days if you don't log in<br>
				Muonium Team
			";
			if($this->_mail->send()) $i++;
		}
	}

	function getFullPath($folder_id, $user_id) {
		if(!is_numeric($folder_id)) return false;
		elseif($folder_id != 0) {
			$req = self::$_sql->prepare("SELECT `path`, name FROM folders WHERE id_owner = ? AND id = ?");
			$ret = $req->execute([$user_id, $folder_id]);
			if($ret) {
				$res = $req->fetch();
				return $res['0'].$res['1'];
			}
			return false;
		}
		return '';
	}

	function deleteNotCompletedFiles() {
		$req = self::$_sql->prepare("SELECT id_owner, folder_id, name FROM files WHERE size = -1 AND expires <= ?");
		$req->execute(array(time()));
		$res = $req->fetchAll(\PDO::FETCH_ASSOC);

		foreach($res as $file) {
			$path = $this->getFullPath($file['folder_id'], $file['id_owner']);
			if($path === false) continue;
			if($path != '') $path = $path.'/';
			$size = @filesize(NOVA.'/'.$file['id_owner'].'/'.$path.$file['name']);
			//echo 'found '.NOVA.'/'.$file['id_owner'].'/'.$path.$file['name'].' size : '.$size.'<br>';
			if(file_exists(NOVA.'/'.$file['id_owner'].'/'.$path.$file['name']) && is_numeric($size)) {
				//echo 'deleted '.NOVA.'/'.$file['id_owner'].'/'.$path.$file['name'].'<br>';
				unlink(NOVA.'/'.$file['id_owner'].'/'.$path.$file['name']);
				// update size stored
				$req = self::$_sql->prepare("UPDATE storage SET size_stored = size_stored-? WHERE id_user = ?");
				$req->execute([$size, $file['id_owner']]);
			}
		}

		$req = self::$_sql->prepare("DELETE FROM files WHERE size = -1 AND expires <= ?");
		$req->execute([time()]);
	}

	function updateUpgrades() {
		// Remove upgrades from user storage quota when date is expired but keep them in DB in order to show history
		$time = time();
		$req = self::$_sql->prepare("SELECT id_user, size FROM upgrade WHERE `end` <= ? AND `end` >= 0 AND removed = 0");
		$req->execute([$time]);
		$res = $req->fetchAll(\PDO::FETCH_ASSOC);

		foreach($res as $up) {
			$req = self::$_sql->prepare("UPDATE storage SET user_quota = user_quota-? WHERE id_user = ?");
			$req->execute([$up['size'], $up['id_user']]);
		}
		$req = self::$_sql->prepare("UPDATE upgrade SET removed = 1 WHERE `end` <= ? AND `end` >= 0 AND removed = 0");
		$req->execute([$time]);
	}
    
    //LIST LAST USERS
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
    //END LIST LAST USERS
    
    //DELETE USER
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
    
    function deleteByUsername($anUsername, $isVerbose) {
        $id = self::getIDbyUsername($anUsername);
        self::deleteByID($id, $isVerbose);
    }

    function deleteByEmail($anEmail, $isVerbose) {
        $id = self::getIDbyEmail($anEmail);
        self::deleteByID($id, $isVerbose);
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
    
    function deleteByID($anID, $isVerbose) {
        $infos = self::getInfos($anID);
        if($infos === false) {
            echo "User not found in database.\n";
        } else {
            if($isVerbose) {
                echo 'User (ID : '.$infos["id"].', username : '.$infos["login"].', email : '.$infos["email"].") deleted\n";
            } else {
                echo "1 user deleted.\n";
            }
            self::deleteUser($anID);
        }
    }
    //END DELETE USER
};
?>
