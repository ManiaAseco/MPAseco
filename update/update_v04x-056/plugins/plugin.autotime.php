<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Auto TimeLimit plugin.
 * Changes Timelimit for TimeAttack dynamically depending on the next
 * map's author time.
 *
 * Original by ck|cyrus
 * Rewrite by Xymph
 *
 * Dependencies: none (but must be after plugin.rasp_jukebox.php in plugins.xml)
 */

Aseco::registerEvent('onSync', 'load_atlconfig');
Aseco::registerEvent('onEndMap', 'autotimelimit');

global $atl_config, $atl_active, $atl_restart;

// load ATL configuration
function load_atlconfig($aseco) {
	global $atl_config, $atl_active, $atl_restart;

	// initialize flags
	$atl_active = false;
	$atl_restart = false;

	// load config file
	$config_file = 'configs/plugins/autotime.xml';
	if (file_exists($config_file)) {
		$aseco->console('Load auto timelimit config [' . $config_file . ']');
		if ($xml = $aseco->xml_parser->parseXml($config_file)) {
			$atl_config = $xml['AUTOTIME'];
			$atl_active = true;
		} else {
			trigger_error('[ATL] Could not read/parse config file ' . $config_file . ' !', E_USER_WARNING);
		}
	} else {
		trigger_error('[ATL] Could not find config file ' . $config_file . ' !', E_USER_WARNING);
	}
}  // load_atlconfig

// called @ onEndMap
function autotimelimit($aseco, $data) {
	global $atl_config, $atl_active, $atl_restart;

	// if not active, bail out immediately
	if (!$atl_active) return;
	// if restarting, bail out immediately
	if ($atl_restart) {
		$atl_restart = false;
		return;
	}

	// get next game settings
	$aseco->client->query('GetNextGameInfo');
	$nextgame = $aseco->client->getResponse();

	// check for TimeAttack on next map
	if ($nextgame['GameMode'] == Gameinfo::TA) {
		// check if auto timelimit enabled
		if ($atl_config['MULTIPLICATOR'][0] > 0) {
			// check if at least one active player on the server
			if (active_player($aseco)) {
				// get next map details
				$map = get_mapinfo($aseco, 1);
				$newtime = intval($map->authortime);
			} else {
				// server already switched so get current map name
				$map = get_mapinfo($aseco, 0);
				$newtime = 0;  // force default
				$newtime = intval($map->authortime);
			}

			// compute new timelimit
			if ($newtime <= 0) {
				$newtime = $atl_config['DEFAULTTIME'][0] * 60 * 1000;
				$tag = 'default';
			} else {
				$newtime *= $atl_config['MULTIPLICATOR'][0];
				$newtime -= ($newtime % 1000);  // round down to seconds
				$tag = 'new';
			}
			// check for min/max times
			if ($newtime < $atl_config['MINTIME'][0] * 60 * 1000) {
				$newtime = $atl_config['MINTIME'][0] * 60 * 1000;
				$tag = 'min';
			} elseif ($newtime > $atl_config['MAXTIME'][0] * 60 * 1000) {
				$newtime = $atl_config['MAXTIME'][0] * 60 * 1000;
				$tag = 'max';
			}

			// set and log timelimit (strip .00 sec)
			$aseco->client->addcall('SetTimeAttackLimit', array($newtime));
			$aseco->console('set {1} timelimit for [{2}]: {3} (Author time: {4})',
			                $tag, stripColors($map->name, false),
			                substr(formatTime($newtime), 0, -3),
			                formatTime($map->authortime));

			// display timelimit (strip .00 sec)
			$message = formatText($atl_config['MESSAGE'][0], $tag,
			                      stripColors($map->name),
			                      substr(formatTime($newtime), 0, -3),
			                      formatTime($map->authortime));
			if ($atl_config['DISPLAY'][0] == 2 && function_exists('send_window_message'))
				send_window_message($aseco, $message, true);
			elseif ($atl_config['DISPLAY'][0] > 0)
				$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
		}
	}
}  // autotimelimit

// get info on current/next map
function get_mapinfo($aseco, $offset) {

	// get current/next map using /nextmap algorithm
	if ($offset == 1)
		$aseco->client->query('GetNextMapIndex');
	else
		$aseco->client->query('GetCurrentMapIndex');
	$trkid = $aseco->client->getResponse();
	$rtn = $aseco->client->query('GetMapList', 1, $trkid);
	$map = $aseco->client->getResponse();

	// get map info
	$rtn = $aseco->client->query('GetMapInfo', $map[0]['FileName']);
	$mapinfo = $aseco->client->getResponse();
	return new Map($mapinfo);
}  // get_mapinfo

// check for at least one active player
function active_player($aseco) {

	$total = 0;
	// check all connected players
	foreach ($aseco->server->players->player_list as $player) {
		// get current player status
		if (!$aseco->isSpectator($player))
			return true;
	}
	return false;
}  // active_player
?>
