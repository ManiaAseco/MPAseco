<?php
Aseco::registerEvent('onModeScriptCallback', 'release_modeScriptCallbacks');

function release_modeScriptCallbacks($aseco, $data) {
  $name = $data[0];
  $params = isset($data[1]) ? $data[1] : '';
  
  switch($name) {
    case 'playerDeath':
      $aseco->releaseEvent('onplayerDeath', $params);
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
      $aseco->releaseEvent('onBeginRound', $params);
    break;
    case 'endRound':
      $aseco->releaseEvent('onEndRound', $params);
    break;
  }
}

?>
