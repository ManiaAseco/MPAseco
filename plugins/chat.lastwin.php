<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Chat plugin.
 * Re-displays last closed multi-page window.
 * Created by Xymph
 *
 * Dependencies: none
 */

Aseco::addChatCommand('lastwin', 'Re-opens the last closed multi-page window');

function chat_lastwin($aseco, $command) {

	$player = $command['author'];
	$login = $player->login;

	if (!isset($player->msgs) || empty($player->msgs)) {
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}No multi-page window available!'), $login);
		return;
	}

	// display ManiaLink message
	display_manialink_multi($player);
}  // chat_lastwin
?>
