<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * This script saves record into a local database.
 * You can modify this file as you want, to advance
 * the information stored in the database!
 *
 * Updated by Xymph
 * Edited for ShootMania by the MPAseco team
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
Aseco::registerEvent('onPlayerDeath', 'ldb_playerDeath');
//Aseco::registerEvent('onPlayerVote', 'ldb_vote');

// called @ onStartup
function ldb_loadSettings($aseco) {
	global $ldb_settings;

	$aseco->console('[LocalDB] Load config file [localdatabase.xml]');
	if (!$settings = $aseco->xml_parser->parseXml('localdatabase.xml')) {
		trigger_error('Could not read/parse Local database config file localdatabase.xml !', E_USER_ERROR);
	}
	$settings = $settings['SETTINGS'];

	// read mysql server settings
	$ldb_settings['mysql']['host'] = $settings['MYSQL_SERVER'][0];
	$ldb_settings['mysql']['login'] = $settings['MYSQL_LOGIN'][0];
	$ldb_settings['mysql']['password'] = $settings['MYSQL_PASSWORD'][0];
	$ldb_settings['mysql']['database'] = $settings['MYSQL_DATABASE'][0];
	$ldb_settings['mysql']['connection'] = false;

	$ldb_settings['messages'] = $settings['MESSAGES'][0];
}  // ldb_loadSettings

// called @ onStartup
function ldb_connect($aseco) {
	global $maxrecs;

	// get the settings
	global $ldb_settings;
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
	            `Nation` varchar(3) NOT NULL default '',
	            `UpdatedAt` datetime NOT NULL default '0000-00-00 00:00:00',
	            `Wins` mediumint(9) NOT NULL default 0,
	            `TimePlayed` int(10) unsigned NOT NULL default 0,
	            `TeamName` char(60) NOT NULL default '',
              `Respawns` mediumint(9) unsigned NOT NULL default 0,
              `Deaths` mediumint(9) unsigned NOT NULL default 0,
              `Hits` mediumint(9) unsigned NOT NULL default 0,
              `GotHits` mediumint(9) unsigned NOT NULL default 0,
              `Captures` mediumint(9) unsigned NOT NULL default 0,
	            PRIMARY KEY (`Id`),
	            UNIQUE KEY `Login` (`Login`),
	            KEY `Game` (`Game`)
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
	//$check[3] = in_array('records', $tables);
	$check[4] = in_array('players_extra', $tables);
	if (!($check[1] && $check[2] && $check[4])) {
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
		          (Login, Game, NickName, Nation, TeamName, UpdatedAt)
		          VALUES
		          (' . quotedString($player->login) . ', ' .
		           quotedString($aseco->server->getGame()) . ', ' .
		           quotedString($player->nickname) . ', ' .
		           quotedString($nation) . ', ' .
		           quotedString($player->teamname) . ', NOW())';

		$result = mysql_query($query);
		if (mysql_affected_rows() != 1) {
			trigger_error('Could not insert connecting player! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
		} else {
			$query = 'SELECT last_insert_id() FROM players';
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



// called @ onBeginMap
function ldb_beginMap($aseco, $map) {
	global $ldb_map, $ldb_records, $ldb_settings;

	// on relay, ignore master server's map
	if ($aseco->server->isrelay) {
		$map->id = 0;
		return;
	}                               //0TsolhrmTzlR43CtotyiHr_Tec

	$order =  'ASC';
	$query =  'SELECT m.Id AS MapId FROM maps m WHERE m.Uid=' . quotedString($map->uid) . ' GROUP BY m.Id';
/*	$query = 'SELECT m.Id AS MapId, p.NickName, p.Login
	          FROM maps m
	          LEFT JOIN players p ON (r.PlayerId=p.Id)
	          WHERE m.Uid=' . quotedString($map->uid) . '
	          GROUP BY r.Id
	          ORDER BY r.Score ' . $order . ',r.Date ASC
	          LIMIT ' . $ldb_records->max;            */
	$result = mysql_query($query);

	if (mysql_num_rows($result) === false) {
		trigger_error('Could not get map info! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
	}

	// map found?
	if (mysql_num_rows($result) > 0) {

 		while ($record = mysql_fetch_array($result)) {   
    $record_item = new Record();
 		$record_item->map = clone $map;
		unset($record_item->map->gbx);  // reduce memory usage
		unset($record_item->map->mx);
      
      // get map info
      $map->id = $record['MapId'];
    }     
		mysql_free_result($result);
	// map isn't in database yet
	} else {
		mysql_free_result($result);

		// then create it
		$query = 'INSERT INTO maps
		          (Uid, Name, Author, Environment)
		          VALUES
		          (' . quotedString($map->uid) . ', ' .
		           quotedString(stripColors($map->name)) . ', ' .
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

function ldb_playerDeath($aseco, $login) {
  $query = 'UPDATE players SET deaths = deaths+1 WHERE login = '.quotedString($login);
  mysql_query($query);
}
?>
