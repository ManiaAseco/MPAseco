<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Chat plugin.
 * Shows new/online records of the current map, and displays related lists.
 * Created by Xymph
 *
 * Dependencies: used by chat.stats.php, plugin.rasp_jukebox.php
 */

if (!INHIBIT_RECCMDS) {
	Aseco::addChatCommand('newrecs', 'Shows newly driven records');
	Aseco::addChatCommand('liverecs', 'Shows records of online players');
}
Aseco::addChatCommand('best', 'Displays your best records');
Aseco::addChatCommand('worst', 'Displays your worst records');
Aseco::addChatCommand('summary', 'Shows summary of all your records');
Aseco::addChatCommand('topsums', 'Displays top 100 of top-3 record holders');
Aseco::addChatCommand('toprecs', 'Displays top 100 ranked records holders');

/*
 * Universal function to generate list of records for current map.
 * Called by chat_newrecs, chat_liverecs, endMap & beginMap (xaseco2.php).
 * Show to a player if $login defined, otherwise show to all players.
 * $mode = 0 (only new), 1 (top-8 & online players at start of map),
 *         2 (top-6 & online during map), 3 (top-8 & new at end of map)
 * In modes 1/2/3 the last ranked record is also shown
 * top-8 is configurable via 'show_min_recs'; top-6 is show_min_recs-2
 */
function show_maprecs($aseco, $login, $mode, $window) {

	$records = '$n';  // use narrow font

	// check for records
	if (($total = $aseco->server->records->count()) == 0) {
		$totalnew = -1;
	} else {
		// check whether to show range
		if ($aseco->settings['show_recs_range']) {
			// get the first & last ranked records
			$first = $aseco->server->records->getRecord(0);
			$last = $aseco->server->records->getRecord($total-1);
			// compute difference between records
			if ($aseco->server->gameinfo->mode != Gameinfo::STNT) {
				$diff = $last->score - $first->score;
				$sec = floor($diff/1000);
				$ths = $diff - ($sec * 1000);
			} else {  // Stunts
				$diff = $first->score - $last->score;
			}
		}

		// get list of online players
		$players = array();
		foreach ($aseco->server->players->player_list as $pl) {
			$players[] = $pl->login;
		}

		// collect new records and records by online players
		$totalnew = 0;
		// go through each record
		for ($i = 0; $i < $total; $i++) {
			$cur_record = $aseco->server->records->getRecord($i);

			// if the record is new then display it
			if ($cur_record->new) {
				$totalnew++;
				$record_msg = formatText($aseco->getChatMessage('RANKING_RECORD_NEW_ON'),
				                         $i+1,
				                         stripColors($cur_record->player->nickname),
				                         ($aseco->server->gameinfo->mode == Gameinfo::STNT ?
				                          $cur_record->score : formatTime($cur_record->score)));
				// always show new record
				$records .= $record_msg;
			} else {
				// check if player is online
				if (in_array($cur_record->player->login, $players)) {
					$record_msg = formatText($aseco->getChatMessage('RANKING_RECORD_ON'),
					                         $i+1,
					                         stripColors($cur_record->player->nickname),
					                         ($aseco->server->gameinfo->mode == Gameinfo::STNT ?
					                          $cur_record->score : formatTime($cur_record->score)));
					// check if last ranked record
					if ($mode != 0 && $i == $total-1) {
						$records .= $record_msg;
					}
					// check if always show (start of/during map)
					elseif ($mode == 1 || $mode == 2) {
						$records .= $record_msg;
					}
					else {
						// show record if < show_min_recs (end of map)
						if ($mode == 3 && $i < $aseco->settings['show_min_recs']) {
							$records .= $record_msg;
						}
					}
				} else {
					$record_msg = formatText($aseco->getChatMessage('RANKING_RECORD'),
					                         $i+1,
					                         stripColors($cur_record->player->nickname),
					                         ($aseco->server->gameinfo->mode == Gameinfo::STNT ?
					                          $cur_record->score : formatTime($cur_record->score)));
					// check if last ranked record
					if ($mode != 0 && $i == $total-1) {
						$records .= $record_msg;
					}
					// show offline record if < show_min_recs-2 (during map)
					elseif (($mode == 2 && $i < $aseco->settings['show_min_recs']-2) ||
					// show offline record if < show_min_recs (start/end of map)
					        (($mode == 1 || $mode == 3) && $i < $aseco->settings['show_min_recs'])) {
						$records .= $record_msg;
					}
				}
			}
		}
	}

	// define wording of the ranking message
	switch ($mode) {
	case 0:
		$timing = 'during';
		break;
	case 1:
		$timing = 'before';
		break;
	case 2:
		$timing = 'during';
		break;
	case 3:
		$timing = 'after';
		break;
	}

	$name = stripColors($aseco->server->map->name);
	if (isset($aseco->server->map->mx->error) && $aseco->server->map->mx->error == '')
		$name = '$l[http://' . $aseco->server->map->mx->prefix .
		        '.mania-exchange.com/tracks/view/' .
		        $aseco->server->map->mx->id . ']' . $name . '$l';

	// define the ranking message
	if ($totalnew > 0) {
		$message = formatText($aseco->getChatMessage('RANKING_NEW'),
		                      $name, $timing, $totalnew);
	}
	elseif ($totalnew == 0 && $records != '$n') {
		// check whether to show range
		if ($aseco->settings['show_recs_range']) {
			$message = formatText($aseco->getChatMessage('RANKING_RANGE'),
			                      $name, $timing,
			                      ($aseco->server->gameinfo->mode == Gameinfo::STNT ?
			                       $diff : sprintf("%d.%03d", $sec, $ths)));
		} else {
			$message = formatText($aseco->getChatMessage('RANKING'),
			                      $name, $timing);
		}
	}
	elseif ($totalnew == 0 && $records == '$n') {
		$message = formatText($aseco->getChatMessage('RANKING_NONEW'),
		                      $name, $timing);
	}
	else {  // $totalnew == -1
		$message = formatText($aseco->getChatMessage('RANKING_NONE'),
		                      $name, $timing);
	}

	// append the records if any
	if ($records != '$n') {
		$records = substr($records, 0, strlen($records)-1);  // strip trailing ", "
		$message .= LF . $records;
	}

	// show to player or all
	if ($login) {
		// strip 1 leading '>' to indicate a player message instead of system-wide
		$message = str_replace('{#server}>> ', '{#server}> ', $message);
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
	} else {
		if (($window & 4) == 4 && function_exists('send_window_message'))
			send_window_message($aseco, $message, ($mode == 3));
		else
			$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
	}
}  // show_maprecs

function chat_newrecs($aseco, $command) {

	// check for relay server
	if ($aseco->server->isrelay) {
		$message = formatText($aseco->getChatMessage('NOTONRELAY'));
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
		return;
	}

	// show only newly driven records
	show_maprecs($aseco, $command['author']->login, 0, 0);
}  // chat_newrecs

function chat_liverecs($aseco, $command) {

	// check for relay server
	if ($aseco->server->isrelay) {
		$message = formatText($aseco->getChatMessage('NOTONRELAY'));
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
		return;
	}

	// show online & show_min_recs-2 records
	show_maprecs($aseco, $command['author']->login, 2, 0);
}  // chat_liverecs


function chat_best($aseco, $command) {

	// check for relay server
	if ($aseco->server->isrelay) {
		$message = formatText($aseco->getChatMessage('NOTONRELAY'));
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
		return;
	}

	// display player records, best first
	disp_recs($aseco, $command, true);
}  // chat_best

function chat_worst($aseco, $command) {

	// check for relay server
	if ($aseco->server->isrelay) {
		$message = formatText($aseco->getChatMessage('NOTONRELAY'));
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
		return;
	}

	// display player records, worst first
	disp_recs($aseco, $command, false);
}  // chat_worst

/*
 * Universal function to get list of all records for a player.
 * Called by disp_recs (chat_best & chat_worst), chat_summary and
 * chat_stats (in chat.stats.php).
 */
function get_recs($pid) {
	global $aseco;

	// get player's record for each map
	$list = array();
	$order = ($aseco->server->gameinfo->mode == Gameinfo::STNT ? 'DESC' : 'ASC');

	$query = 'SELECT Uid,PlayerId FROM records r LEFT JOIN maps m ON (r.MapId=m.Id)
	          WHERE Uid IS NOT NULL ORDER BY MapId ASC,Score ' . $order . ',Date ASC';
	$result = mysql_query($query);

	$last = '';
	while ($row = mysql_fetch_object($result)) {
		// check for new map & reset rank
		if ($last != $row->Uid) {
			$last = $row->Uid;
			$pos = 1;
		}
		if (isset($list[$row->Uid]))
			continue;

		// store player's maps & records
		if ($row->PlayerId == $pid) {
			$list[$row->Uid] = $pos;
			continue;
		}
		$pos++;
	}

	mysql_free_result($result);
	// return list
	return $list;
}  // get_recs

function disp_recs($aseco, $command, $order) {
	global $jb_buffer;

	$player = $command['author'];
	$target = $player;

	// check for optional login parameter if any admin
	if ($command['params'] != '' && $aseco->allowAbility($player, 'chat_bestworst'))
		if (!$target = $aseco->getPlayerParam($player, $command['params'], true))
			return;

	// check for records
	if ($list = get_recs($target->id)) {
		// sort for best or worst records
		$order ? asort($list) : arsort($list);

		// get new/cached list of maps
		$newlist = getMapsCache($aseco);  // from rasp.funcs.php

		// create list of records
		$player->maplist = array();
		$envids = array('Canyon' => 11, 'Valley' => 12);
		$head = ($order ? 'Best' : 'Worst') . ' Records for ' . str_ireplace('$w', '', $target->nickname) . '$z:';
		$recs = array();
		if ($aseco->server->packmask != 'Canyon')
			$recs[] = array('Id', 'Rec', 'Name', 'Author', 'Env');
		else
			$recs[] = array('Id', 'Rec', 'Name', 'Author');
		$tid = 1;
		$lines = 0;
		$player->msgs = array();
		// no extra width for $w tags due to nickname width in header
		if ($aseco->server->packmask != 'Canyon')
			$player->msgs[0] = array(1, $head, array(1.59, 0.12, 0.1, 0.8, 0.4, 0.17), array('Icons128x128_1', 'NewTrack', 0.02));
		else
			$player->msgs[0] = array(1, $head, array(1.42, 0.12, 0.1, 0.8, 0.4), array('Icons128x128_1', 'NewTrack', 0.02));
		foreach ($list as $uid => $pos) {
			// does the uid exist in the current server map list?
			if (array_key_exists($uid, $newlist)) {
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

				if ($aseco->server->packmask != 'Canyon')
					$recs[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
					                str_pad($pos, 2, '0', STR_PAD_LEFT) . '.',
					                $mapname, $mapauthor, $mapenv);
				else
					$recs[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
					                str_pad($pos, 2, '0', STR_PAD_LEFT) . '.',
					                $mapname, $mapauthor);
				$tid++;
				if (++$lines > 14) {
					$player->msgs[] = $recs;
					$lines = 0;
					$recs = array();
					if ($aseco->server->packmask != 'Canyon')
						$recs[] = array('Id', 'Rec', 'Name', 'Author', 'Env');
					else
						$recs[] = array('Id', 'Rec', 'Name', 'Author');
				}
			}
		}
		// add if last batch exists
		if (count($recs) > 1)
			$player->msgs[] = $recs;

		if (count($player->msgs) > 1) {
			// display ManiaLink message
			display_manialink_multi($player);
		} else {
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}No records found!'), $player->login);
		}
	} else {
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}No records found!'), $player->login);
	}
}  // disp_recs

function chat_summary($aseco, $command) {
	global $maxrecs;

	$player = $command['author'];
	$target = $player;

	// check for relay server
	if ($aseco->server->isrelay) {
		$message = formatText($aseco->getChatMessage('NOTONRELAY'));
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		return;
	}

	// check for optional login parameter if any admin
	if ($command['params'] != '' && $aseco->allowAbility($player, 'chat_summary'))
		if (!$target = $aseco->getPlayerParam($player, $command['params'], true))
			return;

	// check for records
	if ($list = get_recs($target->id)) {
		// sort for best records
		asort($list);

		// get new/cached list of maps
		$newlist = getMapsCache($aseco);  // from rasp.funcs.php

		// collect summary of first 3 records and count total
		$show = 3;
		$message = '';
		$total = 0;
		$cntrec = 0;
		$currec = 0;
		foreach ($list as $uid => $rec) {
			// stop upon unranked record
			if ($rec > $maxrecs) break;

			// check if rec is for existing map
			if (array_key_exists($uid, $newlist)) {
				// count total ranked records
				$total++;

				// check for first 3 records
				if ($show > 0) {
					// check for same record
					if ($rec == $currec) {
						$cntrec++;
					} else {
						// collect next record sum
						if ($currec > 0) {
							$message .= formatText($aseco->getChatMessage('SUM_ENTRY'),
							                       $cntrec, ($cntrec > 1 ? 's' : ''), $currec);
							$show--;
						}
						// count first occurance of next record
						$cntrec = 1;
						$currec = $rec;
					}
				}
			}
		}
		// if less than 3 records, add the last one found
		if ($show > 0 && $currec > 0) {
			$message .= formatText($aseco->getChatMessage('SUM_ENTRY'),
			                       $cntrec, ($cntrec > 1 ? 's' : ''), $currec);
			$show--;
		}

		if ($message) {
			// define text version of number of top-3 records
			switch (3-$show) {
				case 1:
					$show = 'one';
					break;
				case 2:
					$show = 'two';
					break;
				case 3:
					$show = 'three';
					break;
			}

			// show chat message
			$message = substr($message, 0, strlen($message)-2);  // strip trailing ", "
			$message = formatText($aseco->getChatMessage('SUMMARY'),
			                      $target->nickname,
			                      $total, ($total > 1 ? 's' : ''),
			                      $show)
			         . $message;
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		} else {
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}No ranked records found!'), $player->login);
		}
	} else {
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}No ranked records found!'), $player->login);
	}
}  // chat_summary

// define sorting function for descending top-3's
function top3_compare($a, $b) {

	// compare #1 records
	if ($a[0] < $b[0])
		return 1;
	elseif ($a[0] > $b[0])
		return -1;

	// compare #2 records
	if ($a[1] < $b[1])
		return 1;
	elseif ($a[1] > $b[1])
		return -1;

	// compare #3 records
	if ($a[2] < $b[2])
		return 1;
	elseif ($a[2] > $b[2])
		return -1;

	// all equal
	return 0;
}  // top3_compare

function chat_topsums($aseco, $command) {

	$player = $command['author'];

	// check for relay server
	if ($aseco->server->isrelay) {
		$message = formatText($aseco->getChatMessage('NOTONRELAY'));
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		return;
	}

	$head = 'TOP 100 of Top-3 Record Holders:';
	$top = 100;
	$bgn = '{#black}';  // nickname begin

	// get new/cached list of maps
	$newlist = getMapsCache($aseco);  // from rasp.funcs.php

	// get current list of map IDs
	$query = 'SELECT Id,Uid FROM maps';
	$result = mysql_query($query);
	$tidlist = array();
	while ($row = mysql_fetch_object($result)) {
		if (array_key_exists($row->Uid, $newlist))
			$tidlist[] = $row->Id;
	}

	// collect top-3 records
	$recs = array();
	$order = ($aseco->server->gameinfo->mode == Gameinfo::STNT ? 'DESC' : 'ASC');
	foreach ($tidlist as $tid) {
		// get top-3 ranked records on this map
		$query = 'SELECT Login FROM players,records
		          WHERE players.Id=records.Playerid AND MapId=' . $tid . '
		          ORDER BY Score ' . $order . ',Date ASC LIMIT 3';
		$result = mysql_query($query);

		// tally top-3 record totals by login
		if ($row = mysql_fetch_array($result)) {
			if (isset($recs[$row[0]])) {
				$recs[$row[0]][0]++;
			} else {
				$recs[$row[0]] = array(1,0,0);
			}
			if ($row = mysql_fetch_array($result)) {
				if (isset($recs[$row[0]])) {
					$recs[$row[0]][1]++;
				} else {
					$recs[$row[0]] = array(0,1,0);
				}
				if ($row = mysql_fetch_array($result)) {
					if (isset($recs[$row[0]])) {
						$recs[$row[0]][2]++;
					} else {
						$recs[$row[0]] = array(0,0,1);
					}
				}
			}
		}
		mysql_free_result($result);
	}

	if (empty($recs)) {
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}No players with ranked records found!'), $player->login);
		return;
	}

	// sort players by #1, #2 & #3 records
	uasort($recs, 'top3_compare');

	$records = array();
	$i = 1;
	$lines = 0;
	$player->msgs = array();
	// reserve extra width for $w tags
	$extra = ($aseco->settings['lists_colornicks'] ? 0.2 : 0);
	$player->msgs[0] = array(1, $head, array(0.85+$extra, 0.1, 0.45+$extra, 0.3), array('BgRaceScore2', 'LadderRank'));
	foreach ($recs as $login => $top3) {
		// obtain nickname for this login
		$nick = $aseco->getPlayerNick($login);
		if (!$aseco->settings['lists_colornicks'])
			$nick = stripColors($nick);

		$records[] = array(str_pad($i, 2, '0', STR_PAD_LEFT) . '.',
		                   $bgn . $nick,
		                   str_pad($top3[0], 3, ' ', STR_PAD_LEFT) . ' / '
		                   . str_pad($top3[1], 3, ' ', STR_PAD_LEFT) . ' / '
		                   . str_pad($top3[2], 3, ' ', STR_PAD_LEFT));
		$i++;
		if (++$lines > 14) {
			$player->msgs[] = $records;
			$lines = 0;
			$records = array();
		}
		if ($i > $top) break;
	}
	// add if last batch exists
	if (!empty($records))
		$player->msgs[] = $records;

	// display ManiaLink message
	display_manialink_multi($player);
}  // chat_topsums

function chat_toprecs($aseco, $command) {
	global $maxrecs;

	$player = $command['author'];

	// check for relay server
	if ($aseco->server->isrelay) {
		$message = formatText($aseco->getChatMessage('NOTONRELAY'));
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
		return;
	}

	$head = 'TOP 100 Ranked Record Holders:';
	$top = 100;
	$bgn = '{#black}';  // nickname begin

	// get new/cached list of maps
	$newlist = getMapsCache($aseco);  // from rasp.funcs.php

	// get current list of map IDs
	$query = 'SELECT Id,Uid FROM maps';
	$result = mysql_query($query);
	$tidlist = array();
	while ($row = mysql_fetch_object($result)) {
		if (array_key_exists($row->Uid, $newlist))
			$tidlist[] = $row->Id;
	}

	// collect record totals
	$recs = array();
	$order = ($aseco->server->gameinfo->mode == Gameinfo::STNT ? 'DESC' : 'ASC');
	foreach ($tidlist as $tid) {
		// get ranked records on this map
		$query = 'SELECT Login FROM players,records
		          WHERE players.Id=records.Playerid AND MapId=' . $tid . '
		          ORDER BY Score ' . $order . ',Date ASC LIMIT ' . $maxrecs;
		$result = mysql_query($query);

		// update record totals by login
		while ($row = mysql_fetch_array($result)) {
			if (isset($recs[$row[0]]))
				$recs[$row[0]]++;
			else
				$recs[$row[0]] = 1;
		}
		mysql_free_result($result);
	}

	if (empty($recs)) {
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}No players with ranked records found!'), $player->login);
		return;
	}

	// sort for most records
	arsort($recs);

	$records = array();
	$i = 1;
	$lines = 0;
	$player->msgs = array();
	// reserve extra width for $w tags
	$extra = ($aseco->settings['lists_colornicks'] ? 0.2 : 0);
	$player->msgs[0] = array(1, $head, array(0.7+$extra, 0.1, 0.45+$extra, 0.15), array('BgRaceScore2', 'LadderRank'));
	foreach ($recs as $login => $rec) {
		// obtain nickname for this login
		$nick = $aseco->getPlayerNick($login);
		if (!$aseco->settings['lists_colornicks'])
			$nick = stripColors($nick);

		$records[] = array(str_pad($i, 2, '0', STR_PAD_LEFT) . '.',
		                   $bgn . $nick,
		                   $rec);
		$i++;
		if (++$lines > 14) {
			$player->msgs[] = $records;
			$lines = 0;
			$records = array();
		}
		if ($i > $top) break;
	}
	// add if last batch exists
	if (!empty($records))
		$player->msgs[] = $records;

	// display ManiaLink message
	display_manialink_multi($player);
}  // chat_toprecs
?>
