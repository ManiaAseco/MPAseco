<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Chat plugin.
 * Displays main list of players.
 * Updated by Xymph
 *
 * Dependencies: none
 */

Aseco::addChatCommand('players', 'Displays current list of nicks/logins');

// handles action id's "2001"-"2200" for /stats
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'event_players');

function chat_players($aseco, $command) {

  // use only first parameter
  $command['params'] = explode(' ', $command['params'], 2);
  $player = $command['author'];
  $player->playerlist = array();

  $head = 'Players On This Server:';
  $msg = array();
  $msg[] = array('Id', '{#nick}Nick $g/{#login} Login', '{#black}Zone');
  $pid = 1;
  $lines = 0;
  $player->msgs = array();
  $player->msgs[0] = array(1, $head, array(1.3, 0.1, 0.55, 0.45, 0.2), array('Icons128x128_1', 'Buddies'));
  // create list of players, optionally by (sub)string
  foreach ($aseco->server->players->player_list as $pl) {
    if (strlen($command['params'][0]) == 0 ||
        stripos(stripColors($pl->nickname), $command['params'][0]) !== false ||
        stripos($pl->login, $command['params'][0]) !== false) {
      $plarr = array();
      $plarr['login'] = $pl->login;
      $player->playerlist[] = $plarr;

      // format nickname & login
      $ply = '{#black}' . $pl->nickname . '$z / ' .
             ($aseco->isAnyAdmin($pl) ? '{#logina}' : '{#login}') . $pl->login;
      // add clickable button
      if ($aseco->settings['clickable_lists'] && $pid <= 200)
        $ply = array($ply, $pid+2000);  // action id

      $pm = array("Private Message", $pid+8400);
      
      $nat = $pl->zone;
     // if (strlen($nat) > 14)
      //  $nat = mapCountry($nat);
      $msg[] = array(str_pad($pid, 3, '0', STR_PAD_LEFT) . '.',
                     $ply, '{#black}' . $nat, $pm);
      $pid++;
      if (++$lines > 14) {
        $player->msgs[] = $msg;
        $lines = 0;
        $msg = array();
        $msg[] = array('Id', '{#nick}Nick $g/{#login} Login', '{#black}Zone');
      }
    }
  }
  // add if last batch exists
  if (count($msg) > 1)
    $player->msgs[] = $msg;

  // display ManiaLink message
  if (count($player->msgs) > 1) {
    display_manialink_multi($player);
  } else {  // == 1
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}No player(s) found!'), $player->login);
  }
}  // chat_players


// called @ onPlayerManialinkPageAnswer
// Handles ManiaLink player responses
// [0]=PlayerUid, [1]=Login, [2]=Answer, [3]=Entries
function event_players($aseco, $answer) {
  // leave actions outside 2001 - 2200 to other handlers
  $action = (int) $answer[2];
  if ($action >= 2001 && $action <= 2200) {
    // get player
    $player = $aseco->server->players->getPlayer($answer[1]);
    $target = $player->playerlist[$action-2001]['login'];

    // log clicked command
    $aseco->console('player {1} clicked command "/stats {2}"',
                    $player->login, $target);

    // close main window because /stats can take a while
    mainwindow_off($aseco, $player->login);
    // /stats selected player
    $command = array();
    $command['author'] = $player;
    $command['params'] = $target;
    chat_stats($aseco, $command);
  }else if ($action >= 8401 && $action < 8600) {
    $player = $aseco->server->players->getPlayer($answer[1]);  
    $target = $player->playerlist[$action-8401]['login'];
  
   
    pm_window($aseco, $player, $target);
    
  }else if ($action == 8600) {
     $player = $aseco->server->players->getPlayer($answer[1]);

     $command = array();
     $command['author'] = $player;
     $command['params'] = $answer[3][0]["Name"]. " " . $answer[3][0]["Value"];
     if(function_exists("chat_pm")){
       chat_pm($aseco, $command);
       mainwindow_off($aseco, $player->login);
       $aseco->client->query('TriggerModeScriptEvent', 'LibXmlRpc_EnableAltMenu', $player->login); 
     }
  }
}  // event_players

//Opens a pm window for writting a message to player
function pm_window($aseco, $player, $target){

            //<quad pos="-0.71 -0.01 -0.1" size="1.4 0.07" halign="center" style="Bgs1InRace" substyle="BgCardList"/>
            //<quad pos="0.3 0.25 -0.1" size="0.6 0.5" halign="center" style="BgsPlayerCard" substyle="BgCard"/>
         
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>';                                       
    $xml .= '<manialinks>';
    $xml .= '  <manialink id="1">';    
    $xml .= '<frame pos="0.4 0.35 -0.6">
              <quad size="0.8 0.3" style="BgsPlayerCard" substyle="BgCard"/>
              <quad pos="-0.055 -0.045 -0.3" size="0.09 0.09" halign="center" valign="center" style="Icons64x64_1" substyle="Inbox"/>
              
              <label pos="-0.10 -0.025 -0.2" size="1.17 0.07" halign="left" style="TextValueMedium" text="Private Message to '.$aseco->getPlayerNick($target).'"/>
              
              <format style="TextCardSmallScores2"/>';      
                
    $xml .= '<entry pos="-0.40 -0.17 -0.14" sizen="40 4" style="TextValueMedium" halign="center"  valign="center" focusareacolor1="555A" substyle="BgCard" name="'.$target.'" default=""/>';
  
    $xml .=   '<label pos="-0.1 -0.1 -0.2" size="0.75 0.06" halign="left" style="TextCardSmallScores2" text="Enter your Message:"/>';
    $xml.=    '<label pos="-0.4 -0.22 -0.2" halign="center" style="CardButtonMedium" text="Send Message" action=8600/>';   
   // $xml .=   '<quad pos="-0.71 -0.84 -0.2" size="0.08 0.08" halign="center" style="Icons64x64_1" substyle="Close" action="0"/>'; //Close Button 
    $xml .= '</frame>';
    $xml .= '  </manialink>';  
    $xml .= getCustomUIBlock();
    $xml .= '</manialinks>';    
     $close = false;
    $timeout = 0;

    $aseco->client->query('TriggerModeScriptEvent', 'LibXmlRpc_DisableAltMenu', $player->login);                                                                     
    $aseco->client->query('SendDisplayManialinkPageToLogin', $player->login, $xml, ($timeout * 1000), $close);          
    
}

?>