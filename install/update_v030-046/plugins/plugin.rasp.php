<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * RASP plugin.
 * Provides rank & personal best handling, and related chat commands.
 * Updated by Xymph
 * edited for SM 20.07.2012 by kremsy (www.mania-server.net) 
 *  v17.09.2012
 * Dependencies: requires plugin.rasp_karma.php
 */

Aseco::registerEvent('onStartup', 'event_onstartup');
Aseco::registerEvent('onSync', 'event_onsync');
Aseco::registerEvent('onBeginMap2', 'event_beginmap');  // use 2nd event for logical ordering of rank/karma messages
Aseco::registerEvent('onEndMap', 'event_endmap');
Aseco::registerEvent('onPlayerFinish', 'event_finish');
Aseco::registerEvent('onPlayerConnect', 'event_playerjoin');

Aseco::addChatCommand('rank', 'Shows your current server rank');
Aseco::addChatCommand('top10', 'Displays top 10 best ranked players');
Aseco::addChatCommand('top100', 'Displays top 100 best ranked players');
Aseco::addChatCommand('topwins', 'Displays top 100 victorious players');
Aseco::addChatCommand('active', 'Displays top 100 most active players');

class Rasp {
	var $aseco;
	var $features;
	var $ranks;
	var $settings;
	var $maps;
	var $responses;
	var $maxrec;
	var $playerlist;

	function start($aseco_ext, $config_file) {
		global $maxrecs;

		$this->aseco = $aseco_ext;
		$this->aseco->console('[RASP] Loading config file [' . $config_file . ']');
		if (!$this->settings = $this->xmlparse($config_file)) {
			trigger_error('{RASP_ERROR} Could not read/parse config file ' . $config_file . ' !', E_USER_ERROR);
		} else {
			$this->aseco->console('[RASP] Checking database structure...');
			if (!$this->checkTables()) {
				trigger_error('{RASP_ERROR} Table structure incorrect!  Use localdb/rasp.sql to correct this', E_USER_ERROR);
			}
			$this->aseco->console('[RASP] ...Structure OK!');
			$this->aseco->server->records->setLimit($maxrecs);
			$this->cleanData();
		}
	}  // start

	function xmlparse($config_file) {

		if ($settings = $this->aseco->xml_parser->parseXml($config_file)) {
			$this->messages = $settings['RASP']['MESSAGES'][0];
			return $settings;
		} else {
			return false;
		}
	}  // xmlparse

	function checkTables() {

		// create rs_* tables if needed
		$query = 'CREATE TABLE IF NOT EXISTS `rs_karma` (
		           `Id` int(11) NOT NULL auto_increment,
		           `MapId` mediumint(9) NOT NULL default 0,
		           `PlayerId` mediumint(9) NOT NULL default 0,
		           `Score` tinyint(3) NOT NULL default 0,
		           PRIMARY KEY (`Id`),
		           UNIQUE KEY `PlayerId` (`PlayerId`,`MapId`),
		           KEY `MapId` (`MapId`)
		         ) ENGINE=MyISAM';
		mysql_query($query);

		$query = 'CREATE TABLE IF NOT EXISTS `rs_rank` (
		           `PlayerId` mediumint(9) NOT NULL default 0,
		           `Avg` float NOT NULL default 0,
		           KEY `PlayerId` (`PlayerId`)
		         ) ENGINE=MyISAM';
		mysql_query($query);
  /*
		$query = 'CREATE TABLE IF NOT EXISTS `rs_times` (
		           `Id` int(11) NOT NULL auto_increment,
		           `MapId` mediumint(9) NOT NULL default 0,
		           `PlayerId` mediumint(9) NOT NULL default 0,
		           `Score` int(11) NOT NULL default 0,
		           `Date` int(10) unsigned NOT NULL default 0,
		           `Checkpoints` text NOT NULL,
		           PRIMARY KEY (`Id`),
		           KEY `PlayerId` (`PlayerId`,`MapId`),
		           KEY `MapId` (`MapId`)
		         ) ENGINE=MyISAM';
		mysql_query($query);     */

		// check for rs_* tables
		$tables = array();
		$res = mysql_query('SHOW TABLES');
		while ($row = mysql_fetch_row($res))
			$tables[] = $row[0];
		mysql_free_result($res);
		$check = array();
		$check[1] = in_array('rs_rank', $tables);
	//	$check[2] = in_array('rs_times', $tables);
		$check[3] = in_array('rs_karma', $tables);

		return ($check[1] && $check[3]);
	}  // checkTables

	function cleanData () {
		global $prune_records_times;

		$this->aseco->console('[RASP] Cleaning up unused data');
		$sql = "DELETE FROM maps WHERE Uid=''";
		mysql_query($sql);
		$sql = "DELETE FROM players WHERE Login=''";
		mysql_query($sql);
	}  // cleanData

	function getMaps() {

		// get new/cached list of maps
		$newlist = getMapsCache($this->aseco);  // from rasp.funcs.php

		foreach ($newlist as $row) {
			$tid = $this->aseco->getMapId($row['UId']);
			// insert in case it wasn't in the database yet
			if ($tid == 0) {
				$query = 'INSERT INTO maps (Uid, Name, Author, Environment)
				          VALUES (' . quotedString($row['UId']) . ', ' . quotedString($row['Name']) . ', '
				                    . quotedString($row['Author']) . ', ' . quotedString($row['Environnement']) . ')';
				mysql_query($query);
				if (mysql_affected_rows() != 1) {
					trigger_error('{RASP_ERROR} Could not insert map! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
				} else {
					$tid = mysql_insert_id();
				}
			}
			if ($tid != 0)
				$tlist[] = $tid;
		}

		// check for missing map list
		if (empty($tlist)) {
			trigger_error('{RASP_ERROR} Cannot obtain map list from server and/or database - check configuration files!', E_USER_ERROR);
		}
		$this->maps = $tlist;
	}  // getMaps

	// called @ onSync
	function onSync($aseco, $data) {
		global $mxdir, $mxtmpdir, $feature_mxadd;

		$sepchar = substr($aseco->server->mapdir, -1, 1);
		if ($sepchar == '\\') {
			$mxdir = str_replace('/', $sepchar, $mxdir);
		}

		if (!file_exists($aseco->server->mapdir . $mxdir)) {
			if (!mkdir($aseco->server->mapdir . $mxdir)) {
				$aseco->console_text('{RASP_ERROR} MX Directory (' . $aseco->server->mapdir . $mxdir . ') cannot be created');
			}
		}

		if (!is_writeable($aseco->server->mapdir . $mxdir)) {
			$aseco->console_text('{RASP_ERROR} MX Directory (' . $aseco->server->mapdir . $mxdir . ') cannot be written to');
		}

		// check if user /add votes are enabled
		if ($feature_mxadd) {
			if (!file_exists($aseco->server->mapdir . $mxtmpdir)) {
				if (!mkdir($aseco->server->mapdir . $mxtmpdir)) {
					$aseco->console_text('{RASP_ERROR} MXtmp Directory (' . $aseco->server->mapdir . $mxtmpdir . ') cannot be created');
					$feature_mxadd = false;
				}
			}

			if (!is_writeable($aseco->server->mapdir . $mxtmpdir)) {
				$aseco->console_text('{RASP_ERROR} MXtmp Directory (' . $aseco->server->mapdir . $mxtmpdir . ') cannot be written to');
				$feature_mxadd = false;
			}
		}
	}  // onSync

	function resetRanks() { 
	  global $aseco, $minrank;
  	$players = array();
		$this->aseco->console('[RASP] Calculating ranks...');

		// erase old average data
		mysql_query('TRUNCATE TABLE rs_rank');
		
		// get list of players 

		//$query = 'SELECT Id, Hits, GotHits, Captures FROM players WHERE Hits > 50';    


    $query = 'SELECT Id, AllPoints FROM players WHERE AllPoints > ' . $minrank;   
   
   //  $query = 'SELECT Id, AllPoints FROM players';          /* UPDATE TEST */
     
    $i=0;
		$res = mysql_query($query);
		while ($row = mysql_fetch_assoc($res)) {
			$players[$i] = $row; 
			$i++;
		}
		mysql_free_result($res);     
  
    $pcnt=$i;
  

    if (!empty($players)) {
      
 			$query = 'INSERT INTO rs_rank VALUES ';
			// compute each player's new average score
			foreach ($players as $player) {
       	$query .= '(' . $player['Id'] . ',' . $player['AllPoints'] . '),';       
			}
			$query = substr($query, 0, strlen($query)-1);  // strip trailing ','

			mysql_query($query);
	
  		if (mysql_affected_rows() < 1) {
				trigger_error('{RASP_ERROR} Could not insert any player averages! (' . mysql_error() . ')', E_USER_WARNING);
			} elseif (mysql_affected_rows() != count($players)) {
				trigger_error('{RASP_ERROR} Could not insert all ' . count($players) . ' player averages! (' . mysql_error() . ')', E_USER_WARNING);
				// increase MySQL's max_allowed_packet setting
			}
		}
		$this->aseco->console('[RASP] ...Done!');
             
    	
	}  // resetRanks  

	// called @ onPlayerConnect
	function onPlayerjoin($aseco, $player) {
		global $feature_ranks, $feature_stats, $always_show_pb;

		if ($feature_ranks)
		{
			$this->showRank($player->login);
			chat_nextrank($aseco, 1, $player->login, $player->id);
		}
		if ($feature_stats)
			$this->showPb($player, $aseco->server->map->id, $always_show_pb);   
	}  // onPlayerjoin

	function showPb($player, $map, $always_show) {
	}  // showPb

	function getPb($login, $map) {
	}  // getPb

	function showRank($login) {
		global $minrank, $aseco;

		$pid = $this->aseco->getPlayerId($login);
		$query = 'SELECT Avg FROM rs_rank
		          WHERE PlayerId=' . $pid;
		$res = mysql_query($query);
		if (mysql_num_rows($res) > 0) {
			$row = mysql_fetch_array($res);
			$query2 = 'SELECT PlayerId FROM rs_rank ORDER BY Avg DESC';
			$res2 = mysql_query($query2);
			$rank = 1;
			while ($row2 = mysql_fetch_array($res2)) {
				if ($row2['PlayerId'] == $pid) break;
				$rank++;
			}
			$message = formatText($this->messages['RANK'][0],
			                      $rank, mysql_num_rows($res2),
			                      $row['Avg']);
			              /*        sprintf("%4.1F", $row['Avg'] / 10000));     */
			$message = $this->aseco->formatColors($message);
			$aseco->console($message) ;
			$this->aseco->client->query('ChatSendServerMessageToLogin', $message, $login);
			mysql_free_result($res2);
		} else {
			$message = formatText($this->messages['RANK_NONE'][0], $minrank); //40 -> minrank
			$message = $this->aseco->formatColors($message);
			$aseco->console($message) ;			
			$this->aseco->client->query('ChatSendServerMessageToLogin', $message, $login);
		}
		mysql_free_result($res);   
       	 
	}  // showRank

	function getRank($login) {
       
		$pid = $this->aseco->getPlayerId($login);
		$query = 'SELECT Avg FROM rs_rank
		          WHERE PlayerId=' . $pid;
		$res = mysql_query($query);
		if (mysql_num_rows($res) > 0) {
			$row = mysql_fetch_array($res);
			$query2 = 'SELECT PlayerId FROM rs_rank ORDER BY Avg DESC';
			$res2 = mysql_query($query2);
			$rank = 1;
			while ($row2 = mysql_fetch_array($res2)) {
				if ($row2['PlayerId'] == $pid) break;
				$rank++;
			}
			$message = formatText('{1}/{2}',
			                      $rank, mysql_num_rows($res2));
			mysql_free_result($res2);
		} else {
			$message = 'None';
		}
		mysql_free_result($res);
		

		
		return $message;  
	}  // getRank

	// called @ onPlayerFinish
	function onFinish($aseco, $finish_item) {
		global $feature_stats,
		       $checkpoints;  // from plugin.checkpoints.php

		// check for actual finish & no Laps mode
		if ($feature_stats && $finish_item->score > 0 && $aseco->server->gameinfo->mode != Gameinfo::LAPS) {
			$this->insertTime($finish_item, isset($checkpoints[$finish_item->player->login]) ?
			                  implode(',', $checkpoints[$finish_item->player->login]->curr_cps) : '');
		}
	}  // onFinish

	// called @ onBeginMap2
	function onBeginMap($aseco, $map) {
		global $feature_karma, $feature_stats, $always_show_pb, $karma_show_start, $karma_show_votes;

		if ($feature_stats && !$aseco->server->isrelay) {
			foreach ($aseco->server->players->player_list as $pl)
				$this->showPb($pl, $map->id, $always_show_pb);
		}
		if ($feature_karma && $karma_show_start &&
		    function_exists('rasp_karma')) {
			// show players' actual votes, or global karma message?
			if ($karma_show_votes) {
				// send individual player messages
				foreach ($aseco->server->players->player_list as $pl)
					rasp_karma($map->id, $pl->login);
			} else {
				// send more efficient global message
				rasp_karma($map->id, false);
			}
		}
	}  // onBeginMap


	// called @ onEndMap
	function onEndMap($aseco, $data) {
		global $feature_ranks, $mxplayed;
    //$command=array();
		// check for relay server
		if ($aseco->server->isrelay) return;
    $feature_ranks=true;
		if ($feature_ranks) {
		//	if (!$mxplayed) {
				$this->resetRanks();
		//	}
	//		if (!$aseco->settings['sb_stats_panels']) {
	$pcnt=0;
				foreach ($aseco->server->players->player_list as $pl)
				{
					$this->showRank($pl->login); 
   
					chat_nextrank($aseco, 1, $pl->login, $pl->id);
					$pcnt++;
				}
				$aseco->releaseEvent('onRankBuilded',$pcnt); 		
	//		}
		}
	}  // onEndMap

}  // class Rasp

// These functions pass the callback data to the Rasp class...
function event_onsync($aseco, $data) { global $rasp; $rasp->onSync($aseco, $data); }
function event_finish($aseco, $data) { global $rasp; $rasp->onFinish($aseco, $data); }
function event_beginmap($aseco, $data) { global $rasp; $rasp->onBeginMap($aseco, $data); }
function event_endmap($aseco, $data) { global $rasp; $rasp->onEndMap($aseco, $data); }
function event_playerjoin($aseco, $data) { global $rasp; $rasp->onPlayerjoin($aseco, $data); }


// Chat commands...


function chat_rank($aseco, $command) {
	global $rasp, $feature_ranks;

	if ($feature_ranks) {
		$rasp->showRank($command['author']->login);
	}
}  // chat_rank

function chat_top10($aseco, $command) {

	$player = $command['author'];

	$header = 'Current TOP 10 Players:';
	$recs = array();
	$top = 10;
	$bgn = '{#black}';  // nickname begin

	$query = 'SELECT p.NickName, r.Avg FROM players p
	          LEFT JOIN rs_rank r ON (p.Id=r.PlayerId)
	          WHERE r.Avg!=0 ORDER BY r.Avg DESC LIMIT ' . $top;
	$res = mysql_query($query);

	if (mysql_num_rows($res) == 0) {
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}No ranked players found!'), $player->login);
		mysql_free_result($res);
		return;
	}

	$i = 1;
	while ($row = mysql_fetch_object($res)) {
		$nick = $row->NickName;
		if (!$aseco->settings['lists_colornicks'])
			$nick = stripColors($nick);
		$recs[] = array($i . '.',
		                $bgn . $nick, $row->Avg);
		                
		                /*sprintf("%4.1F", $row->Avg / 10000));             */
		$i++;
	}

	// reserve extra width for $w tags
	$extra = ($aseco->settings['lists_colornicks'] ? 0.2 : 0);
	// display ManiaLink message
	display_manialink($player->login, $header, array('BgRaceScore2', 'LadderRank'), $recs, array(0.7+$extra, 0.1, 0.45+$extra, 0.15), 'OK');

	mysql_free_result($res);
}  // chat_top10

function chat_top100($aseco, $command) {

	$player = $command['author'];

	$head = 'Current TOP 100 Players:';
	$top = 100;
	$bgn = '{#black}';  // nickname begin

	$query = 'SELECT p.NickName, r.Avg FROM players p
	          LEFT JOIN rs_rank r ON (p.Id=r.PlayerId)
	          WHERE r.Avg!=0 ORDER BY r.Avg DESC LIMIT ' . $top;
	$res = mysql_query($query);

	if (mysql_num_rows($res) == 0) {
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}No ranked players found!'), $player->login);
		mysql_free_result($res);
		return;
	}

	$recs = array();
	$lines = 0;
	$player->msgs = array();
	// reserve extra width for $w tags
	$extra = ($aseco->settings['lists_colornicks'] ? 0.2 : 0);
	$player->msgs[0] = array(1, $head, array(0.7+$extra, 0.1, 0.45+$extra, 0.15), array('BgRaceScore2', 'LadderRank'));
	$i = 1;
	while ($row = mysql_fetch_object($res)) {
		$nick = $row->NickName;
		if (!$aseco->settings['lists_colornicks'])
			$nick = stripColors($nick);
		$recs[] = array(str_pad($i, 2, '0', STR_PAD_LEFT) . '.',
		                $bgn . $nick, $row->Avg);
		             /*   sprintf("%4.1F", $row->Avg / 10000));        */
		$i++;
		if (++$lines > 14) {
			$player->msgs[] = $recs;
			$lines = 0;
			$recs = array();
		}
	}
	// add if last batch exists
	if (!empty($recs))
		$player->msgs[] = $recs;

	// display ManiaLink message
	display_manialink_multi($player);

	mysql_free_result($res);
}  // chat_top100

function chat_topwins($aseco, $command) {

	$player = $command['author'];

	$head = 'Current TOP 100 Victors:';
	$top = 100;
	$bgn = '{#black}';  // nickname begin

	$query = 'SELECT NickName, Wins FROM players ORDER BY Wins DESC LIMIT ' . $top;
	$res = mysql_query($query);

	$wins = array();
	$i = 1;
	$lines = 0;
	$player->msgs = array();
	// reserve extra width for $w tags
	$extra = ($aseco->settings['lists_colornicks'] ? 0.2 : 0);
	$player->msgs[0] = array(1, $head, array(0.7+$extra, 0.1, 0.45+$extra, 0.15), array('BgRaceScore2', 'LadderRank'));
	if (mysql_num_rows($res) > 0) {
		while ($row = mysql_fetch_object($res)) {
			$nick = $row->NickName;
			if (!$aseco->settings['lists_colornicks'])
				$nick = stripColors($nick);
			$wins[] = array(str_pad($i, 2, '0', STR_PAD_LEFT) . '.',
			                $bgn . $nick,
			                $row->Wins);
			$i++;
			if (++$lines > 14) {
				$player->msgs[] = $wins;
				$lines = 0;
				$wins = array();
			}
		}
	}
	// add if last batch exists
	if (!empty($wins))
		$player->msgs[] = $wins;

	// display ManiaLink message
	display_manialink_multi($player);

	mysql_free_result($res);
}  // chat_topwins

function chat_active($aseco, $command) {

	$player = $command['author'];

	$head = 'TOP 100 Most Active Players:';
	$top = 100;
	$bgn = '{#black}';  // nickname begin

	$query = 'SELECT NickName, TimePlayed FROM players ORDER BY TimePlayed DESC LIMIT ' . $top;
	$res = mysql_query($query);

	$active = array();
	$i = 1;
	$lines = 0;
	$player->msgs = array();
	// reserve extra width for $w tags
	$extra = ($aseco->settings['lists_colornicks'] ? 0.2 : 0);
	$player->msgs[0] = array(1, $head, array(0.8+$extra, 0.1, 0.45+$extra, 0.25), array('BgRaceScore2', 'LadderRank'));
	while ($row = mysql_fetch_object($res)) {
		$nick = $row->NickName;
		if (!$aseco->settings['lists_colornicks'])
			$nick = stripColors($nick);
		$active[] = array(str_pad($i, 2, '0', STR_PAD_LEFT) . '.',
		                  $bgn . $nick,
		                  formatTimeH($row->TimePlayed * 1000, false));
		$i++;
		if (++$lines > 14) {
			$player->msgs[] = $active;
			$lines = 0;
			$active = array();
		}
	}
	// add if last batch exists
	if (!empty($active))
		$player->msgs[] = $active;

	// display ManiaLink message
	display_manialink_multi($player);

	mysql_free_result($res);
}  // chat_active


// Starts the rasp plugin...

// called @ onStartup
function event_onstartup($aseco) {
	global $rasp;

	$rasp = new Rasp();
	$rasp->start($aseco, 'rasp.xml');

}  // event_onstartup
?>
