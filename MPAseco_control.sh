#!/bin/bash
# MPAseco_control.sh is a bash-script which can start, stop, restart and check the status of MPAseco.
# MPAseco (ManiaPlanet Automatic Server Controller) is a server controller for ManiaPlanet servers (ShootMania/QuestMania).
# Usage: ./MPAseco_control.sh [start] [stop] [restart] [status]

cd /home/shootmania/MPAseco
if [ "$1" = "start" ]; then
	if [ -f mpaseco.pid ]; then
		PID=$(cat mpaseco.pid)
		if [ -d /proc/${PID} ]; then
			echo 'MPAseco is already running!'
		else
			echo 'MPAseco not running, but .pid file exists.'
			echo 'Removing .pid file and starting MPAseco ...'
			rm mpaseco.pid
			php mpaseco.php TM2C </dev/null >mpaseco.log 2>&1 &
			touch mpaseco.pid
			echo $! > mpaseco.pid
			echo 'MPAseco started with pid: '$(cat mpaseco.pid)
		fi
	else
		php mpaseco.php TM2C </dev/null >mpaseco.log 2>&1 &
		touch mpaseco.pid
		echo $! > mpaseco.pid
		echo 'MPAseco started with pid: '$(cat mpaseco.pid)
	fi
elif [ "$1" = "stop" ]; then
	if [ -f mpaseco.pid ]; then
		PID=$(cat mpaseco.pid)
		if [ -d /proc/${PID} ]; then
			echo 'Stopping MPAseco ...'
			kill ${PID}
			echo 'Removing .pid file ...'
			rm mpaseco.pid
			echo 'Success!'
		else
			echo 'MPAseco is already stopped, removing .pid file.'
			rm mpaseco.pid
		fi
	else
		echo 'MPAseco is already stopped!'
	fi
elif [ "$1" = "restart" ]; then
	if [ -f mpaseco.pid ]; then
		PID=$(cat mpaseco.pid)
		if [ -d /proc/${PID} ]; then
			echo 'Stopping MPAseco ...'
			kill ${PID}
			rm mpaseco.pid
			echo 'Starting MPAseco ...'
			php mpaseco.php TM2C </dev/null >mpaseco.log 2>&1 &
			touch mpaseco.pid
			echo $! > mpaseco.pid
			echo 'MPAseco restarted with pid: '$(cat mpaseco.pid)
		else
			echo 'MPAseco has crashed or is stopped incorrect.'
		fi
	else
		echo 'MPAseco is currently stopped.'
	fi
elif [ "$1" = "status" ]; then
	if [ -f mpaseco.pid ]; then
		PID=$(cat mpaseco.pid)
		if [ -d /proc/${PID} ]; then
			echo 'MPAseco is currently running with pid:' ${PID}'.'
		else
			echo 'MPAseco has crashed or is stopped incorrect.'
		fi
	else
		echo 'MPAseco is currently stopped.'
	fi
else
	echo 'MPAseco_control.sh is a bash-script which can start, stop, restart and check the status of MPAseco.'
	echo 'MPAseco (ManiaPlanet Automatic Server Controller) is a server controller for ManiaPlanet servers (ShootMania/QuestMania).'
	echo 'Usage: ./MPAseco_control.sh [start] [stop] [restart] [status]'
	echo '[start]   : Start MPAseco'
	echo '[stop]    : Stop MPAseco'
	echo '[restart] : Restart (stop & start) MPAseco'
	echo '[status]  : Check if MPAseco is running'
fi
