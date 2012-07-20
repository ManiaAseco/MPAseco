<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Chat plugin.
 * Displays player statistics & personal settings.
 * Updated by Xymph
 *  
 * edited for SM 20.07.2012 by kremsy and the MPAseco-Team
 *  
 */

Aseco::addChatCommand('stats', 'Displays statistics of current player');
Aseco::addChatCommand('settings', 'Displays your personal settings');


function chat_stats($aseco, $command) {
	global $rasp, $feature_ranks, $maxrecs;

	$player = $command['author'];
	$target = $player;

	// check for optional player parameter
	if ($command['params'] != '')
		if (!$target = $aseco->getPlayerParam($player, $command['params'], true))
			return;

	// get current player info
	$aseco->client->resetError();
	$aseco->client->query('GetDetailedPlayerInfo', $target->login);
	$info = $aseco->client->getResponse();
	if ($aseco->client->isError()) {
		$rank = 0;
		$score = 0;
		$lastm = 0;
		$wins = 0;
		$draws = 0;
		$losses = 0;
		$zone = '';
		$inscrdays = 0;
		$inscrhours = 0;
	} else {
		$rank = $info['LadderStats']['PlayerRankings'][0]['Ranking'];
		$score = $info['LadderStats']['PlayerRankings'][0]['Score'];
		$lastm = $info['LadderStats']['LastMatchScore'];
		$wins = $info['LadderStats']['NbrMatchWins'];
		$draws = $info['LadderStats']['NbrMatchDraws'];
		$losses = $info['LadderStats']['NbrMatchLosses'];

		// get zone info
		$zone = substr($info['Path'], 6);  // strip 'World|'
		$inscr = $info['HoursSinceZoneInscription'];
		$inscrdays = floor($inscr / 24);
		$inscrhours = $inscr - ($inscrdays * 24);
	}

	// format numbers with narrow spaces between the thousands
	$frank = str_replace(' ', '$n $m', number_format($rank, 0, ' ', ' '));
	$fwins = str_replace(' ', '$n $m', number_format($wins, 0, ' ', ' '));
	$fdraws = str_replace(' ', '$n $m', number_format($draws, 0, ' ', ' '));
	$flosses = str_replace(' ', '$n $m', number_format($losses, 0, ' ', ' '));

	// obtain last online timestamp
	$query = 'SELECT UpdatedAt FROM players
	          WHERE Login=' . quotedString($target->login);
	$result = mysql_query($query);
	$laston = mysql_fetch_row($result);
	mysql_free_result($result);



	$header = 'Stats for: ' . $target->nickname . '$z / {#login}' . $target->login;
	$stats = array();
	$stats[] = array('Server Date', '{#black}' . date('M d, Y'));
	$stats[] = array('Server Time', '{#black}' . date('H:i:s T'));
	$value = '{#black}' . formatTimeH($target->getTimePlayed() * 1000, false);
	// add clickable button
	if ($aseco->settings['clickable_lists'])
		$value = array($value, -5);  // action id
	$stats[] = array('Time Played', $value);
	$stats[] = array('Last Online', '{#black}' . preg_replace('/:\d\d$/', '', $laston[0]));
if ($feature_ranks) {
	$value = '{#black}' . $rasp->getRank($target->login);
	// add clickable button
	if ($aseco->settings['clickable_lists'])
		$value = array($value, -6);  // action id
	$stats[] = array('Server Rank', $value);
}
	$value = '{#black}' . $records;
	// add clickable button
	if ($aseco->settings['clickable_lists'])
		$value = array($value, 5);  // action id
	$value = '{#black}' . ($target->getWins() > $target->wins ? $target->getWins() : $target->wins);
	// add clickable button
	if ($aseco->settings['clickable_lists'])
		$value = array($value, 6);  // action id
	$stats[] = array('Races Won', $value);
	$stats[] = array('Ladder Rank', '{#black}' . $frank);
	$stats[] = array('Ladder Score', '{#black}' . round($score, 1));
	$stats[] = array('Last Match', '{#black}' . round($lastm, 1));
	$stats[] = array('Wins', '{#black}' . $fwins);
	$stats[] = array('Draws', '{#black}' . $fdraws . ($losses != 0 ?
	                          '   $gW/L: {#black}' . round($wins / $losses, 3) : ''));
	$stats[] = array('Losses', '{#black}' . $flosses);
	$stats[] = array('Zone', '{#black}' . $zone);
	$stats[] = array('Inscribed', '{#black}' . $inscrdays . ' day' . ($inscrdays == 1 ? ' ' : 's ') . $inscrhours . ' hours');
	$stats[] = array('Clan', '{#black}' . ($target->teamname ? $target->teamname . '$z' : '<none>'));
	$stats[] = array('Client', '{#black}' . $target->client);
if ($aseco->allowAbility($player, 'chat_statsip')) {
	$stats[] = array('IP', '{#black}' . $target->ipport);
}

	// display ManiaLink message
	display_manialink($player->login, $header, array('Icons128x128_1', 'Statistics', 0.03), $stats, array(1.0, 0.3, 0.7), 'OK');
}  // chat_stats

function chat_settings($aseco, $command) {

	$player = $command['author'];
	$target = $player;

	// check for optional login parameter if any admin
	if ($command['params'] != '' && $aseco->allowAbility($player, 'chat_settings'))
		if (!$target = $aseco->getPlayerParam($player, $command['params'], true))
			return;

	// get CPs settings
	if (function_exists('chat_cps'))
		$cps = ldb_getCPs($aseco, $target->login);
	else
		$cps = false;

	// get style setting
	$style = ldb_getStyle($aseco, $target->login);

	// get panel settings
	if (function_exists('panels_default'))
		$panels = ldb_getPanels($aseco, $target->login);
	else
		$panels = false;

	// get panel background
	$panelbg = ldb_getPanelBG($aseco, $target->login);

	$header = 'Settings for: ' . $target->nickname . '$z / {#login}' . $target->login;
	$settings = array();



	$settings[] = array('Window Style', '{#black}' . $style);
	$settings[] = array('Panel Background', '{#black}' . $panelbg);

	if ($panels) {
		$settings[] = array();
		if ($aseco->isAnyAdmin($target))
			$settings[] = array('Admin Panel', '{#black}' . substr($panels['admin'], 5));
		$settings[] = array('Donate Panel', '{#black}' . substr($panels['donate'], 6));
		$settings[] = array('Vote Panel', '{#black}' . substr($panels['vote'], 4));
	}

	// display ManiaLink message
	display_manialink($player->login, $header, array('Icons128x128_1', 'Inputs', 0.03), $settings, array(1.0, 0.3, 0.7), 'OK');
}  // chat_settings
?>
