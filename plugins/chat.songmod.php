<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Chat plugin.
 * Shows (file)names of current map's song & mod.
 * Created by Xymph
 *
 * Dependencies: none
 */

Aseco::addChatCommand('song', 'Shows filename of current map\'s song');
Aseco::addChatCommand('mod', 'Shows (file)name of current map\'s mod');

function chat_song($aseco, $command) {

	$player = $command['author'];

	// check for map's song
	if ($aseco->server->map->gbx->songfile) {
		$message = formatText($aseco->getChatMessage('SONG'),
		                      stripColors($aseco->server->map->name),
		                      $aseco->server->map->gbx->songfile);
		// use only first parameter
		$command['params'] = explode(' ', $command['params'], 2);
		if ((strtolower($command['params'][0]) == 'url' ||
		     strtolower($command['params'][0]) == 'loc') &&
		    $aseco->server->map->gbx->songurl) {
			$message .= LF . '{#highlite}$l[' . $aseco->server->map->gbx->songurl . ']' . $aseco->server->map->gbx->songurl . '$l';
		}
	} else {
		$message = '{#server}> {#error}No map song found!';
		if (function_exists('chat_music'))
			$message .= '  Try {#highlite}$i /music current {#error}instead.';
	}
	// show chat message
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
}  // chat_song

function chat_mod($aseco, $command) {

	$player = $command['author'];

	// check for map's mod
	if ($aseco->server->map->gbx->modname) {
		$message = formatText($aseco->getChatMessage('MOD'),
		                      stripColors($aseco->server->map->name),
		                      $aseco->server->map->gbx->modname,
		                      $aseco->server->map->gbx->modfile);
		// use only first parameter
		$command['params'] = explode(' ', $command['params'], 2);
		if ((strtolower($command['params'][0]) == 'url' ||
		     strtolower($command['params'][0]) == 'loc') &&
		    $aseco->server->map->gbx->modurl) {
			$message .= LF . '{#highlite}$l[' . $aseco->server->map->gbx->modurl . ']' . $aseco->server->map->gbx->modurl . '$l';
		}
	} else {
		$message = '{#server}> {#error}No map mod found!';
	}
	// show chat message
	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
}  // chat_mod
?>
