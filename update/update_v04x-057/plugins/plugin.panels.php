<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Panels plugin.
 * Selects ManiaLink panel templates.
 * Created by Xymph
 * edited for SM 20.07.2012 by kremsy (www.mania-server.net) 
 *
 *  Dependencies: used by chat.admin.php, plugin.dedimania.php,
 *                       plugin.localdatabase.php, plugin.rasp_votes.php
 *               requires plugin.donate.php
 *               

 */

Aseco::registerEvent('onStartup', 'panels_default');
Aseco::registerEvent('onSync', 'init_statspanel');
Aseco::registerEvent('onEndMap', 'update_allstatspanels');
//Aseco::registerEvent('onBeginMap2', 'update_allrecpanels');
Aseco::registerEvent('onBeginMap2', 'display_alldonpanels');
Aseco::registerEvent('onEndMap', 'display_alldonpanels');
Aseco::registerEvent('onPlayerConnect', 'init_playerpanels');
Aseco::registerEvent('onPlayerConnect', 'load_admpanel');
Aseco::registerEvent('onPlayerConnect', 'load_donpanel');
Aseco::registerEvent('onPlayerConnect', 'load_recpanel');
Aseco::registerEvent('onPlayerFinish', 'finish_recpanel');

// handles action id's "-100"-"-49" for selecting from max. 50 record panel templates
// handles action id's "-48"-"-7" for selecting from max. 40 admin panel templates
// handles action id's "37"-"48" for selecting from max. 10 vote panel templates
// handles action id's "7201"-"7222" for selecting from max. 20 donate panel templates
// handles action id's "7231"-"7262" for selecting from max. 30 panel background templates
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'event_panels');

Aseco::addChatCommand('donpanel', 'Selects donate panel (see: /donpanel help)');
//Aseco::addChatCommand('recpanel', 'Selects records panel (see: /recpanel help)');
Aseco::addChatCommand('votepanel', 'Selects vote panel (see: /votepanel help)');
Aseco::addChatCommand('panelbg', 'Selects panel background (see: /panelbg help)');

// called @ onStartup
function panels_default($aseco) {

	// check for default admin panel
	if ($aseco->settings['admin_panel'] != '') {
		$panel_file = 'configs/panels/' . $aseco->settings['admin_panel'] . '.xml';
		$aseco->console('Load default admin panel [{1}]', $panel_file);
		// load default panel
		if (!$aseco->panels['admin'] = @file_get_contents($panel_file)) {
			// Could not read XML file
			trigger_error('Could not read admin panel file ' . $panel_file . ' !', E_USER_ERROR);
		}
	}

	// check for default donate panel
	if ($aseco->settings['donate_panel'] != '') {
		$panel_file = 'configs/panels/' . $aseco->settings['donate_panel'] . '.xml';
		$aseco->console('Load default donate panel [{1}]', $panel_file);
		// load default panel
		if (!$aseco->panels['donate'] = @file_get_contents($panel_file)) {
			// Could not read XML file
			trigger_error('Could not read donate panel file ' . $panel_file . ' !', E_USER_ERROR);
		}
	}


	// check for default vote panel
	if ($aseco->settings['vote_panel'] != '') {
		$panel_file = 'configs/panels/' . $aseco->settings['vote_panel'] . '.xml';
		$aseco->console('Load default vote panel [{1}]', $panel_file);
		// load default panel
		if (!$aseco->panels['vote'] = @file_get_contents($panel_file)) {
			// Could not read XML file
			trigger_error('Could not read vote panel file ' . $panel_file . ' !', E_USER_ERROR);
		}
	}
}  // panels_default

// called @ onSync
function init_statspanel($aseco) {

	if ($aseco->settings['sb_stats_panels']) {
		$panel_file = 'configs/panels/StatsMP.xml';
		$aseco->console('Load stats panel [{1}]', $panel_file);
		if (!$aseco->statspanel = @file_get_contents($panel_file)) {
			// Could not read XML file
			trigger_error('Could not read stats panel file ' . $panel_file . ' !', E_USER_ERROR);
		}
	}
}  // init_statspanel

// called @ onEndMap
function update_allstatspanels($aseco, $data) {
	global $rasp;

	if ($aseco->settings['sb_stats_panels']) {
		// get list of online players
		$onlinelist = array(0); // init for implode
		foreach ($aseco->server->players->player_list as $pl)
			$onlinelist[] = $pl->id;

      /*
		// collect these players' record totals
		$query = 'SELECT p.Login, COUNT(p.Id) AS Count FROM players p, records r
		          WHERE p.Id=r.PlayerId AND p.Id IN (' . implode(',', $onlinelist) . ')
		          GROUP BY p.Id';
		$result = mysql_query($query);

		// build quick lookup list
		$recslist = array();
		if (mysql_num_rows($result) > 0) {
			while ($row = mysql_fetch_object($result))
				$recslist[$row->Login] = $row->Count;
		}
		mysql_free_result($result);          */

    

		// display stats panels for all these players
		foreach ($aseco->server->players->player_list as $pl) {
			$rank = $rasp->getRank($pl->login);
			$avg = getPts($pl->login);
			$rank = preg_replace('/ Avg: .+/', '', $rank);
			//$recs = (isset($recslist[$pl->login]) ? $recslist[$pl->login] : 0);
			$wins = ($pl->getWins() > $pl->wins ? $pl->getWins() : $pl->wins);
			$play = formatTimeH($pl->getTimeOnline() * 1000, false);
			$dons = ldb_getDonations($aseco, $pl->login);
			display_statspanel($aseco, $pl, $rank, $avg, 0, $wins, $play, $dons);
		}
	}
}  // update_allstatspanels

	function getPts($login) {
    global $aseco;
		$query = 'SELECT AllPoints FROM players
		          WHERE login="' . $login. '"';
    $res = mysql_query($query);
		if (mysql_num_rows($res) > 0) {	
		  $row = mysql_fetch_array($res); 
	  }
		mysql_free_result($res);
		return $row['AllPoints']; 
	}  // getPts
	
// called @ onPlayerConnect
function init_playerpanels($aseco, $player) {

	if (($panels = ldb_getPanels($aseco, $player->login)) &&
	    ($panelbg = ldb_getPanelBG($aseco, $player->login))) {
		// load player's panel background
		$panelbg_file = 'configs/panels/' . $panelbg . '.xml';
		if (($player->panelbg = $aseco->xml_parser->parseXml($panelbg_file)) && isset($player->panelbg['PANEL']['BACKGROUND'][0])) {
			$player->panelbg = $player->panelbg['PANEL']['BACKGROUND'][0];
		} else {
			// Could not parse XML file
			trigger_error('Could not read/parse panel background file ' . $panelbg_file . ' !', E_USER_WARNING);
		}

		// load player's personal panels
		if ($panels['admin'] != '') {
			$panel_file = 'configs/panels/' . $panels['admin'] . '.xml';
			if (!$player->panels['admin'] = @file_get_contents($panel_file)) {
				// Could not read XML file
				trigger_error('Could not read admin panel file ' . $panel_file . ' !', E_USER_WARNING);
			}
			$player->panels['admin'] = set_panel_bg($player->panels['admin'], $player->panelbg);
		} else {
			$player->panels['admin'] = '';
		}

		if ($panels['donate'] != '') {
			$panel_file = 'configs/panels/' . $panels['donate'] . '.xml';
			if (!$player->panels['donate'] = @file_get_contents($panel_file)) {
				// Could not read XML file
				trigger_error('Could not read donate panel file ' . $panel_file . ' !', E_USER_WARNING);
			}
			$player->panels['donate'] = set_panel_bg($player->panels['donate'], $player->panelbg);
		} else {
			$player->panels['donate'] = '';
		}


		if ($panels['vote'] != '') {
			$panel_file = 'configs/panels/' . $panels['vote'] . '.xml';
			if (!$player->panels['vote'] = @file_get_contents($panel_file)) {
				// Could not read XML file
				trigger_error('Could not read vote panel file ' . $panel_file . ' !', E_USER_WARNING);
			}
			$player->panels['vote'] = set_panel_bg($player->panels['vote'], $player->panelbg);
		} else {
			$player->panels['vote'] = '';
		}
	}
}  // init_playerpanels


function chat_donpanel($aseco, $command) {
	global $donation_values;  // from plugin.donate.php

	$player = $command['author'];
	$login = $player->login;

	// check for donation plugin
	if (!function_exists('chat_donate')) {
		$message = '{#server}> {#error}Donations unavailable - include plugins.donate.php in plugins.xml';
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
		return;
	}

	if ($command['params'] == 'help') {
		$header = '{#black}/donpanel$g will change the donate panel:';
		$help = array();
		$help[] = array('...', '{#black}help',
		                'Displays this help information');
		$help[] = array('...', '{#black}list',
		                'Displays available panels');
		$help[] = array('...', '{#black}default',
		                'Resets panel to server default');
		$help[] = array('...', '{#black}off',
		                'Disables donate panel');
		$help[] = array('...', '{#black}xxx',
		                'Selects donate panel xxx');
		// display ManiaLink message
		display_manialink($login, $header, array('Icons64x64_1', 'TrackInfo', -0.01), $help, array(0.8, 0.05, 0.15, 0.6), 'OK');
	}

	elseif ($command['params'] == 'list') {
		$player->maplist = array();

		// read list of donate panel files
		$paneldir = 'configs/panels/';
		$dir = opendir($paneldir);
		$files = array();
		while (($file = readdir($dir)) !== false) {
			if (strtolower(substr($file, 0, 6)) == 'donate' &&
			    strtolower(substr($file, -4)) == '.xml')
				$files[] = substr($file, 6, strlen($file)-10);
		}
		closedir($dir);
		sort($files, SORT_STRING);
		if (count($files) > 20) {
			$files = array_slice($files, 0, 20);  // maximum 20 templates
			trigger_error('Too many donate panel templates - maximum 20!', E_USER_WARNING);
		}
		// sneak in standard entries
		$files[] = 'default';
		$files[] = 'off';

		$head = 'Currently available donate panels:';
		$list = array();
		$pid = 1;
		$lines = 0;
		$player->msgs = array();
		$player->msgs[0] = array(1, $head, array(0.8, 0.1, 0.7), array('Icons128x128_1', 'Custom'));
		foreach ($files as $file) {
			// store panel in player object for jukeboxing
			$trkarr = array();
			$trkarr['panel'] = $file;
			$player->maplist[] = $trkarr;

			$list[] = array(str_pad($pid, 2, '0', STR_PAD_LEFT) . '.',
			                array('{#black}' . $file, $pid+7200));  // action id
			$pid++;
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
	}

	elseif ($command['params'] != '') {
		$panel = $command['params'];
		if (is_numeric($panel) && $panel > 0) {
			$pid = ltrim($panel, '0');
			$pid--;
			if (array_key_exists($pid, $player->maplist) &&
			    isset($player->maplist[$pid]['panel'])) {
				$panel = $player->maplist[$pid]['panel'];
			}
		}
		if ($panel == 'off') {
			$player->panels['donate'] = '';
			donpanel_off($aseco, $login);
			$message = '{#server}> Donate panel disabled!';
			ldb_setPanel($aseco, $login, 'donate', '');
		}
		elseif ($panel == 'default') {
			$player->panels['donate'] = set_panel_bg($aseco->panels['donate'], $player->panelbg);
			display_donpanel($aseco, $player, $donation_values);
			$message = '{#server}> Donate panel reset to server default {#highlite}' . substr($aseco->settings['donate_panel'], 6) . '{#server} !';
			ldb_setPanel($aseco, $login, 'donate', $aseco->settings['donate_panel']);
		}
		else {
			// add file prefix
			if (strtolower(substr($panel, 0, 6)) != 'donate')
				$panel = 'Donate' . $panel;
			$panel_file = 'configs/panels/' . $panel . '.xml';
			// load new panel
			if ($paneldata = @file_get_contents($panel_file)) {
				$player->panels['donate'] = set_panel_bg($paneldata, $player->panelbg);
				display_donpanel($aseco, $player, $donation_values);
				$message = '{#server}> Donate panel {#highlite}' . $command['params'] . '{#server} selected!';
				ldb_setPanel($aseco, $login, 'donate', $panel);
			} else {
				// Could not read XML file
				trigger_error('Could not read donate panel file ' . $panel_file . ' !', E_USER_WARNING);
				$message = '{#server}> {#error}No valid donate panel file, use {#highlite}$i /donpanel list {#error}!';
			}
		}
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
	}

	else {
		$message = '{#server}> {#error}No donate panel specified, use {#highlite}$i /donpanel help {#error}!';
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
	}
}  // chat_donpanel

// called @ onPlayerConnect
function load_donpanel($aseco, $player) {
	global $donation_values;  // from plugin.donate.php

	// check for donation plugin
	if (function_exists('chat_donate') && $player->panels['donate'] != '') {
		display_donpanel($aseco, $player, $donation_values);
	}
}  // load_donpanel

// called @ onBeginMap2
function display_alldonpanels($aseco, $data) {
	global $donation_values;  // from plugin.donate.php

	// check for donation plugin
	if (function_exists('chat_donate')) {
		// display donate panel for all players that use a panel
		foreach ($aseco->server->players->player_list as &$player) {
			if ($player->panels['donate'] != '')
				display_donpanel($aseco, $player, $donation_values);
		}
	}
}  // display_alldonpanels

          
function chat_votepanel($aseco, $command) {

	$player = $command['author'];
	$login = $player->login;

	if ($command['params'] == 'help') {
		$header = '{#black}/votepanel$g will change the vote panel:';
		$help = array();
		$help[] = array('...', '{#black}help',
		                'Displays this help information');
		$help[] = array('...', '{#black}list',
		                'Displays available panels');
		$help[] = array('...', '{#black}default',
		                'Resets panel to server default');
		$help[] = array('...', '{#black}off',
		                'Disables vote panel');
		$help[] = array('...', '{#black}xxx',
		                'Selects vote panel xxx');
		// display ManiaLink message
		display_manialink($login, $header, array('Icons64x64_1', 'TrackInfo', -0.01), $help, array(0.8, 0.05, 0.15, 0.6), 'OK');
	}

	elseif ($command['params'] == 'list') {
		$player->maplist = array();

		// read list of vote panel files
		$paneldir = 'configs/panels/';
		$dir = opendir($paneldir);
		$files = array();
		while (($file = readdir($dir)) !== false) {
			if (strtolower(substr($file, 0, 4)) == 'vote' &&
			    strtolower(substr($file, -4)) == '.xml')
				$files[] = substr($file, 4, strlen($file)-8);
		}
		closedir($dir);
		sort($files, SORT_STRING);
		if (count($files) > 10) {
			$files = array_slice($files, 0, 10);  // maximum 10 templates
			trigger_error('Too many vote panel templates - maximum 10!', E_USER_WARNING);
		}
		// sneak in standard entries
		$files[] = 'default';
		$files[] = 'off';

		$head = 'Currently available vote panels:';
		$list = array();
		$pid = 1;
		$lines = 0;
		$player->msgs = array();
		$player->msgs[0] = array(1, $head, array(0.8, 0.1, 0.7), array('Icons128x128_1', 'Custom'));
		foreach ($files as $file) {
			// store panel in player object for jukeboxing
			$trkarr = array();
			$trkarr['panel'] = $file;
			$player->maplist[] = $trkarr;

			$list[] = array(str_pad($pid, 2, '0', STR_PAD_LEFT) . '.',
			                array('{#black}' . $file, $pid+36));  // action id
			$pid++;
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
	}

	elseif ($command['params'] != '') {
		$panel = $command['params'];
		if (is_numeric($panel) && $panel > 0) {
			$pid = ltrim($panel, '0');
			$pid--;
			if (array_key_exists($pid, $player->maplist) &&
			    isset($player->maplist[$pid]['panel'])) {
				$panel = $player->maplist[$pid]['panel'];
			}
		}
		if ($panel == 'off') {
			$player->panels['vote'] = '';
			$message = '{#server}> Vote panel disabled!';
			ldb_setPanel($aseco, $login, 'vote', '');
		}
		elseif ($panel == 'default') {
			$player->panels['vote'] = set_panel_bg($aseco->panels['vote'], $player->panelbg);
			display_votepanel($aseco, $player, $aseco->formatColors('{#emotic}') . 'Yes - F5', '$333No - F6', 2000);
			$message = '{#server}> Vote panel reset to server default {#highlite}' . substr($aseco->settings['vote_panel'], 4) . '{#server} !';
			ldb_setPanel($aseco, $login, 'vote', $aseco->settings['vote_panel']);
		}
		else {
			// add file prefix
			if (strtolower(substr($panel, 0, 4)) != 'vote')
				$panel = 'Vote' . $panel;
			$panel_file = 'configs/panels/' . $panel . '.xml';
			// load new panel
			if ($paneldata = @file_get_contents($panel_file)) {
				$player->panels['vote'] = set_panel_bg($paneldata, $player->panelbg);
				display_votepanel($aseco, $player, $aseco->formatColors('{#vote}') . 'Yes - F5', '$333No - F6', 2000);
				$message = '{#server}> Vote panel {#highlite}' . $command['params'] . '{#server} selected!';
				ldb_setPanel($aseco, $login, 'vote', $panel);
			} else {
				// Could not read XML file
				trigger_error('Could not read vote panel file ' . $panel_file . ' !', E_USER_WARNING);
				$message = '{#server}> {#error}No valid vote panel file, use {#highlite}$i /votepanel list {#error}!';
			}
		}
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
	}

	else {
		$message = '{#server}> {#error}No vote panel specified, use {#highlite}$i /votepanel help {#error}!';
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
	}
}  // chat_votepanel

function allvotepanels_on($aseco, $login, $ycolor) {
	global $auto_vote_starter, $allow_spec_voting;

	// enable all vote panels
	foreach ($aseco->server->players->player_list as $player) {
		// check if vote starter hasn't auto-voted
		if ($player->login != $login || !$auto_vote_starter) {
			// check for spectators
			if ($aseco->isSpectator($player)) {
				// check whether they can vote (no function keys)
				if ($allow_spec_voting || $aseco->isAnyAdmin($player))
					display_votepanel($aseco, $player, $ycolor . 'Yes', '$333No', 0);
			} else {  // player, so function keys work
				display_votepanel($aseco, $player, $ycolor . 'Yes - F5', '$333No - F6', 0);
			}
		}
	}
}  // allvotepanels_on


function admin_panel($aseco, $command) {

	$player = $command['author'];
	$login = $player->login;

	if ($command['params'] == 'help') {
		$header = '{#black}/admin panel$g will change the admin panel:';
		$help = array();
		$help[] = array('...', '{#black}help',
		                'Displays this help information');
		$help[] = array('...', '{#black}list',
		                'Displays available panels');
		$help[] = array('...', '{#black}default',
		                'Resets panel to server default');
		$help[] = array('...', '{#black}off',
		                'Disables admin panel');
		$help[] = array('...', '{#black}xxx',
		                'Selects admin panel xxx');
		// display ManiaLink message
		display_manialink($login, $header, array('Icons64x64_1', 'TrackInfo', -0.01), $help, array(0.8, 0.05, 0.15, 0.6), 'OK');
	}

	elseif ($command['params'] == 'list') {
		$player->maplist = array();

		// read list of admin panel files
		$paneldir = 'configs/panels/';
		$dir = opendir($paneldir);
		$files = array();
		while (($file = readdir($dir)) !== false) {
			if (strtolower(substr($file, 0, 5)) == 'admin' &&
			    strtolower(substr($file, -4)) == '.xml')
				$files[] = substr($file, 5, strlen($file)-9);
		}
		closedir($dir);
		sort($files, SORT_STRING);
		if (count($files) > 40) {
			$files = array_slice($files, 0, 40);  // maximum 40 templates
			trigger_error('Too many admin panel templates - maximum 40!', E_USER_WARNING);
		}
		// sneak in standard entries
		$files[] = 'default';
		$files[] = 'off';

		$head = 'Currently available admin panels:';
		$list = array();
		$pid = 1;
		$lines = 0;
		$player->msgs = array();
		$player->msgs[0] = array(1, $head, array(0.8, 0.1, 0.7), array('Icons128x128_1', 'Custom'));
		foreach ($files as $file) {
			// store panel in player object for jukeboxing
			$trkarr = array();
			$trkarr['panel'] = $file;
			$player->maplist[] = $trkarr;

			$list[] = array(str_pad($pid, 2, '0', STR_PAD_LEFT) . '.',
			                array('{#black}' . $file, -6-$pid));  // action id
			$pid++;
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
	}

	elseif ($command['params'] != '') {
		$panel = $command['params'];
		if (is_numeric($panel) && $panel > 0) {
			$pid = ltrim($panel, '0');
			$pid--;
			if (array_key_exists($pid, $player->maplist) &&
			    isset($player->maplist[$pid]['panel'])) {
				$panel = $player->maplist[$pid]['panel'];
			}
		}
		if ($panel == 'off') {
			$player->panels['admin'] = '';
			admpanel_off($aseco, $login);
			$message = '{#server}> Admin panel disabled!';
			ldb_setPanel($aseco, $login, 'admin', '');
		}
		elseif ($panel == 'default') {
			$player->panels['admin'] = set_panel_bg($aseco->panels['admin'], $player->panelbg);
			load_admpanel($aseco, $player);
			$message = '{#server}> Admin panel reset to server default {#highlite}' . substr($aseco->settings['admin_panel'], 5) . '{#server} !';
			ldb_setPanel($aseco, $login, 'admin', $aseco->settings['admin_panel']);
		}
		else {
			// add file prefix
			if (strtolower(substr($panel, 0, 5)) != 'admin')
				$panel = 'Admin' . $panel;
			$panel_file = 'configs/panels/' . $panel . '.xml';
			// load new panel
			if ($paneldata = @file_get_contents($panel_file)) {
				$player->panels['admin'] = set_panel_bg($paneldata, $player->panelbg);
				load_admpanel($aseco, $player);
				$message = '{#server}> Admin panel {#highlite}' . $command['params'] . '{#server} selected!';
				ldb_setPanel($aseco, $login, 'admin', $panel);
			} else {
				// Could not read XML file
				trigger_error('Could not read admin panel file ' . $panel_file . ' !', E_USER_WARNING);
				$message = '{#server}> {#error}No valid admin panel file, use {#highlite}$i /admin panel list {#error}!';
			}
		}
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
	}

	else {
		$message = '{#server}> {#error}No admin panel specified, use {#highlite}$i /admin panel help {#error}!';
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
	}
}  // admin_panel

// called @ onPlayerConnect
function load_admpanel($aseco, $player) {

	// check for any admin
	if ($aseco->isAnyAdmin($player) && $player->panels['admin'] != '') {
		display_admpanel($aseco, $player);
	}
}  // load_admpanel


function chat_panelbg($aseco, $command) {

	$player = $command['author'];
	$login = $player->login;

	if ($command['params'] == 'help') {
		$header = '{#black}/panelbg$g will change the panel background:';
		$help = array();
		$help[] = array('...', '{#black}help',
		                'Displays this help information');
		$help[] = array('...', '{#black}list',
		                'Displays available backgrounds');
		$help[] = array('...', '{#black}default',
		                'Resets background to server default');
		$help[] = array('...', '{#black}xxx',
		                'Selects panel background xxx');
		// display ManiaLink message
		display_manialink($login, $header, array('Icons64x64_1', 'TrackInfo', -0.01), $help, array(0.8, 0.05, 0.15, 0.6), 'OK');
	}

	elseif ($command['params'] == 'list') {
		$player->maplist = array();

		// read list of background files
		$paneldir = 'configs/panels/';
		$dir = opendir($paneldir);
		$files = array();
		while (($file = readdir($dir)) !== false) {
			if (strtolower(substr($file, 0, 7)) == 'panelbg' &&
			    strtolower(substr($file, -4)) == '.xml')
				$files[] = substr($file, 7, strlen($file)-11);
		}
		closedir($dir);
		sort($files, SORT_STRING);
		if (count($files) > 30) {
			$files = array_slice($files, 0, 30);  // maximum 30 templates
			trigger_error('Too many panel background templates - maximum 30!', E_USER_WARNING);
		}
		// sneak in standard entry
		$files[] = 'default';

		$head = 'Currently available panel backgrounds:';
		$list = array();
		$sid = 1;
		$lines = 0;
		$player->msgs = array();
		$player->msgs[0] = array(1, $head, array(0.8, 0.1, 0.7), array('Icons128x32_1', 'Windowed'));
		foreach ($files as $file) {
			// store background in player object for jukeboxing
			$trkarr = array();
			$trkarr['panel'] = $file;
			$player->maplist[] = $trkarr;

			$list[] = array(str_pad($sid, 2, '0', STR_PAD_LEFT) . '.',
			                array('{#black}' . $file, $sid+7230));  // action id
			$sid++;
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
	}

	elseif ($command['params'] != '') {
		$panelbg = $command['params'];
		if (is_numeric($panelbg) && $panelbg > 0) {
			$sid = ltrim($panelbg, '0');
			$sid--;
			if (array_key_exists($sid, $player->maplist) &&
			    isset($player->maplist[$sid]['panel'])) {
				$panelbg = $player->maplist[$sid]['panel'];
			}
		}
		if ($panelbg == 'default') {
			$player->panelbg = $aseco->panelbg;
			$message = '{#server}> Panel background reset to server default {#highlite}' . $aseco->settings['panel_bg'] . '{#server} !';
			ldb_setPanelBG($aseco, $login, $aseco->settings['panel_bg']);

			init_playerpanels($aseco, $player);
			load_donpanel($aseco, $player);
			load_admpanel($aseco, $player);
		//	load_recpanel($aseco, $player);
			display_votepanel($aseco, $player, $aseco->formatColors('{#emotic}') . 'Yes - F5', '$333No - F6', 2000);
		}
		else {
			// add file prefix
			if (strtolower(substr($panelbg, 0, 7)) != 'panelbg')
                                $panelbg = 'PanelBG' . $panelbg;
			$panelbg_file = 'configs/panels/' . $panelbg . '.xml';
			// load new background
			if (($panelbgdata = $aseco->xml_parser->parseXml($panelbg_file)) && isset($panelbgdata['PANEL']['BACKGROUND'][0])) {
				$player->panelbg = $panelbgdata['PANEL']['BACKGROUND'][0];
				$message = '{#server}> Panel background {#highlite}' . $command['params'] . '{#server} selected!';
				ldb_setPanelBG($aseco, $login, $panelbg);

				init_playerpanels($aseco, $player);
				load_donpanel($aseco, $player);
				load_admpanel($aseco, $player);
		//		load_recpanel($aseco, $player);
				display_votepanel($aseco, $player, $aseco->formatColors('{#emotic}') . 'Yes - F5', '$333No - F6', 2000);
			} else {
				// Could not parse XML file
				trigger_error('Could not read/parse panel background file ' . $panelbg_file . ' !', E_USER_WARNING);
				$message = '{#server}> {#error}No valid panel background file, use {#highlite}$i /panelbg list {#error}!';
			}
		}
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
	}

	else {
		$message = '{#server}> {#error}No panel background specified, use {#highlite}$i /panelbg help {#error}!';
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
	}
}  // chat_panelbg


// called @ onPlayerManialinkPageAnswer
// Handles ManiaLink panel responses
// [0]=PlayerUid, [1]=Login, [2]=Answer, [3]=Entries
function event_panels($aseco, $answer) {

	// leave actions outside -7 - -100 & 7201 - 7222 & 7231 - 7262 to other handlers
	$action = (int) $answer[2];
	if ($action >= -100 && $action <= -49) {
		// get player & records panel
		$player = $aseco->server->players->getPlayer($answer[1]);
		$panel = $player->maplist[abs($action)-49]['panel'];

		// select new panel
		$command = array();
		$command['author'] = $player;
		$command['params'] = $panel;
	//	chat_recpanel($aseco, $command);
	}

	elseif ($action >= -48 && $action <= -7) {
		// get player & admin panel
		$player = $aseco->server->players->getPlayer($answer[1]);
		$panel = $player->maplist[abs($action)-7]['panel'];

		// log clicked command
		$aseco->console('player {1} clicked command "/admin panel {2}"',
		                $player->login, $panel);

		// select new panel
		$command = array();
		$command['author'] = $player;
		$command['params'] = $panel;
		admin_panel($aseco, $command);
	}

	elseif ($action >= 37 && $action <= 48) {
		// get player & vote panel
		$player = $aseco->server->players->getPlayer($answer[1]);
		$panel = $player->maplist[$action-37]['panel'];

		// log clicked command
		$aseco->console('player {1} clicked command "/votepanel {2}"',
		                $player->login, $panel);

		// select new panel
		$command = array();
		$command['author'] = $player;
		$command['params'] = $panel;
		chat_votepanel($aseco, $command);
	}

	elseif ($action >= 7201 && $action <= 7222) {
		// get player & donate panel
		$player = $aseco->server->players->getPlayer($answer[1]);
		$panel = $player->maplist[abs($action)-7201]['panel'];

		// log clicked command
		$aseco->console('player {1} clicked command "/donpanel {2}"',
		                $player->login, $panel);

		// select new panel
		$command = array();
		$command['author'] = $player;
		$command['params'] = $panel;
		chat_donpanel($aseco, $command);
	}

	elseif ($action >= 7231 && $action <= 7262) {
		// get player & panel background
		$player = $aseco->server->players->getPlayer($answer[1]);
		$panel = $player->maplist[abs($action)-7231]['panel'];

		// log clicked command
		$aseco->console('player {1} clicked command "/panelbg {2}"',
		                $player->login, $panel);

		// select new background
		$command = array();
		$command['author'] = $player;
		$command['params'] = $panel;
		chat_panelbg($aseco, $command);
	}
}  // event_panels
?>
