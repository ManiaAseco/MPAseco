<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Map plugin.
 * Times playing time of a map, and provides map & time info.
 * Created by Xymph
 *
 * Dependencies: used by plugin.rasp_jukebox.php, plugin.rasp_votes.php
 */

Aseco::registerEvent('onBeginMap', 'time_gameinfo');
Aseco::registerEvent('onBeginMap2', 'time_newmap');  // use 2nd event to start timer just before racing commences
Aseco::registerEvent('onEndMap', 'time_endmap');
Aseco::registerEvent('onSync', 'time_initreplays');

Aseco::addChatCommand('map', 'Shows info about the current map');
Aseco::addChatCommand('playtime', 'Shows time current map has been playing');
Aseco::addChatCommand('time', 'Shows current server time & date');

function chat_map($aseco, $command) {

	$name = stripColors($aseco->server->map->name);
	if (isset($aseco->server->map->mx->error) && $aseco->server->map->mx->error == '')
		$name = '$l[http://' . $aseco->server->map->mx->prefix .
		        '.mania-exchange.com/tracks/view/' .
		        $aseco->server->map->mx->id . ']' . $name . '$l';

	// check for Stunts mode
	if ($aseco->server->gameinfo->mode != Gameinfo::STNT) {
		$message = formatText($aseco->getChatMessage('MAP'),
		                      $name, $aseco->server->map->author,
		                      formatTime($aseco->server->map->authortime),
		                      formatTime($aseco->server->map->goldtime),
		                      formatTime($aseco->server->map->silvertime),
		                      formatTime($aseco->server->map->bronzetime),
		                      $aseco->server->map->copperprice);
	} else {  // Stunts mode
		$message = formatText($aseco->getChatMessage('MAP'),
		                      $name, $aseco->server->map->author,
		                      $aseco->server->map->gbx->authorScore,
		                      $aseco->server->map->goldtime,
		                      $aseco->server->map->silvertime,
		                      $aseco->server->map->bronzetime,
		                      $aseco->server->map->copperprice);
	}
	// $message .= LF . ' {#server}FileName: {#highlite}' . $aseco->server->map->filename;
	// show chat message
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
}  // chat_map

// called @ onBeginMap
function time_gameinfo($aseco, $map) {

	// check for divider message
	if ($aseco->settings['show_curmap'] > 0) {
		$name = stripColors($map->name);
		if (isset($map->mx->error) && $map->mx->error == '')
			$name = '$l[http://' . $map->mx->prefix .
			        '.mania-exchange.com/tracks/view/' .
			        $map->mx->id . ']' . $name . '$l';

		// compile message
		$message = formatText($aseco->getChatMessage('CURRENT_MAP'),
		                      $name, $map->author,
		                      ($aseco->server->gameinfo->mode == Gameinfo::STNT ?
		                       $map->gbx->authorScore :
		                       formatTime($map->authortime)));

		// show chat message
		if ($aseco->settings['show_curmap'] == 2 && function_exists('send_window_message'))
			send_window_message($aseco, $message, false);
		else
			$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
	}
}  // time_gameinfo

// called @ onSync
function time_initreplays($aseco, $data) {
	global $replays_counter, $replays_total;

	$replays_counter = 0;
	$replays_total = 0;
	$aseco->server->starttime = time();
}  // time_init

// called @ onBeginMap2
function time_newmap($aseco, $data) {
	global $replays_total;

	// remember time this map starts playing
	$aseco->server->map->starttime = time();
	if ($replays_total == 0)
		$aseco->server->starttime = time();
}  // time_newmap

function time_playing($aseco) {

	// return map playing time
	return (time() - $aseco->server->map->starttime);
}  // time_playing

// called @ onEndMap
function time_endmap($aseco, $data) {
	global $replays_total;

	// skip if TimeAttack/Stunts mode (always same playing time),
	// or if disabled
	if ($aseco->settings['show_playtime'] == 0 ||
	    $aseco->server->gameinfo->mode == Gameinfo::TA ||
	    $aseco->server->gameinfo->mode == Gameinfo::STNT)
		return;

	$name = stripColors($aseco->server->map->name);
	if (isset($aseco->server->map->mx->error) && $aseco->server->map->mx->error == '')
		$name = '$l[http://' . $aseco->server->map->mx->prefix .
		        '.mania-exchange.com/tracks/view/' .
		        $aseco->server->map->mx->id . ']' . $name . '$l';

	// compute map playing time
	$playtime = time() - $aseco->server->map->starttime;
	$playtime = formatTimeH($playtime * 1000, false);
	$totaltime = time() - $aseco->server->starttime;
	$totaltime = formatTimeH($totaltime * 1000, false);

	// show chat message
	$message = formatText($aseco->getChatMessage('PLAYTIME_FINISH'),
	                      $name, $playtime);
	if ($replays_total > 0)
		$message .= formatText($aseco->getChatMessage('PLAYTIME_REPLAY'),
		                       $replays_total, ($replays_total == 1 ? '' : 's'), $totaltime);

	if ($aseco->settings['show_playtime'] == 2 && function_exists('send_window_message'))
		send_window_message($aseco, $message, false);
	else
		$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));

	// log console message
	if ($replays_total == 0)
		$aseco->console('map [{1}] finished after: {2}',
		                stripColors($aseco->server->map->name, false), $playtime);
	else
		$aseco->console('map [{1}] finished after: {2} ({3} replay{4}, total: {5})',
		                stripColors($aseco->server->map->name, false), $playtime,
		                $replays_total, ($replays_total == 1 ? '' : 's'), $totaltime);
}  // time_endmap

function chat_playtime($aseco, $command) {
	global $replays_total;

	$name = stripColors($aseco->server->map->name);
	if (isset($aseco->server->map->mx->error) && $aseco->server->map->mx->error == '')
		$name = '$l[http://' . $aseco->server->map->mx->prefix .
		        '.mania-exchange.com/tracks/view/' .
		        $aseco->server->map->mx->id . ']' . $name . '$l';

	// compute map playing time
	$playtime = time() - $aseco->server->map->starttime;
	$totaltime = time() - $aseco->server->starttime;

	// show chat message
	$message = formatText($aseco->getChatMessage('PLAYTIME'),
	                      $name, formatTimeH($playtime * 1000, false));
	if ($replays_total > 0)
		$message .= formatText($aseco->getChatMessage('PLAYTIME_REPLAY'),
		                       $replays_total, ($replays_total == 1 ? '' : 's'), formatTimeH($totaltime * 1000, false));

	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
}  // chat_playtime

function chat_time($aseco, $command) {

	// show chat message
	$message = formatText($aseco->getChatMessage('TIME'),
	                      date('H:i:s T'), date('Y/M/d'));
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
}  // chat_time
?>
