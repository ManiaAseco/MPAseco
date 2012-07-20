<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Chat plugin.
 * Displays more lists of players.
 * Created by Xymph
 *
 * Dependencies: none
 */

//Aseco::addChatCommand('ranks', 'Displays list of online ranks/nicks');
Aseco::addChatCommand('clans', 'Displays list of online clans/nicks');
Aseco::addChatCommand('topclans', 'Displays top 10 best ranked clans');

function chat_ranks($aseco, $command) {
	global $rasp;

	$player = $command['author'];
	$ranks = array();

	// sort players by rank, insuring rankless are last by sorting on INT_MAX
	foreach ($aseco->server->players->player_list as $pl) {
		$rank = $rasp->getRank($pl->login);
		$ranks[$pl->login] = $rank != 'None' ?
		                     (integer) preg_replace('/\/.*/', '', $rank) :
		                     PHP_INT_MAX;
	}
	asort($ranks);

	// compile the message
	$head = 'Online Ranks ({#login}rank $g/{#nick} nick$g):';
	$msg = array();
	$lines = 0;
	$player->msgs = array();
	$player->msgs[0] = array(1, $head, array(0.8, 0.15, 0.65), array('Icons128x128_1', 'Buddies'));
	foreach ($ranks as $pl => $rk) {
		$play = $aseco->server->players->getPlayer($pl);
		$msg[] = array('{#login}' . ($rk != PHP_INT_MAX ? $rk : '{#grey}<none>'),
		               '{#black}' . $play->nickname);
		if (++$lines > 14) {
			$player->msgs[] = $msg;
			$lines = 0;
			$msg = array();
		}
	}
	// add if last batch exists
	if (!empty($msg))
		$player->msgs[] = $msg;

	// display ManiaLink message
	display_manialink_multi($player);
}  // chat_ranks

function chat_clans($aseco, $command) {

	$player = $command['author'];
	$clans = array();

	// sort players by clan, insuring clanless are last by sorting on chr(255)
	foreach ($aseco->server->players->player_list as $pl) {
		$clans[$pl->login] = $pl->teamname ? $pl->teamname : chr(255);
	}
	asort($clans);

	// compile the message
	$head = 'Online Clans ({#login}clan $g/{#nick} nick$g):';
	$msg = array();
	$lines = 0;
	$player->msgs = array();
	$player->msgs[0] = array(1, $head, array(1.3, 0.65, 0.65), array('Icons128x128_1', 'Buddies'));
	foreach ($clans as $pl => $tm) {
		$play = $aseco->server->players->getPlayer($pl);
		$msg[] = array('{#login}' . ($tm != chr(255) ? $tm : '{#grey}<none>'),
		               '{#black}' . $play->nickname);
		if (++$lines > 14) {
			$player->msgs[] = $msg;
			$lines = 0;
			$msg = array();
		}
	}
	// add if last batch exists
	if (!empty($msg))
		$player->msgs[] = $msg;

	// display ManiaLink message
	display_manialink_multi($player);
}  // chat_clans

function chat_topclans($aseco, $command) {

	$player = $command['author'];

	$header = 'Current TOP 10 Clans $n(min. ' . $aseco->settings['topclans_minplayers'] . ' players)$m:';
	$top = 10;
	$bgn = '{#black}';  // clanname begin

	// find best ranked
	$query = 'SELECT TeamName, Count, Teamrank FROM (
	            SELECT TeamName, COUNT(avg) AS Count, SUM(Avg)/COUNT(Avg) AS TeamRank
	            FROM players,rs_rank WHERE players.Id=rs_rank.PlayerId
	            GROUP BY TeamName) as Sub
	          WHERE Sub.Count>=' . $aseco->settings['topclans_minplayers'] . '
	          ORDER BY Sub.TeamRank LIMIT ' . $top;
	$res = mysql_query($query);

	if (mysql_num_rows($res) == 0) {
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}No clan(s) found!'), $player->login);
		mysql_free_result($res);
		return false;
	}

	// compile the message with sorted clans
	$i = 1;
	while ($row = mysql_fetch_object($res)) {
		$msg[] = array($i . '.',
		               $bgn . $row->TeamName . '$z $n(' . $row->Count . ')$m',
		               sprintf("%4.1F", $row->TeamRank/10000));
		$i++;
	}

	// display ManiaLink message
	display_manialink($player->login, $header, array('BgRaceScore2', 'Podium'), $msg, array(0.95, 0.1, 0.7, 0.15), 'OK');

	mysql_free_result($res);
}  // chat_topclans
?>
