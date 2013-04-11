<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Chat plugin.
 * Displays server/MPAseco info & plugins/nations lists.
 * Created by Xymph
 *
 * Edited for ShootMania by the MPAseco team
 *  
 * Dependencies: none
 */
require_once('includes/manialinks.inc.php');  // provides ManiaLinks windows

Aseco::addChatCommand('server', 'Displays info about this server');
Aseco::addChatCommand('mpaseco', 'Displays info about this MPAseco');
Aseco::addChatCommand('plugins', 'Displays list of active plugins');
Aseco::addChatCommand('nations', 'Displays top 10 most visiting nations');

function chat_server($aseco, $command) {
  global $maxrecs, $admin_contact, $feature_votes;  // from rasp.settings.php

  $player = $command['author'];
  $login = $player->login;

  // collect players/nations stats
  $query = 'SELECT COUNT(Id), COUNT(DISTINCT Nation), SUM(TimePlayed) FROM players';
  $res = mysql_query($query);
  if (mysql_num_rows($res) > 0) {
    $row = mysql_fetch_row($res);
    $players = $row[0];
    $nations = $row[1];
    $totaltime = $row[2];
    mysql_free_result($res);
    $playdays = floor($totaltime / (24 * 3600));
    $playtime = $totaltime - ($playdays * 24 * 3600);
  } else {
    mysql_free_result($res);
    trigger_error('No players/nations stats found!', E_USER_ERROR);
  }

  // get server uptime
  $aseco->client->query('GetNetworkStats');
  $network = $aseco->client->getResponse();
  $aseco->server->uptime = $network['Uptime'];
  $updays = floor($aseco->server->uptime / (24 * 3600));
  $uptime = $aseco->server->uptime - ($updays * 24 * 3600);

  // get more server settings in one go
  $comment = $aseco->client->addCall('GetServerComment', array());
  $planets = $aseco->client->addCall('GetServerPlanets', array());
  $cuprpc = $aseco->client->addCall('GetCupRoundsPerMap', array());
  if (!$aseco->client->multiquery()) {
    trigger_error('[' . $aseco->client->getErrorCode() . '] GetServer (multi) - ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
  } else {
    $response = $aseco->client->getResponse();
    $comment = $response[$comment][0];
    $planets = $response[$planets][0];
    $cuprpc = $response[$cuprpc][0]['CurrentValue'];
  }

  $header = 'Welcome to: ' . $aseco->server->name;
  $stats = array();
  $stats[] = array('Server Date', '{#black}' . date('M d, Y'));
  $stats[] = array('Server Time', '{#black}' . date('H:i:s T'));
  $stats[] = array('Zone', '{#black}' . $aseco->server->zone);
  $field = 'Comment';

  // break up long line into chunks with continuation strings
  $multicmt = explode(LF, wordwrap($comment, 35, LF . '...'));
  foreach ($multicmt as $line) {
    $stats[] = array($field, '{#black}' . $line);
    $field = '';
  }

  $stats[] = array('Uptime', '{#black}' . $updays . ' day' . ($updays == 1 ? ' ' : 's ') . formatTimeH($uptime * 1000, false));
  if ($aseco->server->isrelay)
    $stats[] = array('Relays', '{#black}' . $aseco->server->relaymaster['Login'] .
                     ' / ' . $aseco->server->relaymaster['NickName']);
  else
    $stats[] = array('Map Count', '{#black}' . $aseco->server->gameinfo->numchall);
  
    $stats[] = array('Game Script', '{#black}' . $aseco->server->gameinfo->type);
  
  /* -- commented out, because mode will always be 0 (Script), might change in QM (TheM)
  switch ($aseco->server->gameinfo->mode) {
    case 0: // Script
      break;
    case 1:
      $stats[] = array('Points Limit', '{#black}' . $aseco->server->gameinfo->rndslimit);
      break;
    case 2:
      $stats[] = array('Time Limit', '{#black}' . formatTime($aseco->server->gameinfo->timelimit, false));
      break;
    case 3:
      $stats[] = array('Points Limit', '{#black}' . $aseco->server->gameinfo->teamlimit);
      break;
    case 4:
      $stats[] = array('Time Limit', '{#black}' . formatTime($aseco->server->gameinfo->lapslimit, false));
      break;
    case 5:
      $stats[] = array('Points Limit', '{#black}' . $aseco->server->gameinfo->cuplimit . '$g   R/C: {#black}' . $cuprpc);
      break;
    case 6:
      $stats[] = array('Time Limit', '{#black}' . formatTime(5 * 60 * 1000, false));  // always 5 minutes?
      break;
  }*/

  $stats[] = array('Max Players', '{#black}' . $aseco->server->maxplay);
  $stats[] = array('Max Specs', '{#black}' . $aseco->server->maxspec);

  if ($feature_votes) {
    $stats[] = array('Voting info', '{#black}/helpvote');
  } else {
    $stats[] = array('Vote Timeout', '{#black}' . formatTime($aseco->server->votetime, false));
    $stats[] = array('Vote Ratio', '{#black}' . round($aseco->server->voterate, 2));
  }

  if ($aseco->allowAbility($player, 'server_planets')) {
    $stats[] = array('Planets', '{#black}' . $planets);
  }

  $stats[] = array('Ladder Limits', '{#black}' . $aseco->server->laddermin .
                    '$g - {#black}' . $aseco->server->laddermax);

  if ($admin_contact) {
    $stats[] = array('Admin Contact', '{#black}' . $admin_contact);
  }

  $stats[] = array();
  $stats[] = array('Visited by $f80' . $players . ' $gPlayers from $f40' . $nations . ' $gNations');
  $stats[] = array('who together played: {#black}' . $playdays . ' day' . ($playdays == 1 ? ' ' : 's ') . formatTimeH($playtime * 1000, false) . ' $g!');

  // display ManiaLink message
  display_manialink($login, $header, array('Icons128x32_1', 'Settings', 0.01), $stats, array(1.0, 0.3, 0.7), 'OK');
}  // chat_server

function chat_mpaseco($aseco, $command) {
  global $admin_contact;  // from rasp.settings.php

  $player = $command['author'];
  $login = $player->login;

  $uptime = time() - $aseco->uptime;
  $updays = floor($uptime / (24 * 3600));
  $uptime = $uptime - ($updays * 24 * 3600);

  // prepare Welcome message
  $welcome = formatText($aseco->getChatMessage('WELCOME'),
                        stripColors($player->nickname),
                        $aseco->server->name, MPASECO_VERSION);

  $header = 'MPASECO info: ' . $aseco->server->name;
  $info = array();
  $info[] = array('Version', '{#black}' . MPASECO_VERSION);
  $field = 'Welcome';
  $welcome = preg_split('/{br}/', $aseco->formatColors($welcome));
  foreach ($welcome as $line) {
    $info[] = array($field, '{#black}' . $line);
    $field = '';
  }

  $info[] = array('Uptime', '{#black}' . $updays . ' day' . ($updays == 1 ? ' ' : 's ') . formatTimeH($uptime * 1000, false));
  $info[] = array('Websites', '{#black}$l[' . MPASECO . ']' . MPASECO . '$l');
  $info[] = array('', '{#black}$l[' . XASECO_ORG . ']' . XASECO_ORG . '$l');
  $info[] = array('', '{#black}$l[' . XASECO_TMN . ']' . XASECO_TMN . '$l');
  $info[] = array('', '{#black}$l[' . XASECO_TMF . ']' . XASECO_TMF . '$l');  
  $info[] = array('', '{#black}$l[' . XASECO_TM2 . ']' . XASECO_TM2 . '$l'); 
  $info[] = array('Credits', '{#black}Main author TMN:  Flo');    
  $info[] = array('', '{#black}Main author TMF/TM2: Xmyph');    
  $info[] = array('', '{#black}Main author SM/QM:  kremsy');

  if (isset($aseco->masteradmin_list['MPLOGIN'])) {
    // count non-LAN logins
    $count = 0;
    foreach ($aseco->masteradmin_list['MPLOGIN'] as $lgn) {
      if ($lgn != '' && !isLANLogin($lgn)) {
        $count++;
      }
    }
    if ($count > 0) {
      $field = 'Masteradmin';
      if ($count > 1)
        $field .= 's';
      foreach ($aseco->masteradmin_list['MPLOGIN'] as $lgn) {
        // skip any LAN logins
        if ($lgn != '' && !isLANLogin($lgn)) {
          $info[] = array($field, '{#black}' . $aseco->getPlayerNick($lgn) . '$z');
          $field = '';
        }
      }
    }
  }
  if ($admin_contact) {
    $info[] = array('Admin Contact', '{#black}' . $admin_contact);
  }

  // display ManiaLink message
  display_manialink($login, $header, array('BgRaceScore2', 'Warmup'), $info, array(1.0, 0.3, 0.7), 'OK');
}  // chat_mpaseco

function chat_plugins($aseco, $command) {

  $player = $command['author'];

  $head = 'Currently active plugins:';
  $list = array();
  $lines = 0;
  $player->msgs = array();
  $player->msgs[0] = array(1, $head, array(0.7), array('Icons128x128_1', 'Browse', 0.02));
  // create list of plugins
  foreach ($aseco->plugins as $plugin) {
    $list[] = array('{#black}' . $plugin);
    if (++$lines > 14) {
      $player->msgs[] = $list;
      $lines = 0;
      $list = array();
    }
  }
  // add if last batch exists
  if (!empty($list))
    $player->msgs[] = $list;

  // display ManiaLink message
  display_manialink_multi($player);
}  // chat_plugins

function chat_nations($aseco, $command) {

  $top = 10;
  $query = 'SELECT Nation, COUNT(Nation) AS Count FROM players GROUP BY Nation ORDER BY Count DESC LIMIT ' . $top;
  $res = mysql_query($query);

  // collect and sort nations
  if (mysql_num_rows($res) > 0) {
    $nations = array();
    while ($row = mysql_fetch_row($res)) {
      $nations[$row[0]] = $row[1];
    }
    mysql_free_result($res);
  } else {
    trigger_error('No players/nations found!', E_USER_WARNING);
    mysql_free_result($res);
    return;
  }
  arsort($nations);

  $header = 'TOP 10 Most Visiting Nations:';
  $nats = array();
  $bgn = '{#black}';  // nation begin

  // compile sorted nations
  $i = 1;
  foreach ($nations as $nat => $tot) {
    $nats[] = array($i++ . '.', $bgn . $nat, $tot);
  }

  // display ManiaLink message
  display_manialink($command['author']->login, $header, array('Icons128x128_1', 'Credits'), $nats, array(0.8, 0.1, 0.4, 0.3), 'OK');
}  // chat_nations
?>