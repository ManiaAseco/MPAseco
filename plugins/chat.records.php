<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Chat plugin.
 * Displays all records of the current map.
 * Updated by Xymph
 *
 * Dependencies: none
 */
if (!INHIBIT_RECCMDS) 
  Aseco::addChatCommand('recs', 'Displays all records on current map');

function chat_recs($aseco, $command) {

	$player = $command['author'];
	$login = $player->login;

	// split params into array
	$arglist = explode(' ', strtolower(preg_replace('/ +/', ' ', $command['params'])));

	// process optional relations commands
	if ($arglist[0] == 'help') {
		$header = '{#black}/recs <option>$g shows local records and relations:';
		$help = array();
		$help[] = array('...', '{#black}help',
		                'Displays this help information');
		$help[] = array('...', '{#black}pb',
		                'Shows your personal best on current map');
		$help[] = array('...', '{#black}new',
		                'Shows newly driven records');
		$help[] = array('...', '{#black}live',
		                'Shows records of online players');
		$help[] = array('...', '{#black}first',
		                'Shows first ranked record on current map');
		$help[] = array('...', '{#black}last',
		                'Shows last ranked record on current map');
		$help[] = array('...', '{#black}next',
		                'Shows next better ranked record to beat');
		$help[] = array('...', '{#black}diff',
		                'Shows your difference to first ranked record');
		$help[] = array('...', '{#black}range',
		                'Shows difference first to last ranked record');
		$help[] = array();
		$help[] = array('Without an option, the normal records list is displayed.');

		// display ManiaLink message
		display_manialink($login, $header, array('Icons64x64_1', 'TrackInfo', -0.01), $help, array(1.1, 0.05, 0.3, 0.75), 'OK');
		return;
	}
	elseif ($arglist[0] == 'pb') {
		chat_pb($aseco, $command);
		return;
	}
	elseif ($arglist[0] == 'new') {
		chat_newrecs($aseco, $command);
		return;
	}
	elseif ($arglist[0] == 'live') {
		chat_liverecs($aseco, $command);
		return;
	}
	elseif ($arglist[0] == 'first') {
		chat_firstrec($aseco, $command);
		return;
	}
	elseif ($arglist[0] == 'last') {
		chat_lastrec($aseco, $command);
		return;
	}
	elseif ($arglist[0] == 'next') {
		chat_nextrec($aseco, $command);
		return;
	}
	elseif ($arglist[0] == 'diff') {
		chat_diffrec($aseco, $command);
		return;
	}
	elseif ($arglist[0] == 'range') {
		chat_recrange($aseco, $command);
		return;
	}

	// check for relay server
	if ($aseco->server->isrelay) {
		$message = formatText($aseco->getChatMessage('NOTONRELAY'));
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
		return;
	}

	if (!$total = $aseco->server->records->count()) {
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}No records found!'), $login);
		return;
	}

	// display ManiaLink window
	$head = 'Current TOP ' . $aseco->server->records->max . ' Local Records:';
	$msg = array();
	$lines = 0;
	$player->msgs = array();
	// reserve extra width for $w tags
	$extra = ($aseco->settings['lists_colornicks'] ? 0.2 : 0);
	if ($aseco->settings['show_rec_logins'])
		$player->msgs[0] = array(1, $head, array(1.2+$extra, 0.1, 0.45+$extra, 0.4, 0.25), array('BgRaceScore2', 'Podium'));
	else
		$player->msgs[0] = array(1, $head, array(0.8+$extra, 0.1, 0.45+$extra, 0.25), array('BgRaceScore2', 'Podium'));

	// create list of records
	for ($i = 0; $i < $total; $i++) {
		$cur_record = $aseco->server->records->getRecord($i);
		$nick = $cur_record->player->nickname;
		if (!$aseco->settings['lists_colornicks'])
			$nick = stripColors($nick);
		if ($aseco->settings['show_rec_logins']) {
			$msg[] = array(str_pad($i+1, 2, '0', STR_PAD_LEFT) . '.',
			               '{#black}' . $nick,
			               '{#login}' . $cur_record->player->login,
			               ($cur_record->new ? '{#black}' : '') .
			               ($aseco->server->gameinfo->mode == Gameinfo::STNT ?
			                $cur_record->score : formatTime($cur_record->score)));
		} else {
			$msg[] = array(str_pad($i+1, 2, '0', STR_PAD_LEFT) . '.',
			               '{#black}' . $nick,
			               ($cur_record->new ? '{#black}' : '') .
			               ($aseco->server->gameinfo->mode == Gameinfo::STNT ?
			                $cur_record->score : formatTime($cur_record->score)));
		}
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
}  // chat_recs
?>
