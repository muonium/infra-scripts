#!/usr/bin/env bash

rel_path="/var/www/html" # path where is located muonium on the server
panel_path="panel" #href reference
alert_token="" # rocket.chat instance token
alerts_enabled="yes" # use rocketchat.py provided along with the code, notifications on a rocket.chat instance
checkout_enabled="yes" # disable or enable alerts
composer_bin="" # composer executable location (example: /usr/bin/composer)

function _alert() {

	# check if the alerts are or not disabled
	if [[ ! "$checkout_enabled" == "yes" ]]; then
		return 0;
	fi

	local serviceState=$1
	local serviceOutput=$2
	$rel_path/core/cron/rocketchat.py --url $alert_token \
	--hostalias $HOSTNAME --notificationtype "RECOVERY" \
	--servicestate $serviceState --serviceoutput "$serviceOutput"
}

function _rollback(){
	function panel(){
		echo "Deleting current release..."
		rm -rf $rel_path/core/cron/panel
		echo "Restoring backup..."
		cp -r $rel_path/panel.bckp $rel_path/core/cron/panel
		echo "Done"
	}

	function rel(){
		echo "Deleting current release..."
		rm -rf $rel_path/core
		echo "Restoring backup..."
		cp -r $rel_path/core.bckp $rel_path/core
		echo "Done"
	}

	case $1 in
		"panel") panel;;
		"rel") rel;;
	esac

}

function _deploy(){
	function panel(){
		local b=$1

		# backup the current panel
		echo "Doing backup..."
		rm -rf $rel_path/panel.bckp
		cp -r $rel_path/core/cron/panel $rel_path/panel.bckp
		echo "Bakup :: DONE"

		echo "Backup protected users config file..."
		cp $rel_path/core/cron/panel/accountsProtected.json $rel_path/.
		rm -rf $rel_path/core/cron/panel.new
		git clone https://github.com/muonium/admin-panel $rel_path/core/cron/panel.new

		echo "Backup logins file..."
		cp $rel_path/core/cron/panel/includes/logins $rel_path/.

		[[ "$checkout_enabled" == "yes" ]]&&[[ ! -z $b ]] && cd $rel_path/core/cron/panel.new &&
		git checkout $b &>/dev/null && deployed_branch="$b" &&
		echo "Checked out branch: $b"||deployed_branch="_default_"
		rm -rf $rel_path/core/cron/panel && mv $rel_path/core/cron/panel.new \
		$rel_path/core/cron/panel

		sed -i "s/href=\"\/panel\"/href=\"\/$panel_path\"/g" \
		$rel_path/core/cron/panel/deployNewVersion.php
		sed -i "s/href=\"\/panel\"/href=\"\/$panel_path\"/g" \
		$rel_path/core/cron/panel/updatePanel.php

		echo "Restoring protected users config file..."
		cp $rel_path/accountsProtected.json $rel_path/core/cron/panel/accountsProtected.json
		rm -f $rel_path/accountsProtected.json

		echo "Restoring logins file..."
		mv $rel_path/logins $rel_path/core/cron/panel/includes/.

		echo "Finished."
	}

	function rel(){
		local b=$1

		if [[ ! -d "$rel_path/core" ]]; then
			echo "Downloading release from Git..."
			git clone https://github.com/muonium/server $rel_path/core

			echo "Applying configuration files"
			rm -rf $rel_path/core/config
			cp -r $rel_path/template/config $rel_path/core/.

			echo "Composer install"
			cd $rel_path/core && $composer_bin install

			echo "Installing translations files"
			mkdir -p $rel_path/core/public
			cd $rel_path/core/public
			git clone https://github.com/muonium/translations t
			mv t/webclient translations
			rm -rf t

			echo "Done"
			return 0;
		fi

		[[ -z "$b" ]]&&b="master" #if no branch got provided, use master as default

		echo "Doing backup..."
		rm -rf $rel_path/core.bckp
		cp -r $rel_path/core $rel_path/core.bckp
		echo "Done"

		echo "Pulling new release..."
		cd $rel_path/core && git pull

		[[ "$checkout_enabled" == "yes" ]]&&[[ ! -z $b ]]&&
		cd $rel_path/core && git checkout $b &>/dev/null && deployed_branch="$b" &&
		echo "Checked out branch: $b"&&git pull||deployed_branch="_default_"

		echo "Composer install..."
		cd $rel_path/core && $composer_bin install

		echo "Installing translations"
		cd $rel_path/core/public
		git clone https://github.com/muonium/translations t
		rm -rf translations && mv t/webclient translations
		rm -rf t

		echo "Done"
	}

	function webclient(){
		local b=$1

		if [[ ! -d "$rel_path/app" ]]; then
			git clone https://github.com/muonium/webclient-build $rel_path/app
		fi

		echo "Doing backup..."
		rm -rf $rel_path/app.bckp
		cp -r $rel_path/app $rel_path/app.bckp
		echo "Done."

		echo "Pulling new release..."
		cd $rel_path/app && git pull

		[[ "$checkout_enabled" == "yes" ]]&&[[ ! -z $b ]]&&
		cd $rel_path/app && git checkout $b &>/dev/null && deployed_branch="$b" &&
		echo "Checked out branch: $b"&&git pull||deployed_branch="_default_"

	}

	case $1 in
		"panel") panel $2;;
		"rel") rel $2;;
		"webclient") webclient $2;
	esac
}

k=$1
case $1 in
	"--panel")
		echo "$(date) :: panel update" >> $rel_path/log.txt
		_alert "OK" "Deploying new panel release..."
		_deploy "panel" $2 &&
		_alert "OK" "New panel release deployed.
		Branch: $deployed_branch"||
		_alert "CRITICAL" "Error while deploying new panel release."
		;;

	"--release")
		echo "$(date) :: release update" >> $rel_path/log.txt
		_alert "OK" "Deploying new core release..."
		_deploy "rel" $2 &&
		_alert "OK" "New release deployed.
		Branch: $deployed_branch"||
		_alert "CRITICAL" "Error while deploying new release."
		;;

	"--webclient")
		echo "$(date) :: webclient update" >> $rel_path/log.txt
		_alert "OK" "Deploying new webclient release..."
		_deploy "webclient" $2&&
		_alert "OK" "New release deployed (webclient)
		Branch: $deployed_branch"||
		_alert "CRITICAL" "Error while deploying (webclient)."
		;;

	"--release-rollback")
		echo "$(date) :: release rollback" >> $rel_path/log.txt
		_alert "OK" "Doing backup [release] ..."
		_rollback $2
		_alert "OK" "Done! [release]"
		;;

	"--panel-rollback")
		echo "$(date) :: panel rollback" >> $rel_path/log.txt
		_alert "OK" "Doing backup [panel] ..."
		_rollback $2
		_alert "OK" "Done! [panel]"
		;;
esac
