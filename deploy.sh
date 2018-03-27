#!/usr/bin/env bash

rel_path="/var/www/html"
panel_path="panel" #href reference
alert_token=""
checkout_enabled="yes"

function _alert() {
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

		echo "Doing backup..."
		rm -rf $rel_path/core.bckp
		cp -r $rel_path/core $rel_path/core.bckp
		echo "Done"

		echo "Back up: admin-panel & crons"
		rm -rf $rel_path/cron.bckp
		cp -r $rel_path/core/cron $rel_path/cron.bckp&&
		echo "Downloading new release..."
		rm -rf $rel_path/core.new
		git clone https://github.com/muonium/core $rel_path/core.new

		[[ "$checkout_enabled" == "yes" ]]&&[[ ! -z $b ]]&&
		cd $rel_path/core.new && git checkout $b &>/dev/null && deployed_branch="$b" &&
		echo "Checked out branch: $b"||deployed_branch="_default_"

		echo "Setting up configuration..."
		rm -rf $rel_path/core.new/config&&
		cp -r $rel_path/template/config $rel_path/core.new/.&&
		echo "Deploying new release now..."
		rm -rf $rel_path/core
		mv $rel_path/core.new $rel_path/core
		echo "restoring crons & admin-panel"
		mv $rel_path/cron.bckp $rel_path/core/cron
		echo "Deleting .git ..."
		rm -rf $rel_path/core/.git
		echo "Finished."
	}

	case $1 in
		"panel") panel $2;;
		"rel") rel $2;;
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
