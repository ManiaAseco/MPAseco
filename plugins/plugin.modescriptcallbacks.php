<?php
/**
 * Plugin to handle the script callbacks send by the server.
 * Made by the MPAseco team for ShootMania
 * v1.0 
 */

Aseco::registerEvent('onModeScriptCallback', 'release_modeScriptCallbacks');



function release_modeScriptCallbacks($aseco, $data) {
  global $singe_callbacks, $multi_callbacks;
  
	$name = $data[0];
	$params = isset($data[1]) ? $data[1] : '';
	$playercnt = count($aseco->server->players->player_list);

  /* For future purpose:   
    foreach($single_callbacks as $callback){
      if($callback->name == $name){
          if($playercnt >= $callback->mincntPlayers)
             $aseco->releaseEvent('on'.ucfirst($callback->name), $params);      
          $name = false; //Avoid switch-case    
      }
    }
  	
  	foreach($multi_callbacks as $callback){
       if($callback->name == $name){
   			  $vals = explode(';', $params);
          $i = 0;
          $indexArr = array();
          foreach($callback->index as $index){
             $index = str_replace($index->name, '', $vals[$i]);
             $indexArr[strtolower($index->name)] = $index;
             $i++;
          }     
          if($playercnt >= $callback->mincntPlayers)
             $aseco->releaseEvent('on'.ucfirst($callback->name), $indexArr);       
          $name = false;  //Avoid switch-case  
       } 
   }   */     
                          
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
		  $paramsObject = json_decode($params);
		  $finish = array(1, $paramsObject->Player->Login, $paramsObject->Run->Time);
			$aseco->playerFinish($finish);
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
   
   function SingleCallback($name){
       $this->name = name;
   }
}

class MultiCallback {
   var $name;
   var $database;
   var $mincnt_players;
   var $index;
   
   function MultiCallback($name){
      $this->name = name;
   }
   function addIndex($indexName, $id){
      $index[$id] = new CallIndex($indexName);
   }
}

class CallIndex{
   var $name;
   var $database;
   function CallIndex($name){
    $this->name = $name;
   }
}

      /*
// called @ onStartup
function load_modeScriptCallbacks($aseco) {
  global $singe_callbacks, $multi_callbacks;
  $msfile = "configs/core/modescriptcallbacks.xml"

//	$aseco->console('[LocalDB] Load config file ['.$msfile.']');
	if (!$xml = $aseco->xml_parser->parseXml($msfile)) {
		trigger_error('Could not read/parse Modescript config file '.$msfile.' !', E_USER_ERROR);
	}
	$xml = $xml['CALLBACKS'];      
  foreach ($xml['SINGLE_CALLBACKS'][0]['CALLBACK'] as $callback) {
    $callback = new SingleCallback($callback['NAME'][0]);
    $callback->database = $callback['DATABASE'][0];
    $callback->mincntPlayers = $callback['MINCNT_PLAYERS'][0];
    $single_callbacks[$callback['NAME'][0]] = $callback;
  }    
  
  foreach ($xml['MULTI_CALLBACKS'][0]['CALLBACK'] as $callback) {
    $callback = new MultiCallback($callback['NAME'][0]);
    foreach ($callback['INDEX'] as $index){
        $id = $index['ID'][0];
        $callback->addIndex($index['NAME'][0], $id);
        $database = $index['DATABASE'][0];
        if($database > 0)
          $callback->index[$id]->database = $database;
    }
    $callback->mincntPlayers = $callback['MINCNT_PLAYERS'][0];
    $multi_callbacks[$callback['NAME'][0]] = $callback; 
  }    

}  // modescriptcallbacks onStartup
      */
?>
