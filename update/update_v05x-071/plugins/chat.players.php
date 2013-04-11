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
  $player->msgs[0] = array(1, $head, array(1.3, 0.1, 0.65, 0.55), array('Icons128x128_1', 'Buddies'));
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

      $nat = $pl->zone;
     // if (strlen($nat) > 14)
      //  $nat = mapCountry($nat);
      $msg[] = array(str_pad($pid, 2, '0', STR_PAD_LEFT) . '.',
                     $ply, '{#black}' . $nat);
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
  }
}  // event_players
?>