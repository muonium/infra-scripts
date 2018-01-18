#!/bin/bash

song="/home/illoxx/Musiques/Occidental/the_do_keep_your_lips_sealed.mp3"

if ! curl --connect-timeout 30 http://mui.cloud &>/dev/null;then
	vlc $song&
	exit 0;
fi
