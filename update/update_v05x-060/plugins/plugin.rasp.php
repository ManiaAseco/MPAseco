<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * RASP plugin.
 * Provides rank & personal best handling, and related chat commands.
 * Updated by Xymph
 *  edited for SM 15.02.2013 by kremsy and the MP-Aseco Team
 *  v15.02.2013
 * Dependencies: requires plugin.rasp_karma.php
 */

Aseco::registerEvent('onStartup', 'event_onstartup');
Aseco::registerEvent('onSync', 'event_onsync');
Aseco::registerEvent('onBeginMap2', 'event_beginmap');  // use 2nd event for logical ordering of rank/karma messages
Aseco::registerEvent('onEndMap', 'event_endmap');
Aseco::registerEvent('onPlayerFinish', 'event_finish');
Aseco::registerEvent('onPlayerConnect', 'event_playerjoin');

if (!INHIBIT_RECCMDS && $aseco->records_active) {
	Aseco::addChatCommand('pb', 'Shows your personal best on current map');
}
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
  var $records_active;
  
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
		
	  /* Check if records file exists and time_based Ranking is on */
		if(!empty($this->aseco->settings['records_activated']))
		  $this->records_active = $this->aseco->settings['records_activated'];
		else
		  $this->records_active = false; 
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
		mysql_query($query);

		// check for rs_* tables
		$tables = array();
		$res = mysql_query('SHOW TABLES');
		while ($row = mysql_fetch_row($res))
			$tables[] = $row[0];
		mysql_free_result($res);
		$check = array();
		$check[1] = in_array('rs_rank', $tables);
		$check[2] = in_array('rs_times', $tables);
		$check[3] = in_array('rs_karma', $tables);

		return ($check[1] && $check[2] && $check[3]);
	}  // checkTables

	function cleanData () {
		global $prune_records_times;

		$this->aseco->console('[RASP] Cleaning up unused data');
		$sql = "DELETE FROM maps WHERE Uid=''";
		mysql_query($sql);
		$sql = "DELETE FROM players WHERE Login=''";
		mysql_query($sql);

		if (!$prune_records_times) return;
		// prune records and rs_times entries for players & maps deleted from database

		$deletelist = array();
		$sql = 'SELECT DISTINCT r.MapId,m.Id FROM records r LEFT JOIN maps m ON (r.MapId=m.Id) WHERE m.Id IS NULL';
		$res = mysql_query($sql);
		if (mysql_num_rows($res) > 0) {
			while ($row = mysql_fetch_row($res))
				$deletelist[] = $row[0];
			$this->aseco->console('[RASP] ...Deleting records for deleted maps: ' . implode(',', $deletelist));
			$sql = 'DELETE FROM records WHERE MapId IN (' . implode(',', $deletelist) . ')';
			mysql_query($sql);
		}
		mysql_free_result($res);

		$deletelist = array();
		$sql = 'SELECT DISTINCT r.PlayerId,p.Id FROM records r LEFT JOIN players p ON (r.PlayerId=p.Id) WHERE p.Id IS NULL';
		$res = mysql_query($sql);
		if (mysql_num_rows($res) > 0) {
			while ($row = mysql_fetch_row($res))
				$deletelist[] = $row[0];
			$this->aseco->console('[RASP] ...Deleting records for deleted players: ' . implode(',', $deletelist));
			$sql = 'DELETE FROM records WHERE PlayerId IN (' . implode(',', $deletelist) . ')';
			mysql_query($sql);
		}
		mysql_free_result($res);

		$deletelist = array();
		$sql = 'SELECT DISTINCT r.MapId,m.Id FROM rs_times r LEFT JOIN maps m ON (r.MapId=m.Id) WHERE m.Id IS NULL';
		$res = mysql_query($sql);
		if (mysql_num_rows($res) > 0) {
			while ($row = mysql_fetch_row($res))
				$deletelist[] = $row[0];
			$this->aseco->console('[RASP] ...Deleting rs_times for deleted maps: ' . implode(',', $deletelist));
			$sql = 'DELETE FROM rs_times WHERE MapId IN (' . implode(',', $deletelist) . ')';
			mysql_query($sql);
		}
		mysql_free_result($res);

		$deletelist = array();
		$sql = 'SELECT DISTINCT r.PlayerId,p.Id FROM rs_times r LEFT JOIN players p ON (r.PlayerId=p.Id) WHERE p.Id IS NULL';
		$res = mysql_query($sql);
		if (mysql_num_rows($res) > 0) {
			while ($row = mysql_fetch_row($res))
				$deletelist[] = $row[0];
			$this->aseco->console('[RASP] ...Deleting rs_times for deleted players: ' . implode(',', $deletelist));
			$sql = 'DELETE FROM rs_times WHERE PlayerId IN (' . implode(',', $deletelist) . ')';
			mysql_query($sql);
		}
		mysql_free_result($res);
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
	  global $minrank, $maxrecs;
  	$players = array();
		$this->aseco->console('[RASP] Calculating ranks...');
		$this->getMaps();
		$maps = $this->maps;
		$total = count($maps);
		
		// erase old average data
		mysql_query('TRUNCATE TABLE rs_rank');
		
		
		if($this->records_active){
  		// get list of players with at least $minrecs records (possibly unranked)
  		$query = 'SELECT PlayerId, COUNT(*) AS Cnt
  		          FROM records
  		          GROUP BY PlayerId
  		          HAVING Cnt >=' . $minrank;
  		$res = mysql_query($query);
  		while ($row = mysql_fetch_object($res)) {
  			$players[$row->PlayerId] = array(0, 0);  // sum, count
  		}
  		mysql_free_result($res);    
    }else{
      $query = 'SELECT Id, AllPoints FROM players WHERE AllPoints > ' . $minrank;       
      $i=0; 
  		$res = mysql_query($query);
  		while ($row = mysql_fetch_assoc($res)) {
  			$players[$i] = $row; 
  			$i++;
  		}
  		mysql_free_result($res);     
      $pcnt=$i;         
    }

    if(!empty($players) && !$this->records_active) { //Point Ranks
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
		}else if(!empty($players) && $this->records_active){ //Timed Ranks
			// get ranked records for all maps    
      $order = 'ASC';
			foreach ($maps as $map) {
				$query = 'SELECT PlayerId FROM records
				          WHERE MapId=' . $map . '
				          ORDER BY Score ' . $order . ', Date ASC
				          LIMIT ' . $maxrecs;
				$res = mysql_query($query);
				if (mysql_num_rows($res) > 0) {
					$i = 1;
					while ($row = mysql_fetch_object($res)) {
						$pid = $row->PlayerId;
						if (isset($players[$pid])) {
							$players[$pid][0] += $i;
							$players[$pid][1] ++;
						}
						$i++;
					}
				}
				mysql_free_result($res);      
				}
  			// one-shot insert for queries up to 1 MB (default max_allowed_packet),
  			// or about 75K rows at 14 bytes/row (avg)
  			$query = 'INSERT INTO rs_rank VALUES ';
  			// compute each player's new average score
  			foreach ($players as $player => $ranked) {
  				// ranked maps sum + $maxrecs rank for all remaining maps
  				$avg = ($ranked[0] + ($total - $ranked[1]) * $maxrecs) / $total;
  				$query .= '(' . $player . ',' . round($avg * 10000) . '),';
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
		global $maxrecs, $maxavg;

		$found = false;
		// find ranked record
		for ($i = 0; $i < $maxrecs; $i++) {
			if (($rec = $this->aseco->server->records->getRecord($i)) !== false) {
				if ($rec->player->login == $player->login) {
					$ret['time'] = $rec->score;
					$ret['rank'] = $i + 1;
					$found = true;
					break;
				}
			} else {
				break;
			}
		}

		// check whether to show PB (e.g. for /pb)
		if (!$always_show) {
			// check for ranked record that's already shown at map start,
			// or for player's records panel showing it
			if (($found && $this->aseco->settings['show_recs_before'] == 2) ||
			    $player->panels['records'] != '')
				return;
		}

		if (!$found) {
			// find unranked time/score
			$order = ($this->aseco->server->gameinfo->mode == Gameinfo::STNT ? 'DESC' : 'ASC');
			$query2 = 'SELECT Score FROM rs_times
			           WHERE PlayerId=' . $player->id . ' AND MapId=' . $map . '
			           ORDER BY Score ' . $order . ' LIMIT 1';
			$res2 = mysql_query($query2);
			if (mysql_num_rows($res2) > 0) {
				$row = mysql_fetch_object($res2);
				$ret['time'] = $row->Score;
				$ret['rank'] = '$nUNRANKED$m';
				$found = true;
			}
			mysql_free_result($res2);
		}

		// compute average time of last $maxavg times
		$query = 'SELECT Score FROM rs_times
		          WHERE PlayerId=' . $player->id . ' AND MapId=' . $map . '
		          ORDER BY Date DESC LIMIT ' . $maxavg;
		$res = mysql_query($query);
		$size = mysql_num_rows($res);
		if ($size > 0) {
			$total = 0;
			while ($row = mysql_fetch_object($res)) {
				$total += $row->Score;
			}
			$avg = floor($total / $size);
			if ($this->aseco->server->gameinfo->mode != Gameinfo::STNT)
				$avg = formatTime($avg);
		} else {
			$avg = 'No Average';
		}
		mysql_free_result($res);

		if ($found) {
			$message = formatText($this->messages['PB'][0],
			                      ($this->aseco->server->gameinfo->mode == Gameinfo::STNT ?
			                       $ret['time'] : formatTime($ret['time'])),
			                      $ret['rank'], $avg);
			$message = $this->aseco->formatColors($message);
			$this->aseco->client->query('ChatSendServerMessageToLogin', $message, $player->login);
		} else {
			$message = $this->messages['PB_NONE'][0];
			$message = $this->aseco->formatColors($message);
			$this->aseco->client->query('ChatSendServerMessageToLogin', $message, $player->login);
		}
	}  // showPb

	function getPb($login, $map) {
		global $maxrecs;
		if(!$this->records_active)
		  return 0;
		$found = false;
		// find ranked record
		for ($i = 0; $i < $maxrecs; $i++) {
			if (($rec = $this->aseco->server->records->getRecord($i)) !== false) {
				if ($rec->player->login == $login) {
					$ret['time'] = $rec->score;
					$ret['rank'] = $i + 1;
					$found = true;
					break;
				}
			} else {
				break;
			}
		}

		if (!$found) {
			$pid = $this->aseco->getPlayerId($login);
			// find unranked time/score
			$order = ($this->aseco->server->gameinfo->mode == Gameinfo::STNT ? 'DESC' : 'ASC');
			$query2 = 'SELECT Score FROM rs_times
			           WHERE PlayerId=' . $pid . ' AND MapId=' . $map . '
			           ORDER BY Score ' . $order . ' LIMIT 1';
			$res2 = mysql_query($query2);
			if (mysql_num_rows($res2) > 0) {
				$row = mysql_fetch_object($res2);
				$ret['time'] = $row->Score;
				$ret['rank'] = '$nUNRANKED$m';
			} else {
				$ret['time'] = 0;
				$ret['rank'] = '$nNONE$m';
			}
			mysql_free_result($res2);
		}

		return $ret;	
	}  // getPb

	function showRank($login) {
		global $minrank;

		if($this->records_active){
      $order = "ASC";
    }else{
      $order = "DESC";
    }                
              
		$pid = $this->aseco->getPlayerId($login);
		$query = 'SELECT Avg FROM rs_rank
		          WHERE PlayerId=' . $pid;
		$res = mysql_query($query);

		if (mysql_num_rows($res) > 0) {
			$row = mysql_fetch_array($res);
			$query2 = 'SELECT PlayerId FROM rs_rank ORDER BY Avg '.$order.'';
			$res2 = mysql_query($query2);
			$rank = 1;
			while ($row2 = mysql_fetch_array($res2)) {
				if ($row2['PlayerId'] == $pid) break;
				$rank++;
			}
			if($this->records_active){
			$message = formatText($this->messages['RANK'][0],
			                      $rank, mysql_num_rows($res2),
                            sprintf("%4.1F", $row['Avg'] / 10000));          
      }else{
			$message = formatText($this->messages['RANK'][0],
			                      $rank, mysql_num_rows($res2),
			                      $row['Avg']);      
      }

			$message = $this->aseco->formatColors($message);
			$this->aseco->client->query('ChatSendServerMessageToLogin', $message, $login);
			mysql_free_result($res2);
		} else {
			$message = formatText($this->messages['RANK_NONE'][0], $minrank); //40 -> minrank
			$message = $this->aseco->formatColors($message);
			$this->aseco->client->query('ChatSendServerMessageToLogin', $message, $login);
		}
		mysql_free_result($res);   
       	 
	}  // showRank

	function getRank($login) {
		if($this->records_active){
      $order = "ASC";
    }else{
      $order = "DESC";
    }     
           
		$pid = $this->aseco->getPlayerId($login);
		$query = 'SELECT Avg FROM rs_rank
		          WHERE PlayerId=' . $pid;
		$res = mysql_query($query);
		if (mysql_num_rows($res) > 0) {
			$row = mysql_fetch_array($res);
			$query2 = 'SELECT PlayerId FROM rs_rank ORDER BY Avg '.$order.'';
			$res2 = mysql_query($query2);
			$rank = 1;
			while ($row2 = mysql_fetch_array($res2)) {
				if ($row2['PlayerId'] == $pid) break;
				$rank++;
			}
			if($this->records_active){
		  	$message = formatText('{1}/{2} Avg: {3}',
			                      $rank, mysql_num_rows($res2),
			                      sprintf("%4.1F", $row['Avg'] / 10000));      
      }else{			
			 $message = formatText('{1}/{2}',$rank, mysql_num_rows($res2));
			}
			
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

	function insertTime($time, $cps) {

		$pid = $time->player->id;
		if ($pid != 0) {
			$query = 'INSERT INTO rs_times (PlayerId, MapId, Score, Date, Checkpoints)
			          VALUES (' . $pid . ', ' . $time->map->id . ', ' . $time->score . ', '
			                    . quotedString(time()) . ', ' . quotedString($cps) . ')';
			mysql_query($query);
			if (mysql_affected_rows() != 1) {
				trigger_error('{RASP_ERROR} Could not insert time! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
			}
		} else {
			trigger_error('{RASP_ERROR} Could not get Player ID for ' . $time->player->login . ' !', E_USER_WARNING);
		}
	}  // insertTime

	function deleteTime($cid, $pid) {

		$query = 'DELETE FROM rs_times WHERE MapId=' . $cid . ' AND PlayerId=' . $pid;
		mysql_query($query);
		if (mysql_affected_rows() <= 0) {
			trigger_error('{RASP_ERROR} Could not remove time(s)! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
		}
	}  // deleteTime

	// called @ onEndMap
	function onEndMap($aseco, $data) {
		global $feature_ranks, $mxplayed;
		// check for relay server
		if ($aseco->server->isrelay) return;
		if ($feature_ranks) {
		  if (!$mxplayed) {
				$this->resetRanks();
		  }
	  //if (!$aseco->settings['sb_stats_panels']) {
	    $pcnt=0;
			foreach ($aseco->server->players->player_list as $pl){
  			$this->showRank($pl->login); 
        chat_nextrank($aseco, 1, $pl->login, $pl->id);
  			$pcnt++;
			}
			$aseco->releaseEvent('onRankBuilded',$pcnt); 		
   	//}
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

function chat_pb($aseco, $command) {
	global $rasp, $feature_stats;
  if(!$aseco->records_active)
    return;
	// check for relay server
	if ($aseco->server->isrelay) {
		$message = formatText($aseco->getChatMessage('NOTONRELAY'));
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
		return;
	}

	if ($feature_stats) {
		$rasp->showPb($command['author'], $aseco->server->map->id, true);
	}
}  // chat_pb

function chat_rank($aseco, $command) {
	global $rasp, $feature_ranks;

	if ($feature_ranks) {
		$rasp->showRank($command['author']->login);
	}
}  // chat_rank

function chat_top10($aseco, $command) {
	if($aseco->settings['records_activated']) $order = "ASC";
  else $order = "DESC";
   
	$player = $command['author'];

	$header = 'Current TOP 10 Players:';
	$recs = array();
	$top = 10;
	$bgn = '{#black}';  // nickname begin

	$query = 'SELECT p.NickName, r.Avg FROM players p
	          LEFT JOIN rs_rank r ON (p.Id=r.PlayerId)
	          WHERE r.Avg!=0 ORDER BY r.Avg '.$order.' LIMIT ' . $top;
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
    if($aseco->settings['records_activated']){
  		$recs[] = array(str_pad($i, 2, '0', STR_PAD_LEFT) . '.',
  		                $bgn . $nick,
  		                sprintf("%4.1F", $row->Avg / 10000));
    }else{
  		$recs[] = array($i . '.', $bgn . $nick, $row->Avg);
		}                
		$i++;
	}

	// reserve extra width for $w tags
	$extra = ($aseco->settings['lists_colornicks'] ? 0.2 : 0);
	// display ManiaLink message
	display_manialink($player->login, $header, array('BgRaceScore2', 'LadderRank'), $recs, array(0.7+$extra, 0.1, 0.45+$extra, 0.15), 'OK');

	mysql_free_result($res);
}  // chat_top10

function chat_top100($aseco, $command) {
	if($aseco->settings['records_activated']) $order = "ASC";
  else $order = "DESC";
  
	$player = $command['author'];

	$head = 'Current TOP 100 Players:';
	$top = 100;
	$bgn = '{#black}';  // nickname begin

	$query = 'SELECT p.NickName, r.Avg FROM players p
	          LEFT JOIN rs_rank r ON (p.Id=r.PlayerId)
	          WHERE r.Avg!=0 ORDER BY r.Avg '.$order.' LIMIT ' . $top;
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
    if($aseco->settings['records_activated']){
  		$recs[] = array(str_pad($i, 2, '0', STR_PAD_LEFT) . '.',
  		                $bgn . $nick,
  		                sprintf("%4.1F", $row->Avg / 10000));
    }else{
  		$recs[] = array($i . '.', $bgn . $nick, $row->Avg);
		}      
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
	global $rasp, $prune_records_times;

	$rasp = new Rasp();
	$rasp->start($aseco, 'configs/plugins/rasp/rasp.xml');

	// prune records and rs_times entries for maps deleted from server
	if ($prune_records_times) {
		$aseco->console('[RASP] Pruning records/rs_times for deleted maps');
		$rasp->getMaps();
		$maps = $rasp->maps;

		// get list of maps IDs with records in the database
		$query = 'SELECT DISTINCT MapId FROM records';
		$res = mysql_query($query);
		while ($row = mysql_fetch_row($res)) {
			$map = $row[0];
			// delete records & rs_times if it's not in server's maps list
			if (!in_array($map, $maps)) {
				$aseco->console('[RASP] ...MapId: ' . $map);
				$query = 'DELETE FROM records WHERE MapId=' . $map;
				mysql_query($query);
				$query = 'DELETE FROM rs_times WHERE MapId=' . $map;
				mysql_query($query);
			}
		}
		mysql_free_result($res);
	}
}  // event_onstartup
?>
