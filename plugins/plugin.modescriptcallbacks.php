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
		case 'passBall': //Speedball Mode
			$players = explode(';', $params);
			$victim = str_replace('Victim:', '', $players[1]);
			$shooter = str_replace('Shooter:', '', $players[0]);
			$aseco->releaseEvent('onPassBall', array('victim' => $victim, 'shooter' => $shooter));
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
		case 'OnFinish':
			$aseco->releaseEvent('onPlayerFinish', $params);
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



/**
 * Should insert in types.inc.php
 */

class SingleCallback{
   var $name;
   var $database;
   var $mincnt_players;
   
   function SingleCallback($name, $database, $mincntPlayers = 0){
       $this->name = name;
       $this->database = $database;
       $this->mincntPlayers = $mincntPlayers;
   }
}

class MultiCallback {
   var $name;
   var $database;
   var $mincnt_players;
   var $index;
   
   function SingleCallback($name, $database, $mincntPlayers = 0){
      $this->name = name;
      $this->database = $database;
      $this->mincntPlayers = $mincntPlayers;
   }
   function addIndex($indexName, $id){
      $index[$id] = new CallIndex($indexName);
   }
}

class CallIndex(){
   var $name;
   var $database;
   function CallIndex($name){
    $this->name = $name;
   }
}

 /*
 <multi_callbacks>
    <callback>
     <name>playerHit</name>
     <index1>Victim</index1>    
     <index2>Shooter</index2>  
     <index3>Points</index3>   
    <!-- <index1_type>String</index1_type>   
     <index2_type>String</index2_type>  
     <index3_type>Int</index3_type>     -->      
     <database_index1>GotHits</database_index1>                
     <database_index2>Hits</database_index2>        
     <mincnt_players>3</mincnt_players>    
    </callback>     
 </multi_callbacks>  
 */
 
// called @ onStartup
function load_modeScriptCallbacks($aseco) {
	global $ms_callbacks;
  $msfile = "configs/modescriptcallbacks.xml"
     /*
     begin modescriptcallback config
     idea: read xml file and set callback settings (which users can be edit)
     localdb / widgets should read the config file to permit the possabilites for the database
     ... more will come soon                */
	$aseco->console('[LocalDB] Load config file ['.$msfile.']');
	if (!$xml = $aseco->xml_parser->parseXml($msfile)) {
		trigger_error('Could not read/parse Modescript config file '.$msfile.' !', E_USER_ERROR);
	}
	$xml = $xml['CALLBACKS'];      
  foreach ($xml['SINGLE_CALLBACKS'][0]['CALLBACK'] as $callback) {
    $callback = new SingleCallback($callback['NAME'][0]);
    $callback->database = $callback['DATABASE'][0];
    $callback->mincntPlayers = $callback['MINCNT_PLAYERS'][0];
    $ms_callbacks[$callback['NAME'][0]] = $callback;
  }    
  
  foreach ($xml['MULTI_CALLBACKS'][0]['CALLBACK'] as $callback) {
  /*  $callback = new SingleCallback($callback['NAME'][0]);
    $callback->database = $callback['DATABASE'][0];
    $callback->mincntPlayers = $callback['MINCNT_PLAYERS'][0];
    $ms_callbacks[$callback['NAME'][0]] = $callback; */
  }    

}  // modescriptcallbacks

?>
