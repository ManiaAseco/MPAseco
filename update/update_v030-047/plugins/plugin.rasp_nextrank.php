<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Nextrank plugin.
 * Shows the next better ranked player.
 * Created by Xymph
 * updated by kremsy
 * Dependencies: none
 */

Aseco::addChatCommand('nextrank', 'Shows the next better ranked player');

function chat_nextrank($aseco, $command, $login = false, $player_id = false) {
	global $rasp, $minrank, $feature_ranks, $nextrank_show_rp;

  if($login == false)
  	$login = $command['author']->login;

   if($player_id == false)
  	$player_id = $command['author']->id;
  	
	// check for relay server
	if ($aseco->server->isrelay) {
		$message = formatText($aseco->getChatMessage('NOTONRELAY'));
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
		return;
	}

	if ($feature_ranks) {
		// find current player's avg
		$query = 'SELECT Avg FROM rs_rank
		          WHERE PlayerId=' . $player_id;
		$res = mysql_query($query);   //6164

		if (mysql_num_rows($res) > 0) {
			$row = mysql_fetch_array($res);
			$avg = $row['Avg'];

			// find players with better avgs
			$query = 'SELECT PlayerId,Avg FROM rs_rank
			          WHERE Avg>' . $avg . ' ORDER BY Avg';
			$res2 = mysql_query($query);

			if (mysql_num_rows($res2) > 0) {
				// find last player before current one
				$row2=mysql_fetch_array($res2);
				$pid = $row2['PlayerId'];
				$avg2 = $row2['Avg'];
			/*	while ($row2 = mysql_fetch_array($res2)) {
					$pid = $row2['PlayerId'];
					$avg2 = $row2['Avg'];
				}       */

				// obtain next player's info
				$query = 'SELECT Login,NickName FROM players
				          WHERE Id=' . $pid;
				$res3 = mysql_query($query);
				$row3 = mysql_fetch_array($res3);

				$rank = $rasp->getRank($row3['Login']);
				$rank = preg_replace('|^(\d+)/|', '{#rank}$1{#record}/{#highlite}', $rank);

				// show chat message
				$message = formatText($rasp->messages['NEXTRANK'][0],
				                      stripColors($row3['NickName']), $rank);
				// show difference in record positions too?
				if ($nextrank_show_rp) {
					// compute difference in record positions
					$diff = ($avg - $avg2);
					$message .= formatText($rasp->messages['NEXTRANK_RP'][0], ceil($diff));
				}
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
				mysql_free_result($res3);
			} else {
				$message = $rasp->messages['TOPRANK'][0];
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
			}
			mysql_free_result($res2);
		} else {
		//	$message = formatText($rasp->messages['RANK_NONE'][0], 100);
		//	$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
		}
		mysql_free_result($res);
	}
}  // chat_nextrank
?>
