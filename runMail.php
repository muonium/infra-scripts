<?php
define('DEFAULT_LANGUAGE', 'en');
define('DIR_LANGUAGE', ROOT.'/public/translations/');

require_once(ROOT."/config/confMail.php");
require_once(ROOT."/config/confDB.php");
require_once(ROOT."/library/MVC/Mail.php");

use \config as conf;
use \library\MVC as l;

// runMail.php contains the cronMail class
// Scripts are not called here
// This page is included by other files which calls cron class

class cronMail {
	protected static $_sql;
    protected static $txt = []; // Array of languages objects
	private $_mail;

	// Delete inactive users after x days
	private $_inactiveUserDeleteDelay = 180;

	// Send a mail to an inactive user after x days
	private $_inactiveUserMailDelay = 150;

    // Send a mail to an user x days before his subscription ends
	private $_subscriptionEndMailDelay = 7;

	function __construct() {
		self::$_sql = new \PDO('mysql:host='.conf\confDB::host.';dbname='.conf\confDB::db,conf\confDB::user,conf\confDB::password);
		$this->_mail = new l\Mail();
	}

    public static function loadLanguage($lang) {
        if(isset(self::$txt[$lang])) { // Language already loaded
            return $lang;
        } elseif(file_exists(DIR_LANGUAGE.$lang.".json")) {  // Load language if file exists
            $_json = file_get_contents(DIR_LANGUAGE.$lang.".json");
		} elseif($lang === DEFAULT_LANGUAGE) {
			exit('Unable to load DEFAULT_LANGUAGE JSON !');
		} else { // Load default language
			self::loadLanguage(DEFAULT_LANGUAGE);
		}

		$decoded = json_decode($_json);
		if(json_last_error() !== 0) {
			if($lang === DEFAULT_LANGUAGE) {
				exit('Error in the DEFAULT_LANGUAGE JSON !');
			}
			self::loadLanguage(DEFAULT_LANGUAGE);
		}
        self::$txt[$lang] = $decoded;
		return $lang;
	}

    function remindSubscriptionsDaysLeft() {
        // Run everyday
        $req = self::$_sql->prepare("SELECT UP.id_user, US.login, UP.end, US.email, US.lang FROM upgrade UP INNER JOIN users US ON (UP.id_user = US.id) WHERE UP.removed = 0 AND UP.end < ? AND UP.end <> -1");
		$req->execute([time() + $this->_subscriptionEndMailDelay * 86400]);
        while($row = $req->fetch(PDO::FETCH_ASSOC)) {
            $daysLeft = ceil(($row['end'] - time())/86400);
            if($daysLeft == 1 || $daysLeft == 3 || $daysLeft == 7) {
                $lang = ($row['lang'] === null || $row['lang'] == '') ? DEFAULT_LANGUAGE : $row['lang'];
                $lang = self::loadLanguage($lang);
                $this->_mail->_subject = str_replace("[days]", $daysLeft, self::$txt[$lang]->EndSubscriptionMail->subject);
                $this->_mail->_to = $row['email'];
                $this->_mail->_message = str_replace("[login]", $row['login'], str_replace("[days]", $daysLeft, self::$txt[$lang]->EndSubscriptionMail->message));
                $this->_mail->send();
            }
        }
    }

	public function deleteInactiveUsers() {
		// Run every day
		// Query for selecting inactive users to delete
        $cronDeleteUser = new \cronDeleteUser();
		$req = self::$_sql->prepare("SELECT id FROM users WHERE last_connection < ?");
		$req->execute([time() - $this->_inactiveUserDeleteDelay*86400]);

		$i = 0;
		while($row = $req->fetch(PDO::FETCH_ASSOC)) {
			if($cronDeleteUser->deleteUser(intval($row['id']))) $i++;
		}

		// Call the notifier to log the event.
		shell_exec("bash notifier.sh inactive_users --force");

		// Query for selecting inactive users to send a mail
		$req = self::$_sql->prepare("SELECT login, email, lang FROM users WHERE last_connection >= ? AND last_connection <= ?");
		// Select timestamp of the day x days ago today at 00:00:00 and 23:59:59
		$mailDayFirst = strtotime('today midnight', strtotime('-'.$this->_inactiveUserMailDelay.' days'));
		$mailDayLast = strtotime('tomorrow midnight', $mailDayFirst) - 1;

		// The mail will be sent once time, when the user reaches x days of inactivity
		$req->execute([$mailDayFirst, $mailDayLast]);

		while($row = $req->fetch(PDO::FETCH_ASSOC)) {
            $lang = ($row['lang'] === null || $row['lang'] == '') ? DEFAULT_LANGUAGE : $row['lang'];
            $lang = self::loadLanguage($lang);

            // Subject of the mail
            $this->_mail->_subject = self::$txt[$lang]->InactiveUserMail->subject;

			$this->_mail->_to = $row['email'];

            $originalMail = self::$txt[$lang]->InactiveUserMail->message;
            $mailWithLogin = str_replace("[login]", $row['login'], $originalMail);
            $mailWithInactiveDays = str_replace("[inactiveDays]", $this->_inactiveUserMailDelay, $mailWithLogin);
            $mailWithDeleteDelay = str_replace("[deleteDelay]", ($this->_inactiveUserDeleteDelay - $this->_inactiveUserMailDelay), $mailWithInactiveDays);

            $this->_mail->_message = $mailWithDeleteDelay;
            $this->_mail->send();
		}
	}
}
