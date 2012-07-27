<?php

/**
 * Plugin to handle the script callbacks send by the server.
 * Made by the MPAseco team for ShootMania
 */

Aseco::registerEvent('onModeScriptCallback', 'release_modeScriptCallbacks');

function release_modeScriptCallbacks($aseco, $data) {
	$name = $data[0];
	$params = isset($data[1]) ? $data[1] : '';

	switch($name) {
		case 'playerDeath':
			$aseco->releaseEvent('onPlayerDeath', $params);
			break;
		case 'poleCapture':
			$aseco->releaseEvent('onPoleCapture', $params);
			break;
		case 'playerHit':
			$players = explode(';', $params);
			$victim = str_replace('Victim:', '', $players[0]);
			$shooter = str_replace('Shooter:', '', $players[1]);
			$points = $players[2];
			$aseco->releaseEvent('onPlayerHit', array('victim' => $victim, 'shooter' => $shooter, 'points' => $points));
			break;
		case 'playerRespawn':
			$aseco->releaseEvent('onPlayerRespawn', $params);
			break;
		case 'beginRound':
			updateRankings($params);
			$aseco->releaseEvent('onBeginRound', $aseco->smrankings);
			break;
		case 'endRound':
			updateRankings($params);
			$aseco->releaseEvent('onEndRound', $aseco->smrankings);
			break;
		case 'endMap':
			updateRankings($params);
			$aseco->endMapRanking($aseco->smrankings);    //temporary fix
			$aseco->endmapvar=1;
			$aseco->releaseEvent('onEndMap', $aseco->smrankings);
			$aseco->releaseEvent('onEndMap1', $aseco->smrankings);
			break;
		case 'beginMap':
			$aseco->smrankings = array();
			break;
	}
}

function updateRankings($data) {
	global $aseco;
	$scores = explode(';', $data);
	foreach($scores as $player) {
		if (strpos($player, ':') !== false) {
			$tmp = explode(':', $player);
			$aseco->smrankings[$tmp[0]] = $tmp[1];
		}
	}
	array_multisort($aseco->smrankings, SORT_DESC, SORT_NUMERIC);
}
?>
