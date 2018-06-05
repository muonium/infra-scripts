<?php
define('ROOT', dirname(__DIR__));
define('NOVA', dirname(dirname(__DIR__)).'/nova');

use \config as conf;

require_once(ROOT."/config/confDB.php");
require_once(ROOT."/config/confRedis.php");

require_once(ROOT."/vendor/autoload.php");

// run.php contains the cron class
// Scripts are not called here
// This page is included by other files which calls cron class

class cron {
	protected static $_sql;

	// Redis
	private $redis;
	private $exp = 1200;

	function __construct() {
		self::$_sql = new \PDO('mysql:host='.conf\confDB::host.';dbname='.conf\confDB::db,conf\confDB::user,conf\confDB::password);
		$this->redis = new \Predis\Client(conf\confRedis::parameters, conf\confRedis::options);
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
                    $this->redis->del('uid:'.$uid.':ga');
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
