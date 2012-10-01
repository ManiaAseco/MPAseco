<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * MX info plugin.
 * Displays MX map info & records, and provides world record message
 * at start of each map.
 * Created by Xymph
 *
 * Edited for ShootMania by the MPAseco team
 *    
 * Dependencies: none
 */

require_once('includes/mxinfofetcher.inc.php');  // provides access to MX info

Aseco::addChatCommand('mxinfo', 'Displays MX info {Map_ID/MX_ID}');

global $mxdata;  // cached MX data

function chat_mxinfo($aseco, $command) {
	global $mxdata;

	$player = $command['author'];
	$login = $player->login;

	$command['params'] = explode(' ', preg_replace('/ +/', ' ', $command['params']));

	// check for optional Map/MX ID parameter
	$id = $aseco->server->map->uid;
	$name = $aseco->server->map->name;
	$game = 'SM';
	if ($command['params'][0] != '') {
		if (is_numeric($command['params'][0]) && $command['params'][0] > 0) {
			$tid = ltrim($command['params'][0], '0');
			// check for possible map ID
			if ($tid <= count($player->maplist)) {
				// find UID by given map ID
				$tid--;
				$id = $player->maplist[$tid]['uid'];
				$name = $player->maplist[$tid]['name'];
			} else {
				// consider it a MX ID
				$id = $tid;
				$name = '';
			}
		} else {
			$message = '{#server}> {#highlite}' . $command['params'][0] . '{#error} is not a valid Map/MX ID!';
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
			return;
		}
	}

	// obtain MX info
	if (isset($mxdata->uid) && $mxdata->uid == $id) {
		$data = $mxdata;  // use cached data
	} else {
		$data = new MXInfoFetcher($game, $id, false);
	}
	if (!$data || $data->error != '') {
		$message = '{#server}> {#highlite}' . ($name != '' ? stripColors($name) : $id) .
		           '{#error} is not a known MX map, or MX is down!';
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);	
		return;
	}

	// compile & send message
	$header = 'MX Info for: {#black}' . $data->name;
	$links = array($data->imageurl . '?.jpg', false,
	               '$l[' . $data->pageurl . ']Visit MX Page',
	               '$l[' . $data->dloadurl . ']Download Map');
	$stats = array();
	$stats[] = array('MX ID', '{#black}' . $data->id,
	                 'Type/Style', '{#black}' . $data->type . '$g / {#black}' . $data->style);
	$stats[] = array('UID', '{#black}$n' . $data->uid,
	                 'Env/Mood', '{#black}' . $data->envir . '$g / {#black}' . $data->mood);
	$stats[] = array('Author', '{#black}' . $data->author,
	                 'Routes', '{#black}' . $data->routes);
	$stats[] = array('Display cost', '{#black}' . $data->dispcost,
	                 'Difficulty', '{#black}' . $data->diffic);
	$stats[] = array('Uploaded', '{#black}' . str_replace('T', ' ', preg_replace('/:\d\d\.\d\d\d$/', '', $data->uploaded)),
	                 'Length', '{#black}' . $data->length);
	$stats[] = array('Updated', '{#black}' . str_replace('T', ' ', preg_replace('/:\d\d\.\d\d\d$/', '', $data->updated)),
	                 'Awards', '{#black}' . $data->awards);
	$stats[] = array('Map Value', '{#black}' . $data->trkvalue,
	                 'Replay', ($data->replayurl ?
	                 '{#black}$l[' . $data->replayurl . ']Download$l' : '<none>'));

	// display custom ManiaLink message
	display_manialink_map($login, $header, array('Icons64x64_1', 'Maximize', -0.01), $links, $stats, array(1.15, 0.2, 0.45, 0.2, 0.3), 'OK');
}  // chat_mxinfo
?>
