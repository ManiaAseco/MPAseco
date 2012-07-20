<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Nextmap plugin.
 * Shows the name of the next map.
 * Updated by Xymph & AssemblerManiac
 *
 * Dependencies: none
 */

Aseco::addChatCommand('nextmap', 'Shows name of the next map');

function chat_nextmap($aseco, $command) {
	global $rasp, $jukebox;

	$login = $command['author']->login;

	// check for relay server
	if ($aseco->server->isrelay) {
		$message = formatText($aseco->getChatMessage('NOTONRELAY'));
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
		return;
	}

	// check jukebox first
	if (!empty($jukebox)) {
		$jbtemp = $jukebox;
		$map = array_shift($jbtemp);
		$next = $map['Name'];
		// get environment
		$aseco->client->query('GetMapInfo', $map['FileName']);
		$map = $aseco->client->getResponse();
		$env = $map['Environnement'];
	} else {
		$aseco->client->query('GetNextMapIndex');
		$next = $aseco->client->getResponse();
		$rtn = $aseco->client->query('GetMapList', 1, $next);
		$map = $aseco->client->getResponse();
		$next = stripNewlines($map[0]['Name']);
		$env = $map[0]['Environnement'];
	}

	// show chat message
	if ($aseco->server->packmask == 'Canyon') {
		$message = formatText($rasp->messages['NEXTMAP'][0],
		                      stripColors($next));
	} else {
		$message = formatText($rasp->messages['NEXTENVMAP'][0],
		                      $env, stripColors($next));
	}
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
}  // chat_nextmap
?>
