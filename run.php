<?php
define('ROOT', dirname(__DIR__));
define('NOVA', dirname(dirname(__DIR__)).'/nova');
define('DEFAULT_LANGUAGE', 'en');
define('DIR_LANGUAGE', ROOT.'/public/translations/');
use \config as conf;
use \library\MVC as l;

require_once(ROOT."/config/confDB.php");
require_once(ROOT."/config/confMail.php");
//require_once(ROOT."/config/confRedis.php");
require_once(ROOT."/library/MVC/Mail.php");

//require_once(ROOT."/vendor/autoload.php");

// run.php contains the cron class
// Scripts are not called here
// This page is included by other files which calls cron class

class cron {
	protected static $_sql;
    protected static $txt = null;
	private $_mail;

	// Delete inactive users after x days
	private $_inactiveUserDeleteDelay = 180;

	// Send a mail to an inactive user after x days
	private $_inactiveUserMailDelay = 150;

    // Send a mail to an user x days before his subscription ends
	private $_subscriptionEndMailDelay = 7;
	private static $userLanguage;
    
	// Redis
	private $redis;
	private $exp = 1200;

	function __construct() {
		self::$_sql = new \PDO('mysql:host='.conf\confDB::host.';dbname='.conf\confDB::db,conf\confDB::user,conf\confDB::password);
		$this->_mail = new l\Mail();
		$this->redis = new \Predis\Client(conf\confRedis::parameters, conf\confRedis::options);
	}

    function remindSubscriptionsDaysLeft() {
        //Run everyday
        
        $req = self::$_sql->prepare("SELECT UP.id_user, US.login, UP.end, US.email, US.language FROM upgrade UP INNER JOIN users US ON (UP.id_user = US.id) WHERE UP.removed = 0 AND UP.end < ? AND UP.end <> -1");
		$req->execute([time() + $this->_subscriptionEndMailDelay * 86400]);
        while($row = $req->fetch(PDO::FETCH_ASSOC)) {
            $daysLeft = ceil(($row['end'] - time())/86400);
            $lang = ($row['language'] === NULL) ? DEFAULT_LANGUAGE : $row['language'];
            self::loadLanguage($lang);
            $this->_mail->_subject = str_replace("[days]", $daysLeft, self::$txt->EndSubscriptionMail->subject);
			$this->_mail->_to = $row['email'];
            $this->_mail->_message = str_replace("[login]", $row['login'], str_replace("[days]", $daysLeft, self::$txt->EndSubscriptionMail->message));
            $this->_mail->send();
        }
    }
    
    public static function loadLanguage($lang) {
		if(file_exists(DIR_LANGUAGE.$lang.".json")) {
            $_json = file_get_contents(DIR_LANGUAGE.$lang.".json");
			self::$userLanguage = $lang;
		} elseif($lang === DIR_LANGUAGE) {
			exit('Unable to load DEFAULT_LANGUAGE JSON !');
		} else {
            $_json = file_get_contents(DIR_LANGUAGE.DEFAULT_LANGUAGE.".json");
			self::$userLanguage = DEFAULT_LANGUAGE;
			self::loadLanguage(DEFAULT_LANGUAGE);
		}

		self::$txt = json_decode($_json);
		if(json_last_error() !== 0) {
			if($lang === DEFAULT_LANGUAGE) {
				exit('Error in the DEFAULT_LANGUAGE JSON !');
			}
			self::loadLanguage(DEFAULT_LANGUAGE);
		}
		return true;
	}
    
	public function deleteInactiveUsers() {
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

	private function getFullPath($folder_id, $user_id) {
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

	public function deleteNotCompletedFiles() {
		$req = self::$_sql->prepare("SELECT id, id_owner, folder_id, name FROM files WHERE size = -1 AND expires <= ?");
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

	public function updateUpgrades() {
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

	public function cleanRedis() {
		// Clean Redis DB by removing expired data
		//echo $this->redis->dbsize();
		$max_iat = time() - $this->exp;
		$keys = $this->redis->keys('token:*:iat');
		// Loop over tokens and search old tokens (iat < max_iat)
		foreach($keys as $key) {
			$iat = $this->redis->get($key);
			if($iat < $max_iat) { // Expired
				$s = substr($key, 0, -4);
				$jti = substr($s, 6);
				$uid = $this->redis->get($s.':uid');

				$k = $this->redis->keys($s.'*'); // Remove token
				foreach($k as $v) {
					$this->redis->del($v);
				}

				if($uid !== null) {
					if($uidTokens = $this->redis->get('uid:'.$uid)) { // Remove token from user tokens list
						$uidTokens = str_replace($jti.';', '', $uidTokens);
						if(strlen($uidTokens) > 0) {
							$this->redis->set('uid:'.$uid, $uidTokens);
						} else {
							$this->redis->del('uid:'.$uid);
						}
					}

					$k = $this->redis->keys('uid:'.$uid.':mailnbf*'); // Remove mailnbf if expired
					foreach($k as $v) {
						$nbf = $this->redis->get($v);
						if(is_numeric($nbf) && $nbf <= time()) {
							$this->redis->del($v);
						}
					}
				}
			}
		}
		// Remove data about shared files (it contains only paths in order to improve performances)
		$keys = $this->redis->keys('shared:*');
		foreach($keys as $key) {
			$this->redis->del($key);
		}
		//echo $this->redis->dbsize();
	}
}
