<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * This script saves record into a local database.
 * You can modify this file as you want, to advance
 * the information stored in the database!
 *
 * Updated by Xymph
 * edited for SM 20.07.2012 by the MPAseco team
 * added full record Support 01.02.2013 by the MPAseco Team
 *  
 * Dependencies: requires plugin.panels.php
 *  
 */

Aseco::registerEvent('onStartup', 'ldb_loadSettings');
Aseco::registerEvent('onStartup', 'ldb_connect');
Aseco::registerEvent('onEverySecond', 'ldb_reconnect');
Aseco::registerEvent('onSync', 'ldb_sync');
Aseco::registerEvent('onBeginMap', 'ldb_beginMap');
Aseco::registerEvent('onPlayerConnect', 'ldb_playerConnect');
Aseco::registerEvent('onPlayerDisconnect', 'ldb_playerDisconnect');
Aseco::registerEvent('onPlayerFinish', 'ldb_playerFinish');
Aseco::registerEvent('onPlayerWins', 'ldb_playerWins');

Aseco::registerEvent('onPlayerHit', 'ldb_playerHit');
Aseco::registerEvent('onPoleCapture', 'ldb_poleCapture');
Aseco::registerEvent('onPlayerRespawn', 'ldb_playerRespawn');
Aseco::registerEvent('onPlayerSurvival', 'ldb_playerSurvival');
Aseco::registerEvent('onPlayerDeath', 'ldb_playerDeath');
//Aseco::registerEvent('onPlayerVote', 'ldb_vote');

// called @ onStartup
function ldb_loadSettings($aseco) {
  global $ldb_settings;
  global $argv,$argc;

  if(strpos($argv[$argc-2],".xml") && strpos($argv[$argc-1],".xml"))
    $ldbfile='configs/'.$argv[$argc-1];
  else if(file_exists('localdatabase.xml'))
    $ldbfile='localdatabase.xml'; 
  else
    $ldbfile='configs/localdatabase.xml'; 
   
  $aseco->console('[LocalDB] Load config file ['.$ldbfile.']');
  if (!$settings = $aseco->xml_parser->parseXml($ldbfile)) {
    trigger_error('Could not read/parse Local database config file '.$ldbfile.' !', E_USER_ERROR);
  }
  $settings = $settings['SETTINGS'];

  // read mysql server settings
  $ldb_settings['mysql']['host'] = $settings['MYSQL_SERVER'][0];
  $ldb_settings['mysql']['login'] = $settings['MYSQL_LOGIN'][0];
  $ldb_settings['mysql']['password'] = $settings['MYSQL_PASSWORD'][0];
  $ldb_settings['mysql']['database'] = $settings['MYSQL_DATABASE'][0];
  $ldb_settings['mysql']['connection'] = false;

  // read additional records settings from records.xml
  if(file_exists('configs/records.xml'))
    $recsfile='configs/records.xml'; 
  else if(file_exists('configs/core/records.xml'))
    $recsfile='configs/core/records.xml'; 
  else
    $recsfile='records.xml'; 
  
  if(file_exists($recsfile)){ 
    $aseco->console('[LocalDB] Load records config file ['.$recsfile.']');
    if (!$settings = $aseco->xml_parser->parseXml($recsfile)) {
      trigger_error('Could not read/parse records config file '.$recsfile.' !', E_USER_ERROR);
    }
    $settings = $settings['RECORDS'];
    // display records in game?
    if (strtoupper($settings['SETTINGS'][0]['DISPLAY'][0]) == 'TRUE')
      $ldb_settings['display'] = true;
    else
      $ldb_settings['display'] = false;
  
    // set highest record still to be displayed
    $ldb_settings['limit'] = $settings['SETTINGS'][0]['LIMIT'][0];
    $ldb_settings['messages'] = $settings['MESSAGES'][0];
  }
}  // ldb_loadSettings

// called @ onStartup
function ldb_connect($aseco) {
  global $maxrecs;

  // get the settings
  global $ldb_settings;
  // create data fields
  global $ldb_records;
  $ldb_records = new RecordList($maxrecs);
  global $ldb_map;
  $ldb_map = new Map();

  // log status message
  $aseco->console("[LocalDB] Try to connect to MySQL server on '{1}' with database '{2}'",
                  $ldb_settings['mysql']['host'], $ldb_settings['mysql']['database']);

  if (!$ldb_settings['mysql']['connection'] = mysql_connect($ldb_settings['mysql']['host'],
                                                            $ldb_settings['mysql']['login'],
                                                            $ldb_settings['mysql']['password'])) {
    trigger_error('[LocalDB] Could not authenticate at MySQL server!', E_USER_ERROR);
  }

  if (!mysql_select_db($ldb_settings['mysql']['database'])) {
    trigger_error('[LocalDB] Could not find MySQL database!', E_USER_ERROR);
  }

  // log status message
  $aseco->console('[LocalDB] MySQL Server Version is ' . mysql_get_server_info());
  // enforce UTF-8 handling
  mysql_query('SET NAMES utf8');
  $aseco->console('[LocalDB] Checking database structure...');

  // create main tables
  $query = "CREATE TABLE IF NOT EXISTS `maps` (
              `Id` mediumint(9) NOT NULL auto_increment,
              `Uid` varchar(27) NOT NULL default '',
              `Name` varchar(100) NOT NULL default '',
              `Author` varchar(30) NOT NULL default '',
              `Environment` varchar(10) NOT NULL default '',
              PRIMARY KEY (`Id`),
              UNIQUE KEY `Uid` (`Uid`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8";
  mysql_query($query);

  $query = "CREATE TABLE IF NOT EXISTS `players` (
              `Id` mediumint(9) NOT NULL auto_increment,
              `Login` varchar(50) NOT NULL default '',
              `Game` varchar(3) NOT NULL default '',
              `NickName` varchar(100) NOT NULL default '',
              `Continent` tinyint(3) NOT NULL default 0,
              `Nation` varchar(3) NOT NULL default '', 
              `UpdatedAt` datetime NOT NULL default '0000-00-00 00:00:00',
              `Wins` mediumint(9) NOT NULL default 0,
              `TimePlayed` int(10) unsigned NOT NULL default 0,
              `TeamName` char(60) NOT NULL default '',
              `Joins` mediumint(9) unsigned NOT NULL default 0,
              `Respawns` mediumint(9) unsigned NOT NULL default 0,
              `Deaths` mediumint(9) unsigned NOT NULL default 0,
              `Hits` mediumint(9) unsigned NOT NULL default 0,
              `attackerWon` mediumint(9) unsigned NOT NULL default 0,
              `GotHits` mediumint(9) unsigned NOT NULL default 0,
              `Captures` mediumint(9) unsigned NOT NULL default 0,
              `Survivals` mediumint(9) unsigned NOT NULL default 0,              
              `AllPoints` mediumint(9) unsigned NOT NULL default 0,
              PRIMARY KEY (`Id`),
              UNIQUE KEY `Login` (`Login`),
              KEY `Game` (`Game`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8";
  mysql_query($query);

  $query = "CREATE TABLE IF NOT EXISTS `records` (
              `Id` int(11) NOT NULL auto_increment,
              `MapId` mediumint(9) NOT NULL default 0,
              `PlayerId` mediumint(9) NOT NULL default 0,
              `Score` int(11) NOT NULL default 0,
              `Date` datetime NOT NULL default '0000-00-00 00:00:00',
              `Checkpoints` text NOT NULL,
              PRIMARY KEY (`Id`),
              UNIQUE KEY `PlayerId` (`PlayerId`,`MapId`),
              KEY `MapId` (`MapId`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8";
  mysql_query($query);
  
  $query = "CREATE TABLE IF NOT EXISTS `players_extra` (
              `PlayerId` mediumint(9) NOT NULL default 0,
              `Cps` smallint(3) NOT NULL default -1,
              `DediCps` smallint(3) NOT NULL default -1,
              `Donations` mediumint(9) NOT NULL default 0,
              `Style` varchar(20) NOT NULL default '',
              `Panels` varchar(255) NOT NULL default '',
              `PanelBG` varchar(30) NOT NULL default '',
              PRIMARY KEY (`PlayerId`),
              KEY `Donations` (`Donations`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8";
  mysql_query($query);

  // check for main tables
  $tables = array();
  $res = mysql_query('SHOW TABLES');
  while ($row = mysql_fetch_row($res))
    $tables[] = $row[0];
  mysql_free_result($res);
  $check = array();
  $check[1] = in_array('maps', $tables);
  $check[2] = in_array('players', $tables);
  $check[3] = in_array('records', $tables);
  $check[4] = in_array('players_extra', $tables);
  if (!($check[1] && $check[2] && $check[3] && $check[4])) {
    trigger_error('[LocalDB] Table structure incorrect!  Use localdb/aseco.sql to correct this', E_USER_ERROR);
  }

  // add players_extra 'PanelBG' column
  $fields = array();
  $result = mysql_query('SHOW COLUMNS FROM players_extra');
  while ($row = mysql_fetch_row($result))
    $fields[] = $row[0];
  mysql_free_result($result);
  if (!in_array('PanelBG', $fields)) {
    $aseco->console("[LocalDB] Add 'players_extra' column 'PanelBG'...");
    mysql_query("ALTER TABLE players_extra ADD PanelBG VARCHAR(30) NOT NULL DEFAULT ''");
  }

  // Add shootmania related columns
  $fields = array();
  $update = '';
  $result = mysql_query('SHOW COLUMNS FROM players');
  while ($row = mysql_fetch_row($result))
    $fields[] = $row[0];
  mysql_free_result($result);
  if (!in_array('Continent', $fields)) {
    $update .= "ADD Continent tinyint(3) unsigned NOT NULL DEFAULT 0,";
  }
  if (!in_array('Joins', $fields)) {
    $update .= "ADD Joins mediumint(9) unsigned NOT NULL DEFAULT 0,";
  }  
  if (!in_array('Respawns', $fields)) {
    $update .= "ADD Respawns mediumint(9) unsigned NOT NULL DEFAULT 0,";
  }
  if (!in_array('Deaths', $fields)) {
    $update .= "ADD Deaths mediumint(9) unsigned NOT NULL DEFAULT 0,";
  }
  if (!in_array('Hits', $fields)) {
    $update .= "ADD Hits mediumint(9) unsigned NOT NULL DEFAULT 0,";
  }
  if (!in_array('GotHits', $fields)) {
    $update .= "ADD GotHits mediumint(9) unsigned NOT NULL DEFAULT 0,";
  }
  if (!in_array('Captures', $fields)) {
    $update .= "ADD Captures mediumint(9) unsigned NOT NULL DEFAULT 0,";
  }
  if (!in_array('Survivals', $fields)) {
    $update .= "ADD Survivals mediumint(9) unsigned NOT NULL DEFAULT 0,";
  } 
  if (!in_array('attackerWon', $fields)) {
    $update .= "ADD attackerWon mediumint(9) unsigned NOT NULL DEFAULT 0,";
  }   
  if (!in_array('AllPoints', $fields)) {
    $update .= "ADD AllPoints int(20) unsigned NOT NULL DEFAULT 0,";
  } 
  $update = substr($update, -1, 1) == ',' ? substr($update, 0, -1) : $update;

  if(!empty($update)) {
    $aseco->console("[LocalDB] Add shootmania-related columns to 'players' ...");
    mysql_query("ALTER TABLE players ".$update);
  }
 
  $aseco->console('[LocalDB] ...Structure OK!');
}  // ldb_connect

// called @ onEverySecond
function ldb_reconnect($aseco) {
  global $ldb_settings;

  // check if any players online
  if (empty($aseco->server->players->player_list)) {
    // check if MySQL connection still alive
    if (!mysql_ping($ldb_settings['mysql']['connection'])) {
      // connection timed out so reconnect
      mysql_close($ldb_settings['mysql']['connection']);
      if (!$ldb_settings['mysql']['connection'] = mysql_connect($ldb_settings['mysql']['host'],
                                                                $ldb_settings['mysql']['login'],
                                                                $ldb_settings['mysql']['password'])) {
        trigger_error('[LocalDB] Could not authenticate at MySQL server!', E_USER_ERROR);
      }
      if (!mysql_select_db($ldb_settings['mysql']['database'])) {
        trigger_error('[LocalDB] Could not find MySQL database!', E_USER_ERROR);
      }
      $aseco->console('[LocalDB] Reconnected to MySQL Server');
    }
  }
}  // ldb_reconnect

// called @ onSync
function ldb_sync($aseco) {

  // reset player list
  $aseco->server->players->resetPlayers();
}  // ldb_sync

// called @ onPlayerConnect
function ldb_playerConnect($aseco, $player) {
  global $ldb_settings;

  $nation = mapCountry($player->nation);

  // get player stats
  $query = 'SELECT Id, Wins, TimePlayed, TeamName
            FROM players
            WHERE Login=' . quotedString($player->login); // .
            // ' AND Game=' . quotedString($aseco->server->getGame());
  $result = mysql_query($query);

  if (mysql_num_rows($result) === false) {
    trigger_error('Could not get stats of connecting player! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
  }

  // was retrieved
  if (mysql_num_rows($result) > 0) {
    $dbplayer = mysql_fetch_object($result);
    mysql_free_result($result);

    // update player stats
    $player->id = $dbplayer->Id;
    if ($player->teamname == '' && $dbplayer->TeamName != '') {
      $player->teamname = $dbplayer->TeamName;
    }
    if ($player->wins < $dbplayer->Wins) {
      $player->wins = $dbplayer->Wins;
    }
    if ($player->timeplayed < $dbplayer->TimePlayed) {
      $player->timeplayed = $dbplayer->TimePlayed;
    }

    // update player data
    $query = 'UPDATE players
              SET NickName=' . quotedString($player->nickname) . ',
                  Nation=' . quotedString($nation) . ',
                  Continent=' . continent2cid($player->continent) . ',
                  Joins=Joins+1,
                  TeamName=' . quotedString($player->teamname) . ',
                  UpdatedAt=NOW()
              WHERE Login=' . quotedString($player->login); // .
              // ' AND Game=' . quotedString($aseco->server->getGame());
    $result = mysql_query($query);
  
    if (mysql_affected_rows() == -1) {
      trigger_error('Could not update connecting player! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
    }

  // could not be retrieved
  } else {  // mysql_num_rows() == 0
    mysql_free_result($result);
    $player->id = 0;

    // insert player
    $query = 'INSERT INTO players
              (Login, Game, NickName, Nation, Continent, TeamName, UpdatedAt)
              VALUES
              (' . quotedString($player->login) . ', ' .
               quotedString($aseco->server->getGame()) . ', ' .
               quotedString($player->nickname) . ', ' .
               continent2cid($player->continent) . ', ' .
               quotedString($nation) . ', ' .
               quotedString($player->teamname) . ', NOW())';

    $result = mysql_query($query);
    if (mysql_affected_rows() != 1) {
      trigger_error('Could not insert connecting player! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
    } else {
      $query = 'SELECT Id FROM players ORDER BY Id DESC LIMIT 1;';
      $result = mysql_query($query);
      if (mysql_num_rows($result) === false) {
        trigger_error('Could not get inserted player\'s id! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
      } else {
        $dbplayer = mysql_fetch_row($result);
        $player->id = $dbplayer[0];
        mysql_free_result($result);   
      }
    }
  }

  // check for player's extra data
  $query = 'SELECT PlayerId
            FROM players_extra
            WHERE PlayerId=' . $player->id;
  $result = mysql_query($query);

  if (mysql_num_rows($result) === false) {
    trigger_error('Could not get player\'s extra data! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
  }

  // was retrieved
  if (mysql_num_rows($result) > 0) {
    mysql_free_result($result);

  // could not be retrieved
  } else {  // mysql_num_rows() == 0
    mysql_free_result($result);

    // insert player's default extra data
    $query = 'INSERT INTO players_extra
              (PlayerId, Cps, DediCps, Donations, Style, Panels, PanelBG)
              VALUES
              (' . $player->id . ', ' .
               ($aseco->settings['auto_enable_cps'] ? 0 : -1) . ', ' .
               ($aseco->settings['auto_enable_dedicps'] ? 0 : -1) . ', 0, ' .
               quotedString($aseco->settings['window_style']) . ', ' .
               quotedString($aseco->settings['admin_panel'] . '/' .
                            $aseco->settings['donate_panel'] . '/' .
                         //   $aseco->settings['records_panel'] . '/' .
                            $aseco->settings['vote_panel']) . ', ' .
               quotedString($aseco->settings['panel_bg']) . ')';
    $result = mysql_query($query);

    if (mysql_affected_rows() != 1) {
      trigger_error('Could not insert player\'s extra data! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
    }
  }
}  // ldb_playerConnect

// called @ onPlayerDisconnect
function ldb_playerDisconnect($aseco, $player) {

  // ignore fluke disconnects with empty logins
  if ($player->login == '') return;

  // update player
  $query = 'UPDATE players
            SET UpdatedAt=NOW(),
                TimePlayed=TimePlayed+' . $player->getTimeOnline() . '
            WHERE Login=' . quotedString($player->login); // .
            // ' AND Game=' . quotedString($aseco->server->getGame());
  $result = mysql_query($query);

  if (mysql_affected_rows() == -1) {
    trigger_error('Could not update disconnecting player! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
  }
}  // ldb_playerDisconnect

function ldb_getDonations($aseco, $login) {

  // get player's donations
  $query = 'SELECT Donations
            FROM players_extra
            WHERE PlayerId=' . $aseco->getPlayerId($login);
  $result = mysql_query($query);

  if (mysql_num_rows($result) === false || mysql_num_rows($result) == 0) {
    mysql_free_result($result);
    trigger_error('Could not get player\'s donations! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
    return false;
  } else {
    $dbextra = mysql_fetch_object($result);
    mysql_free_result($result);

    return $dbextra->Donations;
  }
}  // ldb_getDonations

function ldb_updateDonations($aseco, $login, $donation) {

  // update player's donations
  $query = 'UPDATE players_extra
            SET Donations=Donations+' . $donation . '
            WHERE PlayerId=' . $aseco->getPlayerId($login);
  $result = mysql_query($query);

  if (mysql_affected_rows() != 1) {
    trigger_error('Could not update player\'s donations! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
  }
}  // ldb_updateDonations

function ldb_getCPs($aseco, $login) {

  // get player's CPs settings
  $query = 'SELECT Cps, DediCps FROM players_extra
            WHERE PlayerId=' . $aseco->getPlayerId($login);
  $result = mysql_query($query);

  if (mysql_num_rows($result) === false || mysql_num_rows($result) == 0) {
    mysql_free_result($result);
    trigger_error('Could not get player\'s CPs! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
    return false;
  } else {
    $dbextra = mysql_fetch_object($result);
    mysql_free_result($result);

    return array('cps' => $dbextra->Cps, 'dedicps' => $dbextra->DediCps);
  }
}  // ldb_getCPs

function ldb_setCPs($aseco, $login, $cps, $dedicps) {

  $query = 'UPDATE players_extra
            SET Cps=' . $cps . ', DediCps=' . $dedicps . '
            WHERE PlayerId=' . $aseco->getPlayerId($login);
  $result = mysql_query($query);

  if (mysql_affected_rows() == -1) {
    trigger_error('Could not update player\'s CPs! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
  }
}  // ldb_setCPs

function ldb_getStyle($aseco, $login) {

  // get player's style
  $query = 'SELECT Style FROM players_extra
            WHERE PlayerId=' . $aseco->getPlayerId($login);
  $result = mysql_query($query);

  if (mysql_num_rows($result) === false || mysql_num_rows($result) == 0) {
    mysql_free_result($result);
    trigger_error('Could not get player\'s style! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
    return false;
  } else {
    $dbextra = mysql_fetch_object($result);
    mysql_free_result($result);

    return $dbextra->Style;
  }
}  // ldb_getStyle

function ldb_setStyle($aseco, $login, $style) {

  $query = 'UPDATE players_extra
            SET Style=' . quotedString($style) . '
            WHERE PlayerId=' . $aseco->getPlayerId($login);
  $result = mysql_query($query);

  if (mysql_affected_rows() == -1) {
    trigger_error('Could not update player\'s style! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
  }
}  // ldb_setStyle

function ldb_getPanels($aseco, $login) {

  // get player's panels
  $query = 'SELECT Panels FROM players_extra
            WHERE PlayerId=' . $aseco->getPlayerId($login);
  $result = mysql_query($query);

  if (mysql_num_rows($result) === false || mysql_num_rows($result) == 0) {
    mysql_free_result($result);
    trigger_error('Could not get player\'s panels! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
    return false;
  } else {
    $dbextra = mysql_fetch_object($result);
    mysql_free_result($result);

    $panel = explode('/', $dbextra->Panels);
    $panels = array();
    $panels['admin'] = $panel[0];
    $panels['donate'] = $panel[1];
    //$panels['records'] = $panel[2];
    $panels['vote'] = $panel[2];
    return $panels;
  }
}  // ldb_getPanels

function ldb_setPanel($aseco, $login, $type, $panel) {

  // update player's panels
  $panels = ldb_getPanels($aseco, $login);
  $panels[$type] = $panel;
  $query = 'UPDATE players_extra
            SET Panels=' . quotedString($panels['admin'] . '/' . $panels['donate'] . '/' .
                                        $panels['records'] . '/' . $panels['vote']) . '
            WHERE PlayerId=' . $aseco->getPlayerId($login);
  $result = mysql_query($query);

  if (mysql_affected_rows() == -1) {
    trigger_error('Could not update player\'s panels! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
  }
}  // ldb_setPanel

function ldb_getPanelBG($aseco, $login) {

  // get player's panel background
  $query = 'SELECT PanelBG FROM players_extra
            WHERE PlayerId=' . $aseco->getPlayerId($login);
  $result = mysql_query($query);

  if (mysql_num_rows($result) === false || mysql_num_rows($result) == 0) {
    mysql_free_result($result);
    trigger_error('Could not get player\'s panel background! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
    return false;
  } else {
    $dbextra = mysql_fetch_object($result);
    mysql_free_result($result);

    return $dbextra->PanelBG;
  }
}  // ldb_getPanelBG

function ldb_setPanelBG($aseco, $login, $panelbg) {

  // update player's panel background
  $query = 'UPDATE players_extra
            SET PanelBG=' . quotedString($panelbg) . '
            WHERE PlayerId=' . $aseco->getPlayerId($login);
  $result = mysql_query($query);

  if (mysql_affected_rows() == -1) {
    trigger_error('Could not update player\'s panel background! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
  }
}  // ldb_setPanelBG

// called @ onPlayerFinish
function ldb_playerFinish($aseco, $finish_item) {
  global $ldb_records, $ldb_settings,
         $checkpoints;  // from plugin.checkpoints.php

  // if no actual finish, bail out immediately
  if ($finish_item->score == 0) return;

  // in Laps mode on real PlayerFinish event, bail out too
  if ($aseco->server->gameinfo->mode == Gameinfo::LAPS && !$finish_item->new) return;

  $login = $finish_item->player->login;
  if (isset($finish_item->player->nickname)) {
    $nickname = stripColors($finish_item->player->nickname);
  } else {
    $nickname = stripColors($finish_item->Player->Name);
  }

  // reset lap 'Finish' flag & add checkpoints
  $finish_item->new = false;
  $finish_item->checks = (isset($checkpoints[$login]) ? $checkpoints[$login]->curr_cps : array());

  // drove a new record?
  // go through each of the XX records
  for ($i = 0; $i < $ldb_records->max; $i++) {
    $cur_record = $ldb_records->getRecord($i);

    // if player's time/score is better, or record isn't set (thanks eyez)
    if ($cur_record === false || ($aseco->server->gameinfo->mode == Gameinfo::STNT ?
                                  $finish_item->score > $cur_record->score :
                                  $finish_item->score < $cur_record->score)) {

      // does player have a record already?
      $cur_rank = -1;
      $cur_score = 0;
      for ($rank = 0; $rank < $ldb_records->count(); $rank++) {
        $rec = $ldb_records->getRecord($rank);

        if ($rec->player->login == $login) {

          // new record worse than old one
          if ($aseco->server->gameinfo->mode == Gameinfo::STNT ?
              $finish_item->score < $rec->score :
              $finish_item->score > $rec->score) {
            return;

          // new record is better than or equal to old one
          } else {
            $cur_rank = $rank;
            $cur_score = $rec->score;
            break;
          }
        }
      }

      $finish_time = $finish_item->score;
      if ($aseco->server->gameinfo->mode != Gameinfo::STNT)
        $finish_time = formatTime($finish_time);

      if ($cur_rank != -1) {  // player has a record in topXX already

        // compute difference to old record
        if ($aseco->server->gameinfo->mode != Gameinfo::STNT) {
          $diff = $cur_score - $finish_item->score;
          $sec = floor($diff/1000);
          $ths = $diff - ($sec * 1000);
        } else {  // Stunts
          $diff = $finish_item->score - $cur_score;
        }

        // update record if improved
        if ($diff > 0) {
          $finish_item->new = true;
          $ldb_records->setRecord($cur_rank, $finish_item);
        }

        // player moved up in LR list
        if ($cur_rank > $i) {

          // move record to the new position
          $ldb_records->moveRecord($cur_rank, $i);

          // do a player improved his/her LR rank message
          $message = formatText($ldb_settings['messages']['RECORD_NEW_RANK'][0],
                                $nickname,
                                $i+1,
                                ($aseco->server->gameinfo->mode == Gameinfo::STNT ? 'Score' : 'Time'),
                                $finish_time,
                                $cur_rank+1,
                                ($aseco->server->gameinfo->mode == Gameinfo::STNT ?
                                 '+' . $diff : sprintf('-%d.%03d', $sec, $ths)));

          // show chat message to all or player
          if ($ldb_settings['display']) {
            if ($i < $ldb_settings['limit']) {
              if ($aseco->settings['recs_in_window'] && function_exists('send_window_message'))
                send_window_message($aseco, $message, false);
              else
                $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
            } else {
              $message = str_replace('{#server}>> ', '{#server}> ', $message);
              $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
            }
          }

        } else {

          if ($diff == 0) {
            // do a player equaled his/her record message
            $message = formatText($ldb_settings['messages']['RECORD_EQUAL'][0],
                                  $nickname,
                                  $cur_rank+1,
                                  ($aseco->server->gameinfo->mode == Gameinfo::STNT ? 'Score' : 'Time'),
                                  $finish_time);
          } else {
            // do a player secured his/her record message
            $message = formatText($ldb_settings['messages']['RECORD_NEW'][0],
                                  $nickname,
                                  $i+1,
                                  ($aseco->server->gameinfo->mode == Gameinfo::STNT ? 'Score' : 'Time'),
                                  $finish_time,
                                  $cur_rank+1,
                                  ($aseco->server->gameinfo->mode == Gameinfo::STNT ?
                                   '+' . $diff : sprintf('-%d.%03d', $sec, $ths)));
          }

          // show chat message to all or player
          if ($ldb_settings['display']) {
            if ($i < $ldb_settings['limit']) {
              if ($aseco->settings['recs_in_window'] && function_exists('send_window_message'))
                send_window_message($aseco, $message, false);
              else
                $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
            } else {
              $message = str_replace('{#server}>> ', '{#server}> ', $message);
              $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
            }
          }
        }

      } else {  // player hasn't got a record yet

        // if previously tracking own/last local record, now track new one
        if (isset($checkpoints[$login]) &&
            $checkpoints[$login]->loclrec == 0 && $checkpoints[$login]->dedirec == -1) {
          $checkpoints[$login]->best_fin = $checkpoints[$login]->curr_fin;
          $checkpoints[$login]->best_cps = $checkpoints[$login]->curr_cps;
        }

        // insert new record at the specified position
        $finish_item->new = true;
        $ldb_records->addRecord($finish_item, $i);

        // do a player drove first record message
        $message = formatText($ldb_settings['messages']['RECORD_FIRST'][0],
                              $nickname,
                              $i+1,
                              ($aseco->server->gameinfo->mode == Gameinfo::STNT ? 'Score' : 'Time'),
                              $finish_time);

        // show chat message to all or player
        if ($ldb_settings['display']) {
          if ($i < $ldb_settings['limit']) {
            if ($aseco->settings['recs_in_window'] && function_exists('send_window_message'))
              send_window_message($aseco, $message, false);
            else
              $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
          } else {
            $message = str_replace('{#server}>> ', '{#server}> ', $message);
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
          }
        }
      }

      // update aseco records
      $aseco->server->records = $ldb_records;

      // log records when debugging is set to true
      //if ($aseco->debug) $aseco->console('ldb_playerFinish records:' . CRLF . print_r($ldb_records, true));

      // insert and log a new local record (not an equalled one)
      if ($finish_item->new) {
        ldb_insert_record($finish_item);

        // update all panels if new #1 record
        if ($i == 0) {
          setRecordsPanel('local', ($aseco->server->gameinfo->mode == Gameinfo::STNT ?
                                    str_pad($finish_item->score, 5, ' ', STR_PAD_LEFT) :
                                    formatTime($finish_item->score)));
          if (function_exists('update_allrecpanels'))
            update_allrecpanels($aseco, null);  // from plugin.panels.php
        }

        // log record message in console
        $aseco->console('[LocalDB] player {1} finished with {2} and took the {3}. LR place!',
                        $login, $finish_item->score, $i+1);

        // throw 'local record' event
        $finish_item->pos = $i+1;
        $aseco->releaseEvent('onLocalRecord', $finish_item);
      }

      // got the record, now stop!
      return;
    }
  }
}  // ldb_playerFinish

function ldb_insert_record($record) {
  global $aseco, $ldb_map;

  $playerid = $record->player->id;
  $cps = implode(',', $record->checks);

  // insert new record
  $query = 'INSERT INTO records
            (MapId, PlayerId, Score, Date, Checkpoints)
            VALUES
            (' . $ldb_map->id . ', ' . $playerid . ', ' .
             $record->score . ', NOW(), ' . quotedString($cps) . ')';
  $result = mysql_query($query);

  if (mysql_affected_rows() == -1) {
    $error = mysql_error();
    if (!preg_match('/Duplicate entry.*for key/', $error))
      trigger_error('Could not insert record! (' . $error . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
  }

  // could not be inserted?
  if (mysql_affected_rows() != 1) {
    // update existing record
    $query = 'UPDATE records
              SET Score=' . $record->score . ', Date=NOW(), Checkpoints=' . quotedString($cps) . '
              WHERE MapId=' . $ldb_map->id . ' AND PlayerId=' . $playerid;
    $result = mysql_query($query);

    // could not be updated?
    if (mysql_affected_rows() != 1) {
      trigger_error('Could not update record! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
    }
  }
}  // ldb_insert_record

function ldb_removeRecord($aseco, $cid, $pid, $recno) {
  global $ldb_records;

  // remove record
  $query = 'DELETE FROM records WHERE MapId=' . $cid . ' AND PlayerId=' . $pid;
  $result = mysql_query($query);
  if (mysql_affected_rows() != 1) {
    trigger_error('Could not remove record! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
  }

  // remove record from specified position
  $ldb_records->delRecord($recno);

  // check if fill up is needed
  if ($ldb_records->count() == ($ldb_records->max - 1)) {
    // get max'th time
    $query = 'SELECT DISTINCT PlayerId,Score FROM rs_times t1 WHERE MapId=' . $cid .
             ' AND Score=(SELECT MIN(t2.Score) FROM rs_times t2 WHERE MapId=' . $cid .
             '            AND t1.PlayerId=t2.PlayerId) ORDER BY Score,Date LIMIT ' . ($ldb_records->max - 1) . ',1';
    $result = mysql_query($query);
    if (mysql_num_rows($result) == 1) {
      $timerow = mysql_fetch_object($result);

      // get corresponding date/time & checkpoints
      $query = 'SELECT Date,Checkpoints FROM rs_times WHERE MapId=' . $cid .
               ' AND PlayerId=' . $timerow->PlayerId . ' ORDER BY Score,Date LIMIT 1';
      $result2 = mysql_query($query);
      $timerow2 = mysql_fetch_object($result2);
      $datetime = date('Y-m-d H:i:s', $timerow2->Date);
      mysql_free_result($result2);

      // insert new max'th record
      $query = "INSERT INTO records
                (MapId, PlayerId, Score, Date, Checkpoints)
                VALUES
                (" . $cid . ", " . $timerow->PlayerId . ", " .
                 $timerow->Score . ", '" . $datetime . "', '" .
                 $timerow2->Checkpoints . "')";
      $result2 = mysql_query($query);

      // couldn't be inserted? then player had a record already
      if (mysql_affected_rows() != 1) {
        // update max'th record just to be sure it's correct
        $query = "UPDATE records
                  SET Score=" . $timerow->Score . ", Checkpoints='" . $timerow2->Checkpoints . "', Date='" . $datetime . "'
                  WHERE MapId=" . $cid . " AND PlayerId=" . $timerow->PlayerId;
        $result2 = mysql_query($query);
      }

      // get player info
      $query = 'SELECT * FROM players WHERE Id=' . $timerow->PlayerId;
      $result2 = mysql_query($query);
      $playrow = mysql_fetch_array($result2);
      mysql_free_result($result2);

      // create record object
      $record_item = new Record();
      $record_item->score = $timerow->Score;
      $record_item->checks = ($timerow2->Checkpoints != '' ? explode(',', $timerow2->Checkpoints) : array());
      $record_item->new = false;

      // create a player object to put it into the record object
      $player_item = new Player();
      $player_item->nickname = $playrow['NickName'];
      $player_item->login = $playrow['Login'];
      $record_item->player = $player_item;

      // add the map information to the record object
      $record_item->map = clone $aseco->server->map;
      unset($record_item->map->gbx);  // reduce memory usage
      unset($record_item->map->mx);

      // add the created record to the list
      $ldb_records->addRecord($record_item);
    }
    mysql_free_result($result);
  }

  // update aseco records
  $aseco->server->records = $ldb_records;
}  // ldb_remove_record

// called @ onBeginMap
function ldb_beginMap($aseco, $map) {
  global $ldb_map, $ldb_records, $ldb_settings;

  if($aseco->settings['records_activated']){
    $ldb_records->clear();
    $aseco->server->records->clear();
  }
  
  // on relay, ignore master server's map
  if ($aseco->server->isrelay) {
    $map->id = 0;
    return;
  }                               //0TsolhrmTzlR43CtotyiHr_Tec

  $order =  'ASC';
  if($aseco->settings['records_activated']){
    $query = 'SELECT m.Id AS MapId, r.Score, p.NickName, p.Login, r.Date, r.Checkpoints
              FROM maps m
              LEFT JOIN records r ON (r.MapId=m.Id)
              LEFT JOIN players p ON (r.PlayerId=p.Id)
              WHERE m.Uid=' . quotedString($map->uid) . '
              GROUP BY r.Id
              ORDER BY r.Score ' . $order . ',r.Date ASC
              LIMIT ' . $ldb_records->max;
  }else{
    $query =  'SELECT m.Id AS MapId FROM maps m WHERE m.Uid=' . quotedString($map->uid) . ' GROUP BY m.Id';
  }
    $result = mysql_query($query);  
  
  if (mysql_num_rows($result) === false) {
    trigger_error('Could not get map info! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
  }
  // map found?
  if (mysql_num_rows($result) > 0) {
    // get each record
    while ($record = mysql_fetch_array($result)) {
      if($aseco->settings['records_activated']){
      // create record object
        $record_item = new Record();
        $record_item->score = $record['Score'];
        $record_item->checks = ($record['Checkpoints'] != '' ? explode(',', $record['Checkpoints']) : array());
        $record_item->new = false;
  
        // create a player object to put it into the record object
        $player_item = new Player();
        $player_item->nickname = $record['NickName'];
        $player_item->login = $record['Login'];
        $record_item->player = $player_item;
  
        // add the map information to the record object
        $record_item->map = clone $map;
        unset($record_item->map->gbx);  // reduce memory usage
        unset($record_item->map->mx);
  
        // add the created record to the list
        $ldb_records->addRecord($record_item);
      }
      $ldb_map->id = $record['MapId'];
        // get map info
      $map->id = $record['MapId'];
    }

    // update aseco records
    $aseco->server->records = $ldb_records;
    
    // log records when debugging is set to true
    //if ($aseco->debug) $aseco->console('ldb_beginMap records:' . CRLF . print_r($ldb_records, true));
    mysql_free_result($result);
  // map isn't in database yet
  } else {
    mysql_free_result($result);

    // then create it
    $query = 'INSERT INTO maps
              (Uid, Name, Author, Environment)
              VALUES
              (' . quotedString($map->uid) . ', ' .
               quotedString($map->name) . ', ' .
               quotedString($map->author) . ', ' .
               quotedString($map->environment) . ')';
    $result = mysql_query($query);

    // map was inserted successfully
    if (mysql_affected_rows() == 1) {

      // get its Id now
      $query = 'SELECT Id FROM maps
                WHERE Uid=' . quotedString($map->uid);
      $result = mysql_query($query);

      if (mysql_num_rows($result) == 1) {

        $row = mysql_fetch_row($result);
        $ldb_map->id = $row[0];
        $map->id = $row[0];

      } else {
        // map ID could not be found
        trigger_error('Could not get new map id! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
      }
      mysql_free_result($result);

    } else {
      trigger_error('Could not insert new map! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
    }
  }
}  // ldb_beginMap

// called @ onPlayerWins
function ldb_playerWins($aseco, $player) {

  $wins = $player->getWins();
  $query = 'UPDATE players
            SET Wins=' . $wins . '
            WHERE Login=' . quotedString($player->login);
  $result = mysql_query($query);

  if (mysql_affected_rows() != 1) {
    trigger_error('Could not update winning player! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
  }
}  // ldb_playerWins

function ldb_playerHit($aseco, $data) {
  //maybe optimize
  $query = 'UPDATE players SET Hits = Hits+1 WHERE login = '.quotedString($data['shooter']);
  mysql_query($query);
  $query = 'UPDATE players SET GotHits = GotHits+1 WHERE login = '.quotedString($data['victim']);
  mysql_query($query);
}

function ldb_poleCapture($aseco, $login) {
  $query = 'UPDATE players SET captures = captures+1 WHERE login = '.quotedString($login);
  mysql_query($query);
}

function ldb_playerRespawn($aseco, $login) {
  $query = 'UPDATE players SET respawns = respawns+1 WHERE login = '.quotedString($login);
  mysql_query($query);
}

function ldb_playerSurvival($aseco, $login) {
  $query = 'UPDATE players SET Survivals= Survivals+1 WHERE login = '.quotedString($login);
  mysql_query($query);
}

function ldb_playerDeath($aseco, $login) {
  $query = 'UPDATE players SET deaths = deaths+1 WHERE login = '.quotedString($login);
  mysql_query($query);
}

function ldb_attackerWon($aseco, $login) {
  $query = 'UPDATE players SET attackerWon = attackerWon+1 WHERE login = '.quotedString($login);
  mysql_query($query);
}

?>