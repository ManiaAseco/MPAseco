<?php
/**
 * Plugin to handle the script callbacks send by the server.
 * Made by the MPAseco team for ShootMania
 * v2.2
 */

Aseco::registerEvent('onModeScriptCallback', 'release_modeScriptCallbacks');
Aseco::registerEvent('onModeScriptCallbackArray', 'release_LibXmlRpcCallbacks');
Aseco::registerEvent('onStartup', 'init_XmlRpcOnStartup');

function init_XmlRpcOnStartup($aseco){
  $scriptset = array('S_UseScriptCallbacks' => true); //Aktivate Script callbacks 
  $aseco->client->query('SetModeScriptSettings',  $scriptset);
  $aseco->client->query('TriggerModeScriptEvent', 'LibXmlRpc_GetRankings', '');
}

function release_LibXmlRpcCallbacks($aseco, $data){

  $name = $data[0];
  $params = isset($data[1]) ? $data[1] : '';
  switch($name) {

    case 'LibXmlRpc_Rankings': 
      updateRankings($params[0]);
    break;
    case 'LibXmlRpc_BeginRound':
      $aseco->beginRound($params[0]); //$params[0] = roundsnumber
    break;

    case 'LibXmlRpc_EndRound':  
      $aseco->endRound($params[0]); //$params[0] = roundsnumber
    break;
    
    case 'LibXmlRpc_BeginMatch':
      $aseco->releaseEvent('onBeginMatch', $params[0]);
    break;

    case 'LibXmlRpc_EndMatch':
      $aseco->releaseEvent('onEndMatch', $params[0]);
    break;

    case 'LibXmlRpc_BeginSubmatch':
      $aseco->releaseEvent('onBeginSubmatch', $params[0]);
    break;

    case 'LibXmlRpc_EndSubmatch':
      $aseco->releaseEvent('onEndSubmatch', $params[0]);
    break;

    case 'LibXmlRpc_BeginTurn':
      $aseco->releaseEvent('onBeginTurn', $params[0]);
    break;

    case 'LibXmlRpc_EndTurn':
      $aseco->releaseEvent('onEndTurn', $params[0]);
    break;
                
    case 'LibXmlRpc_BeginMap':
      $aseco->beginMap(0);
    break;   
        
    case 'LibXmlRpc_EndMap':
      if(!$aseco->endmapvar){ 
        $aseco->console_text('End Map');
        $aseco->releaseEvent('onEndMap1', $aseco->smrankings);    
        $aseco->displayEndMapRecords(); 
        $aseco->endMapRanking($aseco->smrankings);    //temporary fix 
        $aseco->beginmapvar = false;   
        $aseco->endmapvar = true;
        $aseco->releaseEvent('onEndMap', $aseco->smrankings);
      }
    break; 
   
    case 'LibXmlRpc_OnShoot':
      $aseco->releaseEvent('onPlayerShoot', $params);
      $aseco->releaseEvent('onPlayerShoot1', $params[0]);
    break;

    case 'LibXmlRpc_OnHit': 
      //ShooterLogin, VictimLogin, TL::ToText(_Event.Damage), TL::ToText(_Event.WeaponNum), TL::ToText(_Event.ShooterPoints)
      $aseco->releaseEvent('onPlayerHit', array('victim' => $params[1], 'shooter' => $params[0], 'points' => $params[4]));
      $aseco->releaseEvent('onPlayerHit1',$params);
    break;

    case 'LibXmlRpc_OnNearMiss':
      $aseco->releaseEvent('onNearMiss', $params[0]);
	  $aseco->releaseEvent('onNearMiss1', array('ShooterLogin' => $params[0], 'VictimLogin' => $params[1], 'NearMissMeter' => $params[3]));
    break;
                   
    case 'LibXmlRpc_OnCapture': 
      $players = explode(';', $params[0]);
      foreach($players as $player){
        $aseco->releaseEvent('onPoleCapture', $player);
      }
	   $aseco->client->query('TriggerModeScriptEvent', 'LibXmlRpc_GetRankings', ''); //Siege Additions....
    break;
    
    case 'LibXmlRpc_OnArmorEmpty': 
      //ShooterLogin, VictimLogin, TL::ToText(_Event.Damage), TL::ToText(_Event.WeaponNum), TL::ToText(_Event.ShooterPoints)
      $aseco->releaseEvent('onPlayerDeath', $params[1]); 
      $aseco->releaseEvent('onPlayerDeath1', $params); 
    break;

    case 'LibXmlRpc_OnPlayerRequestRespawn':  
      $aseco->releaseEvent('onPlayerRespawn', $params[0]);
    break;    

    case 'Royal_UpdatePoints':  
      $aseco->releaseEvent('onRoyalUpdatePoints', $params);
    break;  

    case 'Royal_SpawnPlayer':  
      $aseco->releaseEvent('onRoyalSpawnPlayer', $params);
    break;      
    
    case 'TimeAttack_OnStart':  
      $aseco->releaseEvent('onPlayerStartTimeMode', $params[0]);
    break;  
 
    case 'TimeAttack_OnCheckpoint':
      $aseco->releaseEvent('onCheckpoint1', $params); 
    break;  
    
    case 'TimeAttack_OnFinish':
      $finish = array(1, $params[0], $params[1]);
      $aseco->playerFinish($finish);
    break;      

    case 'TimeAttack_OnRestart':
      $aseco->releaseEvent('onRestart', $params); 
    break;   

    case 'Joust_OnReload':
      $aseco->releaseEvent('onJoustReload', $params[0]); 
    break; 

    case 'Joust_SelectedPlayers':
      $aseco->releaseEvent('onJoustSelectedPlayers', $params); 
    break; 

    case 'Joust_RoundResult':
      $aseco->releaseEvent('onJoustRoundResult', $params); 
    break;
  }
}
function release_modeScriptCallbacks($aseco, $data) {
  $name = $data[0];
  $params = isset($data[1]) ? $data[1] : '';
  switch($name) {
    case 'updateRankings':
      updateRankings($params[0]);    
    break;
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
    case 'Player.Position':
	      $players = explode(';', $params);
		  $Login = str_replace('Login:', '', $players[0]);
		  $PosX = str_replace('PositionX:', '', $players[1]);
		  $PosZ = str_replace('PositionZ:', '', $players[2]);
	$aseco->releaseEvent('Player_Position', array('login' => $Login, 'x' => $PosX, 'z' => $PosZ));
	break;
    case 'playerSurvival':
      $aseco->releaseEvent('onPlayerSurvival', $params);
    break;
    case 'attackerWon':
      $aseco->releaseEvent('onPlayerWonAttackRound', $params);
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
      $aseco->releaseEvent('onPlayerSurvival', $shooter);
    break;
    case 'beginRound':
      updateRankings($params);
      $aseco->beginRound();
    break;
    case 'endRound':
      updateRankings($params);
      $aseco->endRound();
    break;
    case 'MatchEnded': //TimeTrial Mode
      if(!$aseco->endmapvar){    
        $aseco->releaseEvent('onEndMap1', $aseco->smrankings);
        $aseco->smrankings = json_decode($params,true);
        if($aseco->settings['records_activated'])
          array_multisort($aseco->smrankings, SORT_ASC, SORT_NUMERIC);
        else
          array_multisort($aseco->smrankings, SORT_DESC, SORT_NUMERIC); 
        $aseco->beginmapvar = false; 
        $aseco->endmapvar = true;
        $aseco->releaseEvent('onEndMap', $aseco->smrankings);
      }
    break;
        
    case 'endMap1':     
    case 'endMap': 
      if(!$aseco->endmapvar){
        $aseco->console_text('End Map');
        $aseco->releaseEvent('onEndMap1', $aseco->smrankings);
        updateRankings($params);  
        $aseco->displayEndMapRecords(); 
        $aseco->endMapRanking($aseco->smrankings);    //temporary fix   
        $aseco->beginmapvar = false;  
        $aseco->endmapvar = true;
        $aseco->releaseEvent('onEndMap', $aseco->smrankings);
      }
    break;   
    case 'OnShoot': /* Begin JSON Events: */
      $paramsObject = json_decode($params);
      $aseco->releaseEvent('onPlayerShoot', $paramsObject->Event->Shooter->Login);
    break; 
    case 'OnHit':
      $paramsObject = json_decode($params);
      $aseco->releaseEvent('onPlayerHit', array('victim' => $paramsObject->Event->Victim->Login, 'shooter' => $paramsObject->Event->Shooter->Login, 'points' => 1));
    break;
    case 'OnArmorEmpty':
      $paramsObject = json_decode($params);
      $aseco->releaseEvent('onPlayerDeath', $paramsObject->Event->Victim->Login);
    break;
    case 'OnPlayerRequestRespawn':
      $paramsObject = json_decode($params);
      $aseco->releaseEvent('onPlayerRespawn', $paramsObject->Event->Player->Login);
    break;
    case 'Spawning': //TimeTrial Mode
      $paramsObject = json_decode($params);
      $aseco->releaseEvent('onSpawning', array($paramsObject->Login, $paramsObject->CpId));
    break;
    case 'Surrender': //TimeTrial Mode
      $aseco->releaseEvent('onSurrender', json_decode($params)->Login);
    break;
    case 'Checkpoint':  //TimeTrial Mode
      $paramsObject = json_decode($params);
      $checkpoint = array(1, $paramsObject->Login, $paramsObject->CpTime, 1, $paramsObject->CpId - 1);
      $aseco->releaseEvent('onCheckpoint', $checkpoint);
    break;
    case 'OnCheckpoint':
      $paramsObject = json_decode($params);
      $checkpoint = array(1, $paramsObject->Player->Login, $paramsObject->Run->Time, 1, $paramsObject->Run->CheckpointId);
      $aseco->releaseEvent('onCheckpoint', $checkpoint);
    break;    
    case 'Finished': //TimeTrial Mode
      $paramsObject = json_decode($params);
      $finish = array(1, $paramsObject->Login, $paramsObject->Score);
      $aseco->playerFinish($finish);
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
  if($aseco->settings['records_activated']){
    array_multisort($aseco->smrankings, SORT_ASC, SORT_NUMERIC);
	$aseco->releaseEvent('onRankingUpdated', $aseco->smrankings);
  }else{
    array_multisort($aseco->smrankings, SORT_DESC, SORT_NUMERIC);  
  $aseco->releaseEvent('onRankingUpdated', $aseco->smrankings);
  }
}

?>