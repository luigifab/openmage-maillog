#!/bin/sh
#
# Created D/23/09/2018
# Updated S/30/07/2022
#
# Copyright 2015-2024 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
# Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
# Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
# Copyright 2020-2023 | Fabrice Creuzot <fabrice~cellublue~com>
# https://github.com/luigifab/openmage-maillog
#
# This program is free software, you can redistribute it or modify
# it under the terms of the GNU General Public License (GPL) as published
# by the free software foundation, either version 2 of the license, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but without any warranty, without even the implied warranty of
# merchantability or fitness for a particular purpose. See the
# GNU General Public License (GPL) for more details.

BASEDIR=`echo $0 | sed 's/maillog\.sh//g'`
SCRIPT=maillog.php
LOGFILE=maillog.log
LOGDIR="var/log/"
PHPBIN=`which php`

DEV=""
ONLYSYNC=""
ONLYEMAIL=""
MULTI=""
HELP=""

if [ ! -d "$BASEDIR$LOGDIR" ] ; then
	mkdir -p "$BASEDIR$LOGDIR"
fi

for ARG in $*
do
	if [[ $ARG == "--dev" ]] ; then
		DEV=" --dev"
	elif [[ $ARG == "--only-sync" ]] ; then
		ONLYSYNC=" --only-sync"
	elif [[ $ARG == "--only-email" ]] ; then
		ONLYEMAIL=" --only-email"
	elif [[ $ARG == "--multi" ]] ; then
		MULTI=" --multi"
	elif [[ $ARG == "--help" ]] ; then
		HELP=" --help"
	elif [[ $ARG == "-h" ]] ; then
		HELP=" --help"
	else
		echo "unknown argument: $ARG" >> $BASEDIR$LOGDIR$LOGFILE
	fi
done

if ! ps auxwww | grep "$BASEDIR$SCRIPT$DEV$ONLYSYNC$ONLYEMAIL" | grep -v grep 1>/dev/null 2>/dev/null ; then
	$PHPBIN $BASEDIR$SCRIPT$DEV$ONLYSYNC$ONLYEMAIL$MULTI$HELP >> $BASEDIR$LOGDIR$LOGFILE 2>&1 &
fi