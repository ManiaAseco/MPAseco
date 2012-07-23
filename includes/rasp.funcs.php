g<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Common functions for RASP
 * Updated by Xymph
 * edited for SM 23.07.2012 by kremsy and his MP-Team
 */

require_once('includes/gbxdatafetcher.inc.php');  // provides access to GBX data

Aseco::registerEvent('onChallengeListModified', 'clearMapsCache');
Aseco::registerEvent('onBeginMap2', 'initMapsCache');

global $mapListCache;
$mapListCache = array();

// called @ onChallengeListModified
function clearMapsCache($aseco, $data) {
	global $mapListCache;

	// clear cache if map list modified
	if ($data[2]) {
		$mapListCache = array();
		if ($aseco->debug)
			$aseco->console_text('maps cache cleared');
	}
}  // clearMapsCache

// called @ onBeginMap2
function initMapsCache($aseco, $map) {
	global $mapListCache;

	$mapListCache = array();
	getMapsCache($aseco);
	if ($aseco->debug)
		$aseco->console_text('maps cache inited: ' . count($mapListCache));
}  // initMapsCache

function getMapsCache($aseco) {
	global $mapListCache;

	if (empty($mapListCache)) {
		if ($aseco->debug)
			$aseco->console_text('maps cache loading...');
		// get new list of all maps
		$aseco->client->resetError();
		$newlist = array();
		$done = false;
		$size = 300;
		$i = 0;
		while (!$done) {
			$aseco->client->query('GetMapList', $size, $i);
			$maps = $aseco->client->getResponse();
			if (!empty($maps)) {
				if ($aseco->client->isError()) {
					// warning if no maps found
					if (empty($newlist))
						trigger_error('[' . $aseco->client->getErrorCode() . '] GetMapList - ' . $aseco->client->getErrorMessage() . ' - No maps found!', E_USER_WARNING);
					$done = true;
					break;
				}
				foreach ($maps as $trow) {
					// obtain various author fields too
					$mapinfo = getMapData($aseco->server->mapdir . $trow['FileName'], false);
					if ($mapinfo['name'] != 'file not found') {
						$trow['AuthorTime']  = $mapinfo['authortime'];
						$trow['AuthorScore'] = $mapinfo['authorscore'];
					}
					$trow['Name'] = stripNewlines($trow['Name']);
					$newlist[$trow['UId']] = $trow;
				}
				if (count($maps) < $size) {
					// got less than 300 maps, might as well leave
					$done = true;
				} else {
					$i += $size;
				}
			} else {
				$done = true;
			}
		}

		$mapListCache = $newlist;
		if ($aseco->debug)
			$aseco->console_text('maps cache loaded: ' . count($mapListCache));
	}

	return $mapListCache;
}  // getMapsCache


// calls function get_recs() from chat.records2.php
function getAllMaps($player, $wildcard, $env) {
	global $aseco, $jb_buffer, $maxrecs;

	$player->maplist = array();

	// get list of ranked records
//	$reclist = get_recs($player->id);
	// get new/cached list of maps
	$newlist = getMapsCache($aseco);

	$envids = array('Canyon' => 11, 'Valley' => 12, 'Storm' => 13);
	$head = 'Maps On This Server:';
	$msg = array();
	if ($aseco->server->packmask != 'Storm')
		$msg[] = array('Id', 'Rec', 'Name', 'Author', 'Env');
	else
		$msg[] = array('Id', 'Rec', 'Name', 'Author');
	$tid = 1;
	$lines = 0;
	$player->msgs = array();
	// reserve extra width for $w tags
	$extra = ($aseco->settings['lists_colormaps'] ? 0.2 : 0);
	if ($aseco->server->packmask != 'Storm')
		$player->msgs[0] = array(1, $head, array(1.39+$extra, 0.12, 0.1, 0.6+$extra, 0.4, 0.17), array('Icons128x128_1', 'NewTrack', 0.02));
	else
		$player->msgs[0] = array(1, $head, array(1.22+$extra, 0.12, 0.1, 0.6+$extra, 0.4), array('Icons128x128_1', 'NewTrack', 0.02));

	foreach ($newlist as $row) {
		// check for wildcard, map name or author name
		if ($wildcard == '*') {
			$pos = 0;
		} else {
			$pos = stripos(stripColors($row['Name']), $wildcard);
			if ($pos === false) {
				$pos = stripos($row['Author'], $wildcard);
			}
		}
		// check for environment
		if ($env == '*') {
			$pose = 0;
		} else {
			$pose = stripos($row['Environnement'], $env);
		}
		// check for any match
		if ($pos !== false && $pose !== false) {
			// store map in player object for jukeboxing
			$trkarr = array();
			$trkarr['name'] = $row['Name'];
			$trkarr['author'] = $row['Author'];
			$trkarr['environment'] = $row['Environnement'];
			$trkarr['filename'] = $row['FileName'];
			$trkarr['uid'] = $row['UId'];
			$player->maplist[] = $trkarr;

			// format map name
			$mapname = $row['Name'];
			if (!$aseco->settings['lists_colormaps'])
				$mapname = stripColors($mapname);
			// grey out if in history
			if (in_array($row['UId'], $jb_buffer))
				$mapname = '{#grey}' . stripColors($mapname);
			else {
				$mapname = '{#black}' . $mapname;
				// add clickable button
				if ($aseco->settings['clickable_lists'] && $tid <= 1900)
					$mapname = array($mapname, $tid+100);  // action id
			}
			// format author name
			$mapauthor = $row['Author'];
			// add clickable button
			if ($aseco->settings['clickable_lists'] && $tid <= 1900)
				$mapauthor = array($mapauthor, -100-$tid);  // action id
			// format env name
			$mapenv = $row['Environnement'];
			// add clickable button
			if ($aseco->settings['clickable_lists'])
				$mapenv = array($mapenv, $envids[$row['Environnement']]);  // action id

			// get corresponding record
			$pos = isset($reclist[$row['UId']]) ? $reclist[$row['UId']] : 0;
			$pos = ($pos >= 1 && $pos <= $maxrecs) ? str_pad($pos, 2, '0', STR_PAD_LEFT) : '-- ';

			if ($aseco->server->packmask != 'Storm')
				$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
				               $pos . '.', $mapname, $mapauthor, $mapenv);
			else
				$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
				               $pos . '.', $mapname, $mapauthor);
			$tid++;
			if (++$lines > 14) {
				$player->msgs[] = $msg;
				$lines = 0;
				$msg = array();
				if ($aseco->server->packmask != 'Storm')
					$msg[] = array('Id', 'Rec', 'Name', 'Author', 'Env');
				else
					$msg[] = array('Id', 'Rec', 'Name', 'Author');
			}
		}
	}
	// add if last batch exists
	if (count($msg) > 1)
		$player->msgs[] = $msg;
}  // getAllMaps

function getMapsByKarma($player, $karmaval) {
	global $aseco, $jb_buffer;

	$player->maplist = array();

	// get list of karma values for all matching maps
	$order = ($karmaval <= 0 ? 'ASC' : 'DESC');
	if ($karmaval == 0) {
		$sql = '(SELECT Uid, SUM(Score) AS Karma FROM maps m, rs_karma k
		         WHERE m.Id=k.MapId
		         GROUP BY Uid HAVING Karma = 0)
		        UNION
		        (SELECT Uid, 0 FROM maps WHERE Id NOT IN
		         (SELECT DISTINCT MapId FROM rs_karma))
		        ORDER BY Karma ' . $order;
	} else {
		$sql = 'SELECT Uid, SUM(Score) AS Karma FROM maps m, rs_karma k
		        WHERE m.Id=k.MapId
		        GROUP BY Uid
		        HAVING Karma ' . ($karmaval < 0 ? "<= $karmaval" : ">= $karmaval") . '
		        ORDER BY Karma ' . $order;
	}
	$result = mysql_query($sql);
	if (mysql_num_rows($result) == 0) {
		mysql_free_result($result);
		return;
	}

	// get new/cached list of maps
	$newlist = getMapsCache($aseco);

	$envids = array('Canyon' => 11, 'Valley' => 12, 'Storm' => 13);
	$head = 'Maps by Karma (' . $order . '):';
	$msg = array();
	if ($aseco->server->packmask != 'Storm')
		$msg[] = array('Id', 'Karma', 'Name', 'Author', 'Env');
	else
		$msg[] = array('Id', 'Karma', 'Name', 'Author');
	$tid = 1;
	$lines = 0;
	$player->msgs = array();
	// reserve extra width for $w tags
	$extra = ($aseco->settings['lists_colormaps'] ? 0.2 : 0);
	if ($aseco->server->packmask != 'Storm')
		$player->msgs[0] = array(1, $head, array(1.44+$extra, 0.12, 0.15, 0.6+$extra, 0.4, 0.17), array('Icons128x128_1', 'NewTrack', 0.02));
	else
		$player->msgs[0] = array(1, $head, array(1.27+$extra, 0.12, 0.15, 0.6+$extra, 0.4), array('Icons128x128_1', 'NewTrack', 0.02));

	while ($dbrow = mysql_fetch_array($result)) {
		// does the uid exist in the current server map list?
		if (array_key_exists($dbrow[0], $newlist)) {
			$row = $newlist[$dbrow[0]];
			// store map in player object for jukeboxing
			$trkarr = array();
			$trkarr['name'] = $row['Name'];
			$trkarr['author'] = $row['Author'];
			$trkarr['environment'] = $row['Environnement'];
			$trkarr['filename'] = $row['FileName'];
			$trkarr['uid'] = $row['UId'];
			$player->maplist[] = $trkarr;

			// format map name
			$mapname = $row['Name'];
			if (!$aseco->settings['lists_colormaps'])
				$mapname = stripColors($mapname);
			// grey out if in history
			if (in_array($row['UId'], $jb_buffer))
				$mapname = '{#grey}' . stripColors($mapname);
			else {
				$mapname = '{#black}' . $mapname;
			}
			// format author name
			$mapauthor = $row['Author'];
			// format karma
			$mapkarma = str_pad($dbrow[1], 4, '  ', STR_PAD_LEFT);
			// format env name
			$mapenv = $row['Environnement'];
			// add clickable button
			if ($aseco->settings['clickable_lists'])
				$mapenv = array($mapenv, $envids[$row['Environnement']]);

			// add clickable buttons
			if ($aseco->settings['clickable_lists'] && $tid <= 1900) {
				$mapname = array($mapname, $tid+100);  // action ids
				$mapauthor = array($mapauthor, -100-$tid);
				$mapkarma = array($mapkarma, -6000-$tid);
			}

			if ($aseco->server->packmask != 'Storm')
				$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
				               $mapkarma, $mapname, $mapauthor, $mapenv);
			else
				$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
				               $mapkarma, $mapname, $mapauthor);
			$tid++;
			if (++$lines > 14) {
				$player->msgs[] = $msg;
				$lines = 0;
				$msg = array();
				if ($aseco->server->packmask != 'Storm')
					$msg[] = array('Id', 'Karma', 'Name', 'Author', 'Env');
				else
					$msg[] = array('Id', 'Karma', 'Name', 'Author');
			}
		}
	}
	// add if last batch exists
	if (count($msg) > 1)
		$player->msgs[] = $msg;

	mysql_free_result($result);
}  // getMapsByKarma

function getMapsNoFinish($player) {
	global $aseco, $jb_buffer;

	$player->maplist = array();

	// get list of finished maps
	$sql = 'SELECT DISTINCT MapId FROM rs_times
	        WHERE PlayerId=' . $player->id . ' ORDER BY MapId';
	$result = mysql_query($sql);
	$finished = array();
	if (mysql_num_rows($result) > 0) {
		while ($dbrow = mysql_fetch_array($result))
			$finished[] = $dbrow[0];
	}
	mysql_free_result($result);

	// get list of unfinished maps
	// simpler but less efficient query:
	// $sql = 'SELECT uid FROM maps WHERE Id NOT IN
	//         (SELECT DISTINCT MapId FROM rs_times, players
	//          WHERE rs_times.PlayerId=players.Id AND players.Login=' . quotedString($player->login) . ')';
	$sql = 'SELECT Uid FROM maps';
	if (!empty($finished))
		$sql .= ' WHERE id NOT IN (' . implode(',', $finished) . ')';
	$result = mysql_query($sql);
	if (mysql_num_rows($result) == 0) {
		mysql_free_result($result);
		return;
	}

	// get new/cached list of maps
	$newlist = getMapsCache($aseco);

	$envids = array('Canyon' => 11, 'Valley' => 12, 'Storm' => 13);
	$head = 'Maps You Haven\'t Finished:';
	$msg = array();
	if ($aseco->server->packmask != 'Storm')
		$msg[] = array('Id', 'Name', 'Author', 'Env');
	else
		$msg[] = array('Id', 'Name', 'Author');
	$tid = 1;
	$lines = 0;
	$player->msgs = array();
	// reserve extra width for $w tags
	$extra = ($aseco->settings['lists_colormaps'] ? 0.2 : 0);
	if ($aseco->server->packmask != 'Storm')
		$player->msgs[0] = array(1, $head, array(1.29+$extra, 0.12, 0.6+$extra, 0.4, 0.17), array('Icons128x128_1', 'NewTrack', 0.02));
	else
		$player->msgs[0] = array(1, $head, array(1.12+$extra, 0.12, 0.6+$extra, 0.4), array('Icons128x128_1', 'NewTrack', 0.02));

	while ($dbrow = mysql_fetch_array($result)) {
		// does the uid exist in the current server map list?
		if (array_key_exists($dbrow[0], $newlist)) {
			$row = $newlist[$dbrow[0]];
			// store map in player object for jukeboxing
			$trkarr = array();
			$trkarr['name'] = $row['Name'];
			$trkarr['author'] = $row['Author'];
			$trkarr['environment'] = $row['Environnement'];
			$trkarr['filename'] = $row['FileName'];
			$trkarr['uid'] = $row['UId'];
			$player->maplist[] = $trkarr;

			// format map name
			$mapname = $row['Name'];
			if (!$aseco->settings['lists_colormaps'])
				$mapname = stripColors($mapname);
			// grey out if in history
			if (in_array($row['UId'], $jb_buffer))
				$mapname = '{#grey}' . stripColors($mapname);
			else {
				$mapname = '{#black}' . $mapname;
				// add clickable button
				if ($aseco->settings['clickable_lists'] && $tid <= 1900)
					$mapname = array($mapname, $tid+100);  // action id
			}
			// format author name
			$mapauthor = $row['Author'];
			// add clickable button
			if ($aseco->settings['clickable_lists'] && $tid <= 1900)
				$mapauthor = array($mapauthor, -100-$tid);  // action id
			// format env name
			$mapenv = $row['Environnement'];
			// add clickable button
			if ($aseco->settings['clickable_lists'])
				$mapenv = array($mapenv, $envids[$row['Environnement']]);  // action id

			if ($aseco->server->packmask != 'Storm')
				$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
				               $mapname, $mapauthor, $mapenv);
			else
				$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
				               $mapname, $mapauthor);
			$tid++;
			if (++$lines > 14) {
				$player->msgs[] = $msg;
				$lines = 0;
				$msg = array();
				if ($aseco->server->packmask != 'Storm')
					$msg[] = array('Id', 'Name', 'Author', 'Env');
				else
					$msg[] = array('Id', 'Name', 'Author');
			}
		}
	}
	// add if last batch exists
	if (count($msg) > 1)
		$player->msgs[] = $msg;

	mysql_free_result($result);
}  // getMapsNoFinish

function getMapsNoRank($player) {
	global $aseco, $jb_buffer, $maxrecs;

	$player->maplist = array();

	// get list of finished maps
	$sql = 'SELECT DISTINCT MapId FROM rs_times
	        WHERE PlayerId=' . $player->id . ' ORDER BY MapId';
	$result = mysql_query($sql);
	$finished = array();
	if (mysql_num_rows($result) > 0) {
		while ($dbrow = mysql_fetch_array($result))
			$finished[] = $dbrow[0];
	}
	mysql_free_result($result);

	// get list of finished maps
	// simpler but less efficient query:
	// $sql = 'SELECT Id,Uid FROM maps WHERE Id IN
	//         (SELECT DISTINCT MapId FROM rs_times, players
	//          WHERE rs_times.PlayerId=players.Id AND players.Login=' . quotedString($player->login) . ')';
	$sql = 'SELECT Id,Uid FROM maps WHERE Id ';
	if (!empty($finished))
		$sql .= 'IN (' . implode(',', $finished) . ')';
	else
		$sql .= '= 0';  // empty list
	$result = mysql_query($sql);
	if (mysql_num_rows($result) == 0) {
		mysql_free_result($result);
		return;
	}
    /*
	$order = 'ASC';
	$unranked = array();
	$i = 0;
	// check if player not in top $maxrecs on each map
	while ($dbrow = mysql_fetch_array($result)) {
		// more efficient but unsupported query: :(
		// $sql2 = 'SELECT id FROM players WHERE (Id=' . $player->id . ') AND (Id NOT IN
		//          (SELECT playerid FROM records WHERE MapId=' . $dbrow[0] . ' ORDER by Score, Date LIMIT ' . $maxrecs . '))';
		$sql2 = 'SELECT playerid FROM records
		         WHERE MapId=' . $dbrow[0] . '
		         ORDER by Score ' . $order . ', Date ASC LIMIT ' . $maxrecs;
		$result2 = mysql_query($sql2);
		$found = false;
		if (mysql_num_rows($result2) > 0) {
			while ($plrow = mysql_fetch_array($result2)) {
				if ($player->id == $plrow[0]) {
					$found = true;
					break;
				}
			}
		} 
		if (!$found) {
			$unranked[$i++] = $dbrow[1];
		}
		mysql_free_result($result2);
	}    */
	if (empty($unranked)) {
		mysql_free_result($result);
		return;
	}

	// get new/cached list of maps
	$newlist = getMapsCache($aseco);

	$envids = array('Canyon' => 11, 'Valley' => 12, 'Storm' => 13);
	$head = 'Maps You Have No Rank On:';
	$msg = array();
	if ($aseco->server->packmask != 'Storm')
		$msg[] = array('Id', 'Name', 'Author', 'Env');
	else
		$msg[] = array('Id', 'Name', 'Author');
	$tid = 1;
	$lines = 0;
	$player->msgs = array();
	// reserve extra width for $w tags
	$extra = ($aseco->settings['lists_colormaps'] ? 0.2 : 0);
	if ($aseco->server->packmask != 'Storm')
		$player->msgs[0] = array(1, $head, array(1.29+$extra, 0.12, 0.6+$extra, 0.4, 0.17), array('Icons128x128_1', 'NewTrack', 0.02));
	else
		$player->msgs[0] = array(1, $head, array(1.12+$extra, 0.12, 0.6+$extra, 0.4), array('Icons128x128_1', 'NewTrack', 0.02));

	for ($i = 0; $i < count($unranked); $i++) {
		// does the uid exist in the current server map list?
		if (array_key_exists($unranked[$i], $newlist)) {
			$row = $newlist[$unranked[$i]];
			// store map in player object for jukeboxing
			$trkarr = array();
			$trkarr['name'] = $row['Name'];
			$trkarr['author'] = $row['Author'];
			$trkarr['environment'] = $row['Environnement'];
			$trkarr['filename'] = $row['FileName'];
			$trkarr['uid'] = $row['UId'];
			$player->maplist[] = $trkarr;

			// format map name
			$mapname = $row['Name'];
			if (!$aseco->settings['lists_colormaps'])
				$mapname = stripColors($mapname);
			// grey out if in history
			if (in_array($row['UId'], $jb_buffer))
				$mapname = '{#grey}' . stripColors($mapname);
			else {
				$mapname = '{#black}' . $mapname;
				// add clickable button
				if ($aseco->settings['clickable_lists'] && $tid <= 1900)
					$mapname = array($mapname, $tid+100);  // action id
			}
			// format author name
			$mapauthor = $row['Author'];
			// add clickable button
			if ($aseco->settings['clickable_lists'] && $tid <= 1900)
				$mapauthor = array($mapauthor, -100-$tid);  // action id
			// format env name
			$mapenv = $row['Environnement'];
			// add clickable button
			if ($aseco->settings['clickable_lists'])
				$mapenv = array($mapenv, $envids[$row['Environnement']]);  // action id

			if ($aseco->server->packmask != 'Storm')
				$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
				               $mapname, $mapauthor, $mapenv);
			else
				$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
				               $mapname, $mapauthor);
			$tid++;
			if (++$lines > 9) {
				$player->msgs[] = $msg;
				$lines = 0;
				$msg = array();
				if ($aseco->server->packmask != 'Storm')
					$msg[] = array('Id', 'Name', 'Author', 'Env');
				else
					$msg[] = array('Id', 'Name', 'Author');
			}
		}
	}
	// add if last batch exists
	if (count($msg))
		$player->msgs[] = $msg;

	mysql_free_result($result);
}  // getMapsNoRank

function getMapsNoGold($player) {
	global $aseco, $jb_buffer;

	$player->maplist = array();

	// check for Stunts mode
	if ($aseco->server->gameinfo->mode != Gameinfo::STNT) {

		// get list of finished maps with their best (minimum) times
		$sql = 'SELECT DISTINCT c.Uid,t1.Score FROM rs_times t1, maps m
		        WHERE (PlayerId=' . $player->id . ' AND t1.MapId=m.Id AND
		               Score=(SELECT MIN(t2.Score) FROM rs_times t2
		                      WHERE PlayerId=' . $player->id . ' AND t1.MapId=t2.MapId))';
		$result = mysql_query($sql);
		if (mysql_num_rows($result) == 0) {
			mysql_free_result($result);
			return;
		}

		// get new/cached list of maps
		$newlist = getMapsCache($aseco);

		$envids = array('Canyon' => 11, 'Valley' => 12, 'Storm' => 13);
		$head = 'Maps You Didn\'t Beat Gold Time On:';
		$msg = array();
		if ($aseco->server->packmask != 'Storm')
			$msg[] = array('Id', 'Name', 'Author', 'Env', 'Time');
		else
			$msg[] = array('Id', 'Name', 'Author', 'Time');
		$tid = 1;
		$lines = 0;
		$player->msgs = array();
		// reserve extra width for $w tags
		$extra = ($aseco->settings['lists_colormaps'] ? 0.2 : 0);
		if ($aseco->server->packmask != 'Storm')
			$player->msgs[0] = array(1, $head, array(1.42+$extra, 0.12, 0.6+$extra, 0.4, 0.15, 0.15), array('Icons128x128_1', 'NewTrack', 0.02));
		else
			$player->msgs[0] = array(1, $head, array(1.27+$extra, 0.12, 0.6+$extra, 0.4, 0.15), array('Icons128x128_1', 'NewTrack', 0.02));

		while ($dbrow = mysql_fetch_array($result)) {
			// does the uid exist in the current server map list?
			if (array_key_exists($dbrow[0], $newlist)) {
				$row = $newlist[$dbrow[0]];
				// does best time beat map's Gold time?
				if ($dbrow[1] > $row['GoldTime']) {
					// store map in player object for jukeboxing
					$trkarr = array();
					$trkarr['name'] = $row['Name'];
					$trkarr['author'] = $row['Author'];
					$trkarr['environment'] = $row['Environnement'];
					$trkarr['filename'] = $row['FileName'];
					$trkarr['uid'] = $row['UId'];
					$player->maplist[] = $trkarr;

					// format map name
					$mapname = $row['Name'];
					if (!$aseco->settings['lists_colormaps'])
						$mapname = stripColors($mapname);
					// grey out if in history
					if (in_array($row['UId'], $jb_buffer))
						$mapname = '{#grey}' . stripColors($mapname);
					else {
						$mapname = '{#black}' . $mapname;
						// add clickable button
						if ($aseco->settings['clickable_lists'] && $tid <= 1900)
							$mapname = array($mapname, $tid+100);  // action id
					}
					// format author name
					$mapauthor = $row['Author'];
					// add clickable button
					if ($aseco->settings['clickable_lists'] && $tid <= 1900)
						$mapauthor = array($mapauthor, -100-$tid);  // action id
					// format env name
					$mapenv = $row['Environnement'];
					// add clickable button
					if ($aseco->settings['clickable_lists'])
						$mapenv = array($mapenv, $envids[$row['Environnement']]);  // action id

					// compute difference to Gold time
					$diff = $dbrow[1] - $row['GoldTime'];
					$sec = floor($diff/1000);
					$hun = ($diff - ($sec * 1000)) / 10;

					if ($aseco->server->packmask != 'Storm')
						$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
						               $mapname, $mapauthor, $mapenv,
						               '+' . sprintf("%d.%02d", $sec, $hun));
					else
						$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
						               $mapname, $mapauthor,
						               '+' . sprintf("%d.%02d", $sec, $hun));
					$tid++;
					if (++$lines > 14) {
						$player->msgs[] = $msg;
						$lines = 0;
						$msg = array();
						if ($aseco->server->packmask != 'Storm')
							$msg[] = array('Id', 'Name', 'Author', 'Env', 'Time');
						else
							$msg[] = array('Id', 'Name', 'Author', 'Time');
					}
				}
			}
		}
		// add if last batch exists
		if (count($msg) > 1)
			$player->msgs[] = $msg;

	} else { // Stunts mode

		// get list of finished maps with their best (maximum) scores
		$sql = 'SELECT DISTINCT c.Uid,t1.Score FROM rs_times t1, maps m
		        WHERE (PlayerId=' . $player->id . ' AND t1.MapId=m.Id AND
		               Score=(SELECT MAX(t2.Score) FROM rs_times t2
		                      WHERE PlayerId=' . $player->id . ' AND t1.MapId=t2.MapId))';
		$result = mysql_query($sql);
		if (mysql_num_rows($result) == 0) {
			mysql_free_result($result);
			return;
		}

		// get new/cached list of maps
		$newlist = getMapsCache($aseco);

		$head = 'Maps You Didn\'t Beat Gold Score On:';
		$msg = array();
		$msg[] = array('Id', 'Name', 'Author', 'Env', 'Score');
		$tid = 1;
		$lines = 0;
		$player->msgs = array();
		// reserve extra width for $w tags
		$extra = ($aseco->settings['lists_colormaps'] ? 0.2 : 0);
		$player->msgs[0] = array(1, $head, array(1.42+$extra, 0.12, 0.6+$extra, 0.4, 0.15, 0.15), array('Icons128x128_1', 'NewTrack', 0.02));

		while ($dbrow = mysql_fetch_array($result)) {
			// does the uid exist in the current server map list?
			if (array_key_exists($dbrow[0], $newlist)) {
				$row = $newlist[$dbrow[0]];
				// does best score beat map's Gold score?
				if ($dbrow[1] < $row['GoldTime']) {
					// store map in player object for jukeboxing
					$trkarr = array();
					$trkarr['name'] = $row['Name'];
					$trkarr['author'] = $row['Author'];
					$trkarr['environment'] = $row['Environnement'];
					$trkarr['filename'] = $row['FileName'];
					$trkarr['uid'] = $row['UId'];
					$player->maplist[] = $trkarr;

					// format map name
					$mapname = $row['Name'];
					if (!$aseco->settings['lists_colormaps'])
						$mapname = stripColors($mapname);
					// grey out if in history
					if (in_array($row['UId'], $jb_buffer))
						$mapname = '{#grey}' . stripColors($mapname);
					else {
						$mapname = '{#black}' . $mapname;
						// add clickable button
						if ($aseco->settings['clickable_lists'] && $tid <= 1900)
							$mapname = array($mapname, $tid+100);  // action id
					}
					// format author name
					$mapauthor = $row['Author'];
					// add clickable button
					if ($aseco->settings['clickable_lists'] && $tid <= 1900)
						$mapauthor = array($mapauthor, -100-$tid);  // action id
					// format env name
					$mapenv = $row['Environnement'];
					// add clickable button
					if ($aseco->settings['clickable_lists'])
						$mapenv = array($mapenv, $envids[$row['Environnement']]);  // action id

					// compute difference to Gold score
					$diff = $row['GoldTime'] - $dbrow[1];

					$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
					               $mapname, $mapauthor, $mapenv,
					               '-' . $diff);
					$tid++;
					if (++$lines > 14) {
						$player->msgs[] = $msg;
						$lines = 0;
						$msg = array();
						$msg[] = array('Id', 'Name', 'Author', 'Env', 'Score');
					}
				}
			}
		}
		// add if last batch exists
		if (count($msg) > 1)
			$player->msgs[] = $msg;
	}

	mysql_free_result($result);
}  // getMapsNoGold

function getMapsNoAuthor($player) {
	global $aseco, $jb_buffer;

	$player->maplist = array();

	// check for Stunts mode
	if ($aseco->server->gameinfo->mode != Gameinfo::STNT) {

		// get list of finished maps with their best (minimum) times
		$sql = 'SELECT DISTINCT c.Uid,t1.Score FROM rs_times t1, maps m
		        WHERE (PlayerId=' . $player->id . ' AND t1.MapId=m.Id AND
		               Score=(SELECT MIN(t2.Score) FROM rs_times t2
		                      WHERE PlayerId=' . $player->id . ' AND t1.MapId=t2.MapId))';
		$result = mysql_query($sql);
		if (mysql_num_rows($result) == 0) {
			mysql_free_result($result);
			return;
		}

		// get new/cached list of maps
		$newlist = getMapsCache($aseco);

		$envids = array('Canyon' => 11, 'Valley' => 12, 'Storm' => 13);
		$head = 'Maps You Didn\'t Beat Author Time On:';
		$msg = array();
		if ($aseco->server->packmask != 'Storm')
			$msg[] = array('Id', 'Name', 'Author', 'Env', 'Time');
		else
			$msg[] = array('Id', 'Name', 'Author', 'Time');
		$tid = 1;
		$lines = 0;
		$player->msgs = array();
		// reserve extra width for $w tags
		$extra = ($aseco->settings['lists_colormaps'] ? 0.2 : 0);
		if ($aseco->server->packmask != 'Storm')
			$player->msgs[0] = array(1, $head, array(1.42+$extra, 0.12, 0.6+$extra, 0.4, 0.15, 0.15), array('Icons128x128_1', 'NewTrack', 0.02));
		else
			$player->msgs[0] = array(1, $head, array(1.27+$extra, 0.12, 0.6+$extra, 0.4, 0.15), array('Icons128x128_1', 'NewTrack', 0.02));

		while ($dbrow = mysql_fetch_array($result)) {
			// does the uid exist in the current server map list?
			if (array_key_exists($dbrow[0], $newlist)) {
				$row = $newlist[$dbrow[0]];
				// does best time beat map's Author time?
				if ($dbrow[1] > $row['AuthorTime']) {
					// store map in player object for jukeboxing
					$trkarr = array();
					$trkarr['name'] = $row['Name'];
					$trkarr['author'] = $row['Author'];
					$trkarr['environment'] = $row['Environnement'];
					$trkarr['filename'] = $row['FileName'];
					$trkarr['uid'] = $row['UId'];
					$player->maplist[] = $trkarr;

					// format map name
					$mapname = $row['Name'];
					if (!$aseco->settings['lists_colormaps'])
						$mapname = stripColors($mapname);
					// grey out if in history
					if (in_array($row['UId'], $jb_buffer))
						$mapname = '{#grey}' . stripColors($mapname);
					else {
						$mapname = '{#black}' . $mapname;
						// add clickable button
						if ($aseco->settings['clickable_lists'] && $tid <= 1900)
							$mapname = array($mapname, $tid+100);  // action id
					}
					// format author name
					$mapauthor = $row['Author'];
					// add clickable button
					if ($aseco->settings['clickable_lists'] && $tid <= 1900)
						$mapauthor = array($mapauthor, -100-$tid);  // action id
					// format env name
					$mapenv = $row['Environnement'];
					// add clickable button
					if ($aseco->settings['clickable_lists'])
						$mapenv = array($mapenv, $envids[$row['Environnement']]);  // action id

					// compute difference to Author time
					$diff = $dbrow[1] - $row['AuthorTime'];
					$sec = floor($diff/1000);
					$hun = ($diff - ($sec * 1000)) / 10;

					if ($aseco->server->packmask != 'Storm')
						$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
						               $mapname, $mapauthor, $mapenv,
						               '+' . sprintf("%d.%02d", $sec, $hun));
					else
						$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
						               $mapname, $mapauthor,
						               '+' . sprintf("%d.%02d", $sec, $hun));
					$tid++;
					if (++$lines > 14) {
						$player->msgs[] = $msg;
						$lines = 0;
						$msg = array();
						if ($aseco->server->packmask != 'Storm')
							$msg[] = array('Id', 'Name', 'Author', 'Env', 'Time');
						else
							$msg[] = array('Id', 'Name', 'Author', 'Time');
					}
				}
			}
		}
		// add if last batch exists
		if (count($msg) > 1)
			$player->msgs[] = $msg;

	} else {  // Stunts mode

		// get list of finished maps with their best (maximum) scores
		$sql = 'SELECT DISTINCT c.Uid,t1.Score FROM rs_times t1, maps m
		        WHERE (PlayerId=' . $player->id . ' AND t1.MapId=m.Id AND
		               Score=(SELECT MAX(t2.Score) FROM rs_times t2
		                      WHERE PlayerId=' . $player->id . ' AND t1.MapId=t2.MapId))';
		$result = mysql_query($sql);
		if (mysql_num_rows($result) == 0) {
			mysql_free_result($result);
			return;
		}

		// get new/cached list of maps
		$newlist = getMapsCache($aseco);

		$head = 'Maps You Didn\'t Beat Author Score On:';
		$msg = array();
		$msg[] = array('Id', 'Name', 'Author', 'Env', 'Score');
		$tid = 1;
		$lines = 0;
		$player->msgs = array();
		// reserve extra width for $w tags
		$extra = ($aseco->settings['lists_colormaps'] ? 0.2 : 0);
		$player->msgs[0] = array(1, $head, array(1.42+$extra, 0.12, 0.6+$extra, 0.4, 0.15, 0.15), array('Icons128x128_1', 'NewTrack', 0.02));

		while ($dbrow = mysql_fetch_array($result)) {
			// does the uid exist in the current server map list?
			if (array_key_exists($dbrow[0], $newlist)) {
				$row = $newlist[$dbrow[0]];
				// does best score beat map's Author score?
				if ($dbrow[1] < $row['AuthorScore']) {
					// store map in player object for jukeboxing
					$trkarr = array();
					$trkarr['name'] = $row['Name'];
					$trkarr['author'] = $row['Author'];
					$trkarr['environment'] = $row['Environnement'];
					$trkarr['filename'] = $row['FileName'];
					$trkarr['uid'] = $row['UId'];
					$player->maplist[] = $trkarr;

					// format map name
					$mapname = $row['Name'];
					if (!$aseco->settings['lists_colormaps'])
						$mapname = stripColors($mapname);
					// grey out if in history
					if (in_array($row['UId'], $jb_buffer))
						$mapname = '{#grey}' . stripColors($mapname);
					else {
						$mapname = '{#black}' . $mapname;
						// add clickable button
						if ($aseco->settings['clickable_lists'] && $tid <= 1900)
							$mapname = array($mapname, $tid+100);  // action id
					}
					// format author name
					$mapauthor = $row['Author'];
					// add clickable button
					if ($aseco->settings['clickable_lists'] && $tid <= 1900)
						$mapauthor = array($mapauthor, -100-$tid);  // action id
					// format env name
					$mapenv = $row['Environnement'];
					// add clickable button
					if ($aseco->settings['clickable_lists'])
						$mapenv = array($mapenv, $envids[$row['Environnement']]);  // action id

					// compute difference to Author score
					$diff = $row['AuthorScore'] - $dbrow[1];

					$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
					               $mapname, $mapauthor, $mapenv,
					               '-' . $diff);
					$tid++;
					if (++$lines > 14) {
						$player->msgs[] = $msg;
						$lines = 0;
						$msg = array();
						$msg[] = array('Id', 'Name', 'Author', 'Env', 'Score');
					}
				}
			}
		}
		// add if last batch exists
		if (count($msg) > 1)
			$player->msgs[] = $msg;
	}

	mysql_free_result($result);
}  // getMapsNoAuthor

// calls function get_recs() from chat.records2.php
function getMapsNoRecent($player) {
	global $aseco, $jb_buffer, $maxrecs;

	$player->maplist = array();

	// get list of finished maps with their most recent (maximum) dates
	$sql = 'SELECT DISTINCT c.Uid,t1.Date FROM rs_times t1, maps m
	        WHERE (PlayerId=' . $player->id . ' AND t1.MapId=m.Id AND
	               Date=(SELECT MAX(t2.Date) FROM rs_times t2
	                     WHERE PlayerId=' . $player->id . ' AND t1.MapId=t2.MapId))
	        ORDER BY t1.date';
	$result = mysql_query($sql);
	if (mysql_num_rows($result) == 0) {
		mysql_free_result($result);
		return;
	}

	// get list of ranked records
	$reclist = get_recs($player->id);
	// get new/cached list of maps
	$newlist = getMapsCache($aseco);

	$envids = array('Canyon' => 11, 'Valley' => 12, 'Storm' => 13);
	$head = 'Maps You Didn\'t Play Recently:';
	$msg = array();
	if ($aseco->server->packmask != 'Storm')
		$msg[] = array('Id', 'Rec', 'Name', 'Author', 'Env', 'Date');
	else
		$msg[] = array('Id', 'Rec', 'Name', 'Author', 'Date');
	$tid = 1;
	$lines = 0;
	$player->msgs = array();
	// reserve extra width for $w tags
	$extra = ($aseco->settings['lists_colormaps'] ? 0.2 : 0);
	if ($aseco->server->packmask != 'Storm')
		$player->msgs[0] = array(1, $head, array(1.58+$extra, 0.12, 0.1, 0.6+$extra, 0.4, 0.15, 0.21), array('Icons128x128_1', 'NewTrack', 0.02));
	else
		$player->msgs[0] = array(1, $head, array(1.43+$extra, 0.12, 0.1, 0.6+$extra, 0.4, 0.21), array('Icons128x128_1', 'NewTrack', 0.02));

	while ($dbrow = mysql_fetch_array($result)) {
		// does the uid exist in the current server map list?
		if (array_key_exists($dbrow[0], $newlist)) {
			$row = $newlist[$dbrow[0]];
			// store map in player object for jukeboxing
			$trkarr = array();
			$trkarr['name'] = $row['Name'];
			$trkarr['author'] = $row['Author'];
			$trkarr['environment'] = $row['Environnement'];
			$trkarr['filename'] = $row['FileName'];
			$trkarr['uid'] = $row['UId'];
			$player->maplist[] = $trkarr;

			// format map name
			$mapname = $row['Name'];
			if (!$aseco->settings['lists_colormaps'])
				$mapname = stripColors($mapname);
			// grey out if in history
			if (in_array($row['UId'], $jb_buffer))
				$mapname = '{#grey}' . stripColors($mapname);
			else {
				$mapname = '{#black}' . $mapname;
				// add clickable button
				if ($aseco->settings['clickable_lists'] && $tid <= 1900)
					$mapname = array($mapname, $tid+100);  // action id
			}
			// format author name
			$mapauthor = $row['Author'];
			// add clickable button
			if ($aseco->settings['clickable_lists'] && $tid <= 1900)
				$mapauthor = array($mapauthor, -100-$tid);  // action id
			// format env name
			$mapenv = $row['Environnement'];
			// add clickable button
			if ($aseco->settings['clickable_lists'])
				$mapenv = array($mapenv, $envids[$row['Environnement']]);  // action id

			// get corresponding record
			$pos = isset($reclist[$dbrow[0]]) ? $reclist[$dbrow[0]] : 0;
			$pos = ($pos >= 1 && $pos <= $maxrecs) ? str_pad($pos, 2, '0', STR_PAD_LEFT) : '-- ';

			if ($aseco->server->packmask != 'Storm')
				$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
				               $pos . '.', $mapname, $mapauthor, $mapenv,
				               date('Y/m/d', $dbrow[1]));
			else
				$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
				               $pos . '.', $mapname, $mapauthor,
				               date('Y/m/d', $dbrow[1]));
			$tid++;
			if (++$lines > 14) {
				$player->msgs[] = $msg;
				$lines = 0;
				$msg = array();
				if ($aseco->server->packmask != 'Storm')
					$msg[] = array('Id', 'Rec', 'Name', 'Author', 'Env', 'Date');
				else
					$msg[] = array('Id', 'Rec', 'Name', 'Author', 'Date');
			}
		}
	}
	// add if last batch exists
	if (count($msg) > 1)
		$player->msgs[] = $msg;

	mysql_free_result($result);
}  // getMapsNoRecent

function getMapsByLength($player, $order) {
	global $aseco, $jb_buffer;

	$player->maplist = array();

	// if Stunts mode, bail out immediately
	if ($aseco->server->gameinfo->mode == Gameinfo::STNT) return;

	// get new/cached list of maps
	$newlist = getMapsCache($aseco);

	// build list of author times
	$times = array();
	foreach ($newlist as $uid => $row)
		$times[$uid] = $row['AuthorTime'];

	// sort for shortest or longest author times
	$order ? asort($times) : arsort($times);

	$envids = array('Canyon' => 11, 'Valley' => 12, 'Storm' => 13);
	$head = ($order ? 'Shortest' : 'Longest') . ' Maps On This Server:';
	$msg = array();
	if ($aseco->server->packmask != 'Storm')
		$msg[] = array('Id', 'Name', 'Author', 'Env', 'AuthTime');
	else
		$msg[] = array('Id', 'Name', 'Author', 'AuthTime');
	$tid = 1;
	$lines = 0;
	$player->msgs = array();
	// reserve extra width for $w tags
	$extra = ($aseco->settings['lists_colormaps'] ? 0.2 : 0);
	if ($aseco->server->packmask != 'Storm')
		$player->msgs[0] = array(1, $head, array(1.44+$extra, 0.12, 0.6+$extra, 0.4, 0.15, 0.17), array('Icons128x128_1', 'NewTrack', 0.02));
	else
		$player->msgs[0] = array(1, $head, array(1.29+$extra, 0.12, 0.6+$extra, 0.4, 0.17), array('Icons128x128_1', 'NewTrack', 0.02));

	foreach ($times as $uid => $time) {
		$row = $newlist[$uid];
		// store map in player object for jukeboxing
		$trkarr = array();
		$trkarr['name'] = $row['Name'];
		$trkarr['author'] = $row['Author'];
		$trkarr['environment'] = $row['Environnement'];
		$trkarr['filename'] = $row['FileName'];
		$trkarr['uid'] = $row['UId'];
		$player->maplist[] = $trkarr;

		// format map name
		$mapname = $row['Name'];
		if (!$aseco->settings['lists_colormaps'])
			$mapname = stripColors($mapname);
		// grey out if in history
		if (in_array($row['UId'], $jb_buffer))
			$mapname = '{#grey}' . stripColors($mapname);
		else {
			$mapname = '{#black}' . $mapname;
			// add clickable button
			if ($aseco->settings['clickable_lists'] && $tid <= 1900)
				$mapname = array($mapname, $tid+100);  // action id
		}
		// format author name
		$mapauthor = $row['Author'];
		// add clickable button
		if ($aseco->settings['clickable_lists'] && $tid <= 1900)
			$mapauthor = array($mapauthor, -100-$tid);  // action id
		// format env name
		$mapenv = $row['Environnement'];
		// add clickable button
		if ($aseco->settings['clickable_lists'])
			$mapenv = array($mapenv, $envids[$row['Environnement']]);  // action id

		if ($aseco->server->packmask != 'Storm')
			$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
			               $mapname, $mapauthor, $mapenv, formatTime($time));
		else
			$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
			               $mapname, $mapauthor, formatTime($time));
		$tid++;
		if (++$lines > 14) {
			$player->msgs[] = $msg;
			$lines = 0;
			$msg = array();
			if ($aseco->server->packmask != 'Storm')
				$msg[] = array('Id', 'Name', 'Author', 'Env', 'AuthTime');
			else
				$msg[] = array('Id', 'Name', 'Author', 'AuthTime');
		}
	}
	// add if last batch exists
	if (count($msg) > 1)
		$player->msgs[] = $msg;
}  // getMapsByLength

function getMapsByAdd($player, $order, $count) {
	global $aseco, $jb_buffer;

	$player->maplist = array();

	// get list of maps in reverse order of addition
	$sql = 'SELECT Uid FROM maps
	        ORDER BY Id ' . ($order ? 'DESC' : 'ASC');
	$result = mysql_query($sql);
	if (mysql_num_rows($result) == 0) {
		mysql_free_result($result);
		return;
	}

	// get new/cached list of maps
	$newlist = getMapsCache($aseco);

	$tcnt = 0;
	$envids = array('Canyon' => 11, 'Valley' => 12, 'Storm' => 13);
	$head = ($order ? 'Newest' : 'Oldest') . ' Maps On This Server:';
	$msg = array();
	if ($aseco->server->packmask != 'Storm')
		$msg[] = array('Id', 'Name', 'Author', 'Env');
	else
		$msg[] = array('Id', 'Name', 'Author');
	$tid = 1;
	$lines = 0;
	$player->msgs = array();
	// reserve extra width for $w tags
	$extra = ($aseco->settings['lists_colormaps'] ? 0.2 : 0);
	if ($aseco->server->packmask != 'Storm')
		$player->msgs[0] = array(1, $head, array(1.29+$extra, 0.12, 0.6+$extra, 0.4, 0.17), array('Icons128x128_1', 'NewTrack', 0.02));
	else
		$player->msgs[0] = array(1, $head, array(1.12+$extra, 0.12, 0.6+$extra, 0.4), array('Icons128x128_1', 'NewTrack', 0.02));

	while ($dbrow = mysql_fetch_array($result)) {
		// does the uid exist in the current server map list?
		if (array_key_exists($dbrow[0], $newlist)) {
			$row = $newlist[$dbrow[0]];
			// store map in player object for jukeboxing
			$trkarr = array();
			$trkarr['name'] = $row['Name'];
			$trkarr['author'] = $row['Author'];
			$trkarr['environment'] = $row['Environnement'];
			$trkarr['filename'] = $row['FileName'];
			$trkarr['uid'] = $row['UId'];
			$player->maplist[] = $trkarr;

			// format map name
			$mapname = $row['Name'];
			if (!$aseco->settings['lists_colormaps'])
				$mapname = stripColors($mapname);
			// grey out if in history
			if (in_array($row['UId'], $jb_buffer))
				$mapname = '{#grey}' . stripColors($mapname);
			else {
				$mapname = '{#black}' . $mapname;
				// add clickable button
				if ($aseco->settings['clickable_lists'] && $tid <= 1900)
					$mapname = array($mapname, $tid+100);  // action id
			}
			// format author name
			$mapauthor = $row['Author'];
			// add clickable button
			if ($aseco->settings['clickable_lists'] && $tid <= 1900)
				$mapauthor = array($mapauthor, -100-$tid);  // action id
			// format env name
			$mapenv = $row['Environnement'];
			// add clickable button
			if ($aseco->settings['clickable_lists'])
				$mapenv = array($mapenv, $envids[$row['Environnement']]);  // action id

			if ($aseco->server->packmask != 'Storm')
				$msg[] =  array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
				                $mapname, $mapauthor, $mapenv);
			else
				$msg[] =  array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
				                $mapname, $mapauthor);
			$tid++;
			if (++$lines > 14) {
				$player->msgs[] = $msg;
				$lines = 0;
				$msg = array();
				if ($aseco->server->packmask != 'Storm')
					$msg[] = array('Id', 'Name', 'Author', 'Env');
				else
					$msg[] = array('Id', 'Name', 'Author');
			}
			// check if we have enough maps already
			if (++$tcnt == $count) break;
		}
	}
	// add if last batch exists
	if (count($msg) > 1)
		$player->msgs[] = $msg;

	mysql_free_result($result);
}  // getMapsByAdd

function getMapsNoVote($player) {
	global $aseco, $jb_buffer, $maxrecs;

	$player->maplist = array();

	// get list of ranked records
	$reclist = get_recs($player->id);

	// get new/cached list of maps
	$newlist = getMapsCache($aseco);

	// get list of voted maps and remove those
	$sql = 'SELECT Uid FROM maps m, rs_karma k
	        WHERE m.Id=k.MapId AND k.PlayerId=' . $player->id;
	$result = mysql_query($sql);
	if (mysql_num_rows($result) > 0) {
		while ($dbrow = mysql_fetch_array($result))
			unset($newlist[$dbrow[0]]);
	}
	mysql_free_result($result);

	$envids = array('Canyon' => 11, 'Valley' => 12, 'Storm' => 13);
	$head = 'Maps You Didn\'t Vote For:';
	$msg = array();
	if ($aseco->server->packmask != 'Storm')
		$msg[] = array('Id', 'Rec', 'Name', 'Author', 'Env');
	else
		$msg[] = array('Id', 'Rec', 'Name', 'Author');
	$tid = 1;
	$lines = 0;
	$player->msgs = array();
	// reserve extra width for $w tags
	$extra = ($aseco->settings['lists_colormaps'] ? 0.2 : 0);
	if ($aseco->server->packmask != 'Storm')
		$player->msgs[0] = array(1, $head, array(1.39+$extra, 0.12, 0.1, 0.6+$extra, 0.4, 0.17), array('Icons128x128_1', 'NewTrack', 0.02));
	else
		$player->msgs[0] = array(1, $head, array(1.22+$extra, 0.12, 0.1, 0.6+$extra, 0.4), array('Icons128x128_1', 'NewTrack', 0.02));

	foreach ($newlist as $row) {
		// store map in player object for jukeboxing
		$trkarr = array();
		$trkarr['name'] = $row['Name'];
		$trkarr['author'] = $row['Author'];
		$trkarr['environment'] = $row['Environnement'];
		$trkarr['filename'] = $row['FileName'];
		$trkarr['uid'] = $row['UId'];
		$player->maplist[] = $trkarr;

		// format map name
		$mapname = $row['Name'];
		if (!$aseco->settings['lists_colormaps'])
			$mapname = stripColors($mapname);
		// grey out if in history
		if (in_array($row['UId'], $jb_buffer))
			$mapname = '{#grey}' . stripColors($mapname);
		else {
			$mapname = '{#black}' . $mapname;
			// add clickable button
			if ($aseco->settings['clickable_lists'] && $tid <= 1900)
				$mapname = array($mapname, $tid+100);  // action id
		}
		// format author name
		$mapauthor = $row['Author'];
		// add clickable button
		if ($aseco->settings['clickable_lists'] && $tid <= 1900)
			$mapauthor = array($mapauthor, -100-$tid);  // action id
		// format env name
		$mapenv = $row['Environnement'];
		// add clickable button
		if ($aseco->settings['clickable_lists'])
			$mapenv = array($mapenv, $envids[$row['Environnement']]);  // action id

		// get corresponding record
		$pos = isset($reclist[$row['UId']]) ? $reclist[$row['UId']] : 0;
		$pos = ($pos >= 1 && $pos <= $maxrecs) ? str_pad($pos, 2, '0', STR_PAD_LEFT) : '-- ';

		if ($aseco->server->packmask != 'Storm')
			$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
			               $pos . '.', $mapname, $mapauthor, $mapenv);
		else
			$msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
			               $pos . '.', $mapname, $mapauthor);
		$tid++;
		if (++$lines > 14) {
			$player->msgs[] = $msg;
			$lines = 0;
			$msg = array();
			if ($aseco->server->packmask != 'Storm')
				$msg[] = array('Id', 'Rec', 'Name', 'Author', 'Env');
			else
				$msg[] = array('Id', 'Rec', 'Name', 'Author');
		}
	}
	// add if last batch exists
	if (count($msg) > 1)
		$player->msgs[] = $msg;
}  // getMapsNoVote


function getMapData($filename, $rtnvotes) {
	global $aseco, $mxvoteratio;

	$ret = array();
	if (!file_exists($filename)) {
		$ret['name'] = 'file not found';
		$ret['votes'] = 500;
		return $ret;
	}
	// check whether votes are needed
	if ($rtnvotes) {
		$ret['votes'] = required_votes($mxvoteratio);  // from plugin.rasp_votes.php
		if ($aseco->debug) {
			$ret['votes'] = 1;
		}
	} else {
		$ret['votes'] = 1;
	}

	$gbx = new GBXChallengeFetcher($filename, false);
	if (isset($gbx->uid) && $gbx->uid != 'read error') {
		$ret['uid'] = $gbx->uid;
		$ret['name'] = stripNewlines($gbx->name);
		$ret['author'] = $gbx->author;
		$ret['environment'] = $gbx->envir;
		$ret['authortime'] = $gbx->authortm;
		$ret['authorscore'] = $gbx->ascore;
		$ret['cost'] = $gbx->cost;
		$ret['kind'] = $gbx->kind;		
		$ret['mood'] = $gbx->mood;
		$ret['pub'] = $gbx->pub;   
		$ret['maptype'] = $gbx->maptype; 
		$ret['titleuid'] = $gbx->titleuid; 					
	} else {
		$ret['votes'] = 500;
		$ret['name'] = 'Not a GBX file';
	}
	return $ret;
}  // getMapData
?>
