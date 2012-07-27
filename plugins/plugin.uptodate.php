<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Uptodate plugin.
 * Checks MPAseco version at start-up & MasterAdmin connect, and provides
 * /admin uptodate command.
 * Also merges global blacklist at MasterAdmin connect, and provides
 * /admin mergegbl command.
 *
 * Created by Xymph
 * Edited for ShootMania by the MPAseco team
 *
 * Dependencies: used by chat.admin.php
 */

Aseco::registerEvent('onSync', 'start_uptodate');
Aseco::registerEvent('onPlayerConnect', 'connect_uptodate');
Aseco::addChatCommand('uptodate', 'Checks current version of MPASECO', true);

function up_to_date($aseco) {

	$version_url = MPASECO . 'version2.txt';  // URL to current version file

	// grab version file
	$current = trim(http_get_file($version_url));
	if ($current && $current != -1) {
		// compare versions                     str_replace('{br}', LF, $this->getChatMessage('CONNECT_ERROR'));
		if ($current > MPASECO_VERSION) {
			$message = formatText($aseco->getChatMessage('UPTODATE_NEW'), $current,
			                      // hyperlink release page
			                      '$l[' . MPASECO  . ']' . MPASECO  . '$l');
		} else {
			$message = formatText($aseco->getChatMessage('UPTODATE_OK'), MPASECO_VERSION);
		}
	} else {
		$message = false;
	}
	$message=str_replace('{br}', LF, $message);
	return $message;
}  // up_to_date

// called @ onSync
function start_uptodate($aseco, $command) {
	global $uptodate_check;

	// check version but ignore error
	if ($uptodate_check && $message = up_to_date($aseco)) {
		// show chat message
		$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
	}
}  // start_uptodate

// called @ onPlayerConnect
function connect_uptodate($aseco, $player) {
	global $uptodate_check, $globalbl_merge, $globalbl_url;

	// check for a master admin
	if ($aseco->isMasterAdmin($player)) {
		// check version but ignore error
		if ($uptodate_check && $message = up_to_date($aseco)) {
			// check whether out of date
			if (!preg_match('/' . formatText($aseco->getChatMessage('UPTODATE_OK'), '.*') . '/', $message)) {
				// strip 1 leading '>' to indicate a player message instead of system-wide
				$message = str_replace('{#server}>> ', '{#server}> ', $message);
				// show chat message
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
			}
		}

		// check whether to merge global black list
		if ($globalbl_merge && $globalbl_url != '') {
			admin_mergegbl($aseco, 'MasterAdmin', $player->login, false, $globalbl_url);
		}
	}
}  // connect_uptodate

function admin_uptodate($aseco, $command) {

	$login = $command['author']->login;

	// check version or report error
	if ($message = up_to_date($aseco)) {
		// strip 1 leading '>' to indicate a player message instead of system-wide
		$message = str_replace('{#server}>> ', '{#server}> ', $message);
		// show chat message
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
	} else {
		$message = '{#server}> {#error}Error: can\'t access the last version!';
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
	}
}  // admin_uptodate

function admin_mergegbl($aseco, $logtitle, $login, $manual, $url) {

	// download & parse global black list
	$blacklist = http_get_file($url);
	if ($blacklist && $blacklist != -1) {
		if ($globals = $aseco->xml_parser->parseXml($blacklist, false)) {
			// get current black list
			$blacks = get_blacklist($aseco);  // from chat.admin.php

			// merge new global entries
			$new = 0;
			foreach ($globals['BLACKLIST']['PLAYER'] as $black) {
				if (!array_key_exists($black['LOGIN'][0], $blacks)) {
					$aseco->client->addCall('BlackList', array($black['LOGIN'][0]));
					$new++;
				}
			}
			// send all entries and ignore results
			if (!$aseco->client->multiquery(true)) {
				trigger_error('[' . $this->client->getErrorCode() . '] BlackList (merge) - ' . $this->client->getErrorMessage(), E_USER_ERROR);
			}

			// update black list file if necessary
			if ($new > 0) {
				$filename = $aseco->settings['blacklist_file'];
				$aseco->client->addCall('SaveBlackList', array($filename));
			}

			// check whether to report new mergers
			if ($new > 0 || $manual) {
				// log console message
				$aseco->console('{1} [{2}] merged global blacklist [{3}] new: {4}', $logtitle, $login, $url, $new);

				// show chat message
				$message = formatText('{#server}> {#highlite}{1} {#server}new login{2} merged into blacklist',
				                      $new, ($new == 1 ? '' : 's'));
				$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
			}
		} else {
			$message = formatText('{#server}> {#error}Error: can\'t parse {#highlite}$i{1}{#error}!',
			                      $url);
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
		}
	} else {
		$message = formatText('{#server}> {#error}Error: can\'t access {#highlite}$i{1}{#error}!',
		                      $url);
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
	}
}  // admin_mergegbl

/** TODO: check if plugins are up to date (TheM)
 * ------------------------------------------------------------------------------------
 * Plugins up-to-date
 * ------------------------------------------------------------------------------------
 *
 * List of plugins:
 * ---------------------------------------------------------------------------
 * | Upd. Type | Pluginname | Author | Cur. ver. | Lat. ver. | Req. MPA ver. |
 * ---------------------------------------------------------------------------
 *
 * List is shown when using /checkplugins (MA only) and when a MasterAdmin connects
 * to the server (like on undef's version for XAseco(2)).
 *
 * Pluginwindow:
 * ---------------------------------------------------------------------------
 * | Pluginname:  .........                 Current version: x.xx            |
 * | Author:      .........                 Latest version:  x.xx            |
 * | Description: .......................................................... |
 * | ....................................................................... |
 * | Update type: critical / beta / normal                                   |
 * |                                                                         |
 * |  (!! Your MPAseco is too old for this update, update your MPAseco !!)   |
 * |                                                                         |
 * | Changelog: ............................................................ |
 * | ....................................................................... |
 * | ....................................................................... |
 * | ....................................................................... |
 * | Information link: _click_                                               |
 * | Download link:    _click_                                               |
 * ---------------------------------------------------------------------------
 *
 * A pluginwindow is shown when you click on one of the plugins in the list.
 * This window provides you with more information about the plugin and what
 * changed in the update.
 *
 * ------------------------------------------------------------------------------------
 *
 * Saving the plugins in MPAseco might be done like in XAseco, make an array
 * in $aseco ($aseco->plugins ?) and let all the plugins make an entry in this
 * array.
 *
 * Administration of the plugins should be done on the MPAseco.org website,
 * where people can upload their plugins (maybe to risky ... link to own site ?)
 * and enter information about those plugins.
 * A file will make a JSON object including all those plugins (dunno if this is
 * slow ?), which MPAseco can retrieve and check for updates on plugins on the server.
 *
 * @Idea: maybe a "featured plugin" function, showing the MA's a nice plugin
 * which they might wanna use on their server (pop-up in listwindow, or maybe by
 * making a sidebar in that list ?).
 */
?>