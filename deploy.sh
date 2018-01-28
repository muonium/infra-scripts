#!/usr/bin/env bash

rel_path="/var/www/html"
panel_path="panel" #href reference

function _deploy(){
	function panel(){
		rm -rf $rel_path/core/cron/panel
		git clone https://github.com/muonium/admin-panel $rel_path/core/cron/panel

		sed -i "s/href=\"\/panel\"/href=\"\/$panel_path\"/g" \
		$rel_path/core/cron/panel/deployNewVersion.php
	}

	function rel(){
		echo "Back up: admin-panel & crons"
		cp -r $rel_path/core/cron $rel_path/cron.bckp&&
		echo "Downloading new release..."
		rm -rf $rel_path/core.new
		git clone https://github.com/muonium/core $rel_path/core.new&&
		echo "Setting up configuration..."
		rm -rf $rel_path/core.new/config&&
		cp -r $rel_path/template/config $rel_path/core.new/.&&
		echo "Deploying new release now..."
		rm -rf $rel_path/core
		mv $rel_path/core.new $rel_path/core
		echo "restoring crons & admin-panel"
		mv $rel_path/cron.bckp $rel_path/core/cron
		echo "Deleting `.git` ..."
		rm -rf $rel_path/core/.git
	}

	local k=$1
	case $1 in
		"panel") echo "Not available yet.";;
		"rel") rel;;
	esac
}

k=$1
case $1 in
	"--panel") _deploy "panel";;
	"--release") _deploy "rel";;
esac
