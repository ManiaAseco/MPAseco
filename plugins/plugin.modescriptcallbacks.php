<?php
/**
 * Plugin to handle the script callbacks send by the server.
 * Made by the MPAseco team for ShootMania
 * v1.0 
 */

Aseco::registerEvent('onModeScriptCallback', 'release_modeScriptCallbacks');

function release_modeScriptCallbacks($aseco, $data) {
	$name = $data[0];
	$params = isset($data[1]) ? $data[1] : '';
	$playercnt = count($aseco->server->players->player_list);
	
	switch($name) {
		case 'playerDeath':
			$aseco->releaseEvent('onPlayerDeath', $params);
		break;
		case 'poleCapture':
			if($playercnt > 1) //only if more than 1 Player on the Server
				$aseco->releaseEvent('onPoleCapture', $params);
		break;
		case 'playerHit':
			$players = explode(';', $params);
			$victim = str_replace('Victim:', '', $players[0]);
			$shooter = str_replace('Shooter:', '', $players[1]);
			$points = $players[2];	 
			if($playercnt > 2) //only if more than 2 Player on the Server
				$aseco->releaseEvent('onPlayerHit', array('victim' => $victim, 'shooter' => $shooter, 'points' => $points));
		break;
		case 'playerSurvival':
			if($playercnt > 3) //only if more than 3 Player on the Server
				$aseco->releaseEvent('onPlayerSurvival', $params);
		break;
		case 'playerRespawn':
			$aseco->releaseEvent('onPlayerRespawn', $params);
		break;
		case 'playerEscaped': //Jailbreak Mode
			$aseco->releaseEvent('onPlayerEscaped', $params);
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
			$aseco->releaseEvent('onEndMap1', $aseco->smrankings);
			updateRankings($params);   
			$aseco->endMapRanking($aseco->smrankings);    //temporary fix  
			$aseco->endmapvar=1;
			$aseco->releaseEvent('onEndMap', $aseco->smrankings);
		break;
		case 'beginMap':
			$aseco->smrankings = array();
		break;   /* Begin JSON Events: */
		case 'OnShoot':
			$paramsObject = json_decode($params);
			$aseco->releaseEvent('onPlayerShoot', $paramsObject->OnShoot->Shooter->Login);
		break; 
		case 'OnHit':
			$paramsObject = json_decode($params);
			if($playercnt > 2) //only if more than 2 Player on the Server
        $aseco->releaseEvent('onPlayerHit', array('victim' => $paramsObject->OnHit->Victim->Login, 'shooter' => $paramsObject->OnHit->Shooter->Login, 'points' => 1));
		break;
		case 'OnArmorEmpty':
			$paramsObject = json_decode($params);
			$aseco->releaseEvent('onPlayerDeath', $paramsObject->OnArmorEmpty->Victim->Login);
		break;
		case 'OnPlayerRequestRespawn':
			$paramsObject = json_decode($params);
			$aseco->releaseEvent('onPlayerRespawn', $paramsObject->OnPlayerRequestRespawn->Player->Login);
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
