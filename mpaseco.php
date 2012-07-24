<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Projectname: MPAseco
 *
 * Requires: PHP version 5, MySQL version 4/5
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * @license             http://www.gnu.org/copyleft/gpl.html GNU GPL
 *
 * Project further developed for SM and QM from July 2012 - ? 
 *  by kremsy and his MPAseco-Team <server@esc-clan.net> www.mania-server.net
 * Authored & copyright Aug 2011 - Jan 2012 by Xymph <tm@gamers.org>
 * Derived from XAseco (formerly ASECO/RASP) by Xymph, Flo and others
 */

/**
 * Include required classes
 */
require_once('includes/types.inc.php');  // contains classes to store information
require_once('includes/basic.inc.php');  // contains standard functions
require_once('includes/GbxRemote.inc.php');  // needed for dedicated server connections
require_once('includes/xmlparser.inc.php');  // provides an XML parser
require_once('includes/gbxdatafetcher.inc.php');  // provides access to GBX data
require_once('includes/rasp.settings.php');  // specific to the RASP plugins

/**
 * Runtime configuration definitions
 */

// add abbreviations for some chat commands?
// /admin -> /ad, /jukebox -> /jb, /autojuke -> /aj
define('ABBREV_COMMANDS', false);
// separate logs by month in logs/ dir?
define('MONTHLY_LOGSDIR', false);
// keep UTF-8 encoding in config.xml?
define('CONFIG_UTF8ENCODE', false);

/**
 * System definitions - no changes below this point
 */

// current project version
define('MPASECO_VERSION', '0.30');
define('XASECO_TMN', 'http://www.gamers.org/tmn/');
define('XASECO_TMF', 'http://www.gamers.org/tmf/');
define('XASECO_TM2', 'http://www.gamers.org/tm2/');
define('XASECO_ORG', 'http://www.xaseco.org/');
define('MPASECO', 'http://www.mania-server.net/mpaseco/');

// required official dedicated server build
define('MP_BUILD', '2012-07-19-xx_xx');
define('API_VERSION', '2012-06-19');

// check current operating system
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
	// on Win32/NT use:
	define('CRLF', "\r\n");
} else {
	// on Unix use:
	define('CRLF', "\n");
}
if (!defined('LF')) {
	define('LF', "\n");
}

/**
 * Error function
 * Report errors in a regular way.
 */
set_error_handler('displayError');
function displayError($errno, $errstr, $errfile, $errline) {
	global $aseco;

	// check for error suppression
	if (error_reporting() == 0) return;

	switch ($errno) {
	case E_USER_ERROR:
		$message = "[MPAseco Fatal Error] $errstr on line $errline in file $errfile" . CRLF;
		echo $message;
		doLog($message);

		// throw 'shutting down' event
		$aseco->releaseEvent('onShutdown', null);
		// clear all ManiaLinks
		$aseco->client->query('SendHideManialinkPage');

		if (function_exists('xdebug_get_function_stack'))
			doLog(print_r(xdebug_get_function_stack()), true);
		die();
		break;
	case E_USER_WARNING:
		$message = "[MPAseco Warning] $errstr" . CRLF;
		echo $message;
		doLog($message);
		break;
	case E_ERROR:
		$message = "[PHP Error] $errstr on line $errline in file $errfile" . CRLF;
		echo $message;
		doLog($message);
		break;
	case E_WARNING:
		$message = "[PHP Warning] $errstr on line $errline in file $errfile" . CRLF;
		echo $message;
		doLog($message);
		break;
	case E_NOTICE:
		if (strpos($errfile, 'plugin.fufi') !== false) break;
		$message = "[PHP Notice] $errstr on line $errline in file $errfile" . CRLF;
		echo $message;
		doLog($message);
		break;
	default:
		if (strpos($errstr, 'Function call_user_method') !== false) break;
		$message = "[PHP $errno] $errstr on line $errline in file $errfile" . CRLF;
		echo $message;
		doLog($message);
		// do nothing, only treat known errors
	}
}  // displayError

/**
 * Here MPAseco actually starts.
 */
class Aseco {

	/**
	 * Public fields
	 */
	var $client;
	var $xml_parser;
	var $script_timeout;
	var $debug;
	var $server;
	var $command;
	var $events;
	var $rpc_calls;
	var $rpc_responses;
	var $chat_commands;
	var $chat_colors;
	var $chat_messages;
	var $plugins;
	var $settings;
	var $style;
	var $panels;
	var $panelbg;
	var $statspanel;
	var $titles;
	var $masteradmin_list;
	var $admin_list;
	var $adm_abilities;
	var $operator_list;
	var $op_abilities;
	var $bannedips;
	var $startup_phase;  // MPAseco start-up phase
	var $warmup_phase;  // warm-up phase
	var $restarting;  // restarting map (0 = not, 1 = instant, 2 = chattime)
	var $changingmode;  // changing game mode
	var $currstatus;  // server status changes
	var $prevstatus;
	var $currsecond;  // server time changes
	var $prevsecond;
	var $uptime;  // MPAseco start-up time
	
  public $smrankings; // XXX: Temporary rankings


	/**
	 * Initializes the server.
	 */
	function Aseco($debug) {
		global $maxrecs;  // from rasp.settings.php

		echo '# initialize MPASECO ###########################################################' . CRLF;

		// log php & mysql version info
		$this->console_text('[MPAseco] PHP Version is ' . phpversion() . ' on ' . PHP_OS);

		// initialize
		$this->uptime = time();
		$this->chat_commands = array();
		$this->debug = $debug;
		$this->client = new IXR_ClientMulticall_Gbx();
		$this->xml_parser = new Examsly();
		$this->server = new Server('127.0.0.1', 5000, 'SuperAdmin', 'SuperAdmin');
		$this->server->map = new Map();
		$this->server->players = new PlayerList();
		$this->server->records = new RecordList($maxrecs);
		$this->server->mutelist = array();
		$this->plugins = array();
		$this->titles = array();
		$this->masteradmin_list = array();
		$this->admin_list = array();
		$this->adm_abilities = array();
		$this->operator_list = array();
		$this->op_abilities = array();
	//	$this->bannedips = array();
		$this->startup_phase = true;
		$this->warmup_phase = false;
		$this->restarting = 0;
		$this->changingmode = false;
		$this->currstatus = 0;
    $this->endmapvar=0;
    
	}  // Aseco


	/**
	 * Load settings and apply them on the current instance.
	 */
	function loadSettings($config_file) {

		if ($settings = $this->xml_parser->parseXml($config_file, true, CONFIG_UTF8ENCODE)) {
			// read the XML structure into an array
			$aseco = $settings['SETTINGS']['MPASECO'][0];

			// read settings and apply them
			$this->chat_colors = $aseco['COLORS'][0];
			$this->chat_messages = $aseco['MESSAGES'][0];
			$this->masteradmin_list = $aseco['MASTERADMINS'][0];
			if (!isset($this->masteradmin_list) || !is_array($this->masteradmin_list))
				trigger_error('No MasterAdmin(s) configured in config.xml!', E_USER_ERROR);

			// check masteradmin list consistency
			if (empty($this->masteradmin_list['IPADDRESS'])) {
				// fill <ipaddress> list to same length as <tmlogin> list
				if (($cnt = count($this->masteradmin_list['MPLOGIN'])) > 0)
					$this->masteradmin_list['IPADDRESS'] = array_fill(0, $cnt, '');
			} else {
				if (count($this->masteradmin_list['MPLOGIN']) != count($this->masteradmin_list['IPADDRESS']))
					trigger_error("MasterAdmin mismatch between <MPLOGIN>'s and <ipaddress>'s!", E_USER_WARNING);
			}

			// set admin lock password
			$this->settings['lock_password'] = $aseco['LOCK_PASSWORD'][0];
			// set cheater action
			$this->settings['cheater_action'] = $aseco['CHEATER_ACTION'][0];
			// set script timeout
			$this->settings['script_timeout'] = $aseco['SCRIPT_TIMEOUT'][0];
			// show played time at end of map?
			$this->settings['show_playtime'] = $aseco['SHOW_PLAYTIME'][0];
			// show current map at start?
			$this->settings['show_curmap'] = $aseco['SHOW_CURMAP'][0];
			// set default filename for readmaplist/writemaplist
			$this->settings['default_maplist'] = $aseco['DEFAULT_MAPLIST'][0];
			// set minimum number of ranked players in a clan to be included in /topclans
			$this->settings['topclans_minplayers'] = $aseco['TOPCLANS_MINPLAYERS'][0];
			// set multiple of win count to show global congrats message
			$this->settings['global_win_multiple'] = ($aseco['GLOBAL_WIN_MULTIPLE'][0] > 0 ? $aseco['GLOBAL_WIN_MULTIPLE'][0] : 1);
			// timeout of the message window in seconds
			$this->settings['window_timeout'] = $aseco['WINDOW_TIMEOUT'][0];
			// set filename of admin/operator/ability lists file
			$this->settings['adminops_file'] = $aseco['ADMINOPS_FILE'][0];
/*			// set filename of banned IPs list file
			$this->settings['bannedips_file'] = $aseco['BANNEDIPS_FILE'][0];       */
			// set filename of blacklist file
			$this->settings['blacklist_file'] = $aseco['BLACKLIST_FILE'][0];
			// set filename of guestlist file
			$this->settings['guestlist_file'] = $aseco['GUESTLIST_FILE'][0];
			// set filename of map history file
			$this->settings['maphist_file'] = $aseco['MAPHIST_FILE'][0];
			// set minimum admin client version
			$this->settings['admin_client'] = $aseco['ADMIN_CLIENT_VERSION'][0];
			// set minimum player client version
			$this->settings['player_client'] = $aseco['PLAYER_CLIENT_VERSION'][0];
			// set default rounds points system
			$this->settings['default_rpoints'] = $aseco['DEFAULT_RPOINTS'][0];

			// set windows style (none = Card)
			$this->settings['window_style'] = $aseco['WINDOW_STYLE'][0];
			if ($this->settings['window_style'] == '')
				$this->settings['window_style'] = 'Card';

			// set admin panel (none = no panel)
			$this->settings['admin_panel'] = $aseco['ADMIN_PANEL'][0];
			// set donate panel (none = no panel)
			$this->settings['donate_panel'] = $aseco['DONATE_PANEL'][0];
			// set vote panel (none = no panel)
			$this->settings['vote_panel'] = $aseco['VOTE_PANEL'][0];

			// set panel background (none = Card)
			$this->settings['panel_bg'] = $aseco['PANEL_BG'][0];
			if ($this->settings['panel_bg'] == '')
				$this->settings['panel_bg'] = 'PanelBGCard';

			// display welcome message as window ?
			if (strtoupper($aseco['WELCOME_MSG_WINDOW'][0]) == 'TRUE') {
				$this->settings['welcome_msg_window'] = true;
			} else {
				$this->settings['welcome_msg_window'] = false;
			}

			// log all chat, not just chat commands ?
			if (strtoupper($aseco['LOG_ALL_CHAT'][0]) == 'TRUE') {
				$this->settings['log_all_chat'] = true;
			} else {
				$this->settings['log_all_chat'] = false;
			}

			// show timestamps in /chatlog, /pmlog & /admin pmlog ?
			if (strtoupper($aseco['CHATPMLOG_TIMES'][0]) == 'TRUE') {
				$this->settings['chatpmlog_times'] = true;
			} else {
				$this->settings['chatpmlog_times'] = false;
			}

			// show round reports in message window?
			if (strtoupper($aseco['ROUNDS_IN_WINDOW'][0]) == 'TRUE') {
				$this->settings['rounds_in_window'] = true;
			} else {
				$this->settings['rounds_in_window'] = false;
			}

			// add random filter to /admin writemaplist output
			if (strtoupper($aseco['WRITEMAPLIST_RANDOM'][0]) == 'TRUE') {
				$this->settings['writemaplist_random'] = true;
			} else {
				$this->settings['writemaplist_random'] = false;
			}

			// add explanation to /help output
			if (strtoupper($aseco['HELP_EXPLANATION'][0]) == 'TRUE') {
				$this->settings['help_explanation'] = true;
			} else {
				$this->settings['help_explanation'] = false;
			}

			// color nicknames in the various /top... etc lists?
			if (strtoupper($aseco['LISTS_COLORNICKS'][0]) == 'TRUE') {
				$this->settings['lists_colornicks'] = true;
			} else {
				$this->settings['lists_colornicks'] = false;
			}

			// color mapnames in the various /lists... lists?
			if (strtoupper($aseco['LISTS_COLORMAPS'][0]) == 'TRUE') {
				$this->settings['lists_colormaps'] = true;
			} else {
				$this->settings['lists_colormaps'] = false;
			}

			// display checkpoints panel?
			if (strtoupper($aseco['DISPLAY_CHECKPOINTS'][0]) == 'TRUE') {
				$this->settings['display_checkpoints'] = true;
			} else {
				$this->settings['display_checkpoints'] = false;
			}

			// enable /cpsspec command?
			if (strtoupper($aseco['ENABLE_CPSSPEC'][0]) == 'TRUE') {
				$this->settings['enable_cpsspec'] = true;
			} else {
				$this->settings['enable_cpsspec'] = false;
			}

			// automatically enable /cps for new players?
			if (strtoupper($aseco['AUTO_ENABLE_CPS'][0]) == 'TRUE') {
				$this->settings['auto_enable_cps'] = true;
			} else {
				$this->settings['auto_enable_cps'] = false;
			}

			// automatically enable /dedicps for new players?
			if (strtoupper($aseco['AUTO_ENABLE_DEDICPS'][0]) == 'TRUE') {
				$this->settings['auto_enable_dedicps'] = true;
			} else {
				$this->settings['auto_enable_dedicps'] = false;
			}

			// automatically add IP for new admins/operators?
			if (strtoupper($aseco['AUTO_ADMIN_ADDIP'][0]) == 'TRUE') {
				$this->settings['auto_admin_addip'] = true;
			} else {
				$this->settings['auto_admin_addip'] = false;
			}

			// automatically force spectator on player using /afk ?
			if (strtoupper($aseco['AFK_FORCE_SPEC'][0]) == 'TRUE') {
				$this->settings['afk_force_spec'] = true;
			} else {
				$this->settings['afk_force_spec'] = false;
			}

			// provide clickable buttons in lists?
			if (strtoupper($aseco['CLICKABLE_LISTS'][0]) == 'TRUE') {
				$this->settings['clickable_lists'] = true;
			} else {
				$this->settings['clickable_lists'] = false;
			}

			// show logins in /recs?
			if (strtoupper($aseco['SHOW_REC_LOGINS'][0]) == 'TRUE') {
				$this->settings['show_rec_logins'] = true;
			} else {
				$this->settings['show_rec_logins'] = false;
			}

			// display individual stats panels at scoreboard?
			if (strtoupper($aseco['SB_STATS_PANELS'][0]) == 'TRUE') {
				$this->settings['sb_stats_panels'] = true;
			} else {
				$this->settings['sb_stats_panels'] = false;
			}

			// read the XML structure into an array
			$mpserver = $settings['SETTINGS']['MPSERVER'][0];

			// read settings and apply them
			$this->server->login = $mpserver['LOGIN'][0];
			$this->server->pass = $mpserver['PASSWORD'][0];
			$this->server->port = $mpserver['PORT'][0];
			$this->server->ip = $mpserver['IP'][0];
			if (isset($mpserver['TIMEOUT'][0])) {
				$this->server->timeout = (int)$mpserver['TIMEOUT'][0];
			} else {
				$this->server->timeout = null;
				trigger_error('Server init timeout not specified in config.xml !', E_USER_WARNING);
			}

			// initialise default window style
			$style_file = 'styles/' . $this->settings['window_style'] . '.xml';
			$this->console_text('[MPAseco] Load default window style [{1}]', $style_file);
			// load default style
			if (($this->style = $this->xml_parser->parseXml($style_file)) && isset($this->style['STYLES'])) {
				$this->style = $this->style['STYLES'];
			} else {
				// Could not parse XML file
				trigger_error('Could not read/parse style file ' . $style_file . ' !', E_USER_ERROR);
			}

			// initialise default panel background
			$panelbg_file = 'panels/' . $this->settings['panel_bg'] . '.xml';
			$this->console_text('[MPAseco] Load default panel background [{1}]', $panelbg_file);
			// load default background
			if (($this->panelbg = $this->xml_parser->parseXml($panelbg_file)) && isset($this->panelbg['PANEL']['BACKGROUND'][0])) {
				$this->panelbg = $this->panelbg['PANEL']['BACKGROUND'][0];
			} else {
				// Could not parse XML file
				trigger_error('Could not read/parse panel background file ' . $panelbg_file . ' !', E_USER_ERROR);
			}

			$this->panels = array();
			$this->panels['admin'] = '';
			$this->panels['donate'] = '';
			$this->panels['records'] = '';
			$this->panels['vote'] = '';

			if ($this->settings['admin_client'] != '' &&
			    preg_match('/^2\.11\.[12][0-9]$/', $this->settings['admin_client']) != 1 ||
			    $this->settings['admin_client'] == '2.11.10')
				trigger_error('Invalid admin client version : ' . $this->settings['admin_client'] . ' !', E_USER_ERROR);
			if ($this->settings['player_client'] != '' &&
			    preg_match('/^2\.11\.[12][0-9]$/', $this->settings['player_client']) != 1 ||
			    $this->settings['player_client'] == '2.11.10')
				trigger_error('Invalid player client version: ' . $this->settings['player_client'] . ' !', E_USER_ERROR);
		} else {
			// could not parse XML file
			trigger_error('Could not read/parse config file ' . $config_file . ' !', E_USER_ERROR);
		}
	}  // loadSettings


	/**
	 * Read Admin/Operator/Ability lists and apply them on the current instance.
	 */
	function readLists() {

		// get lists file name
		$adminops_file = $this->settings['adminops_file'];

		if ($lists = $this->xml_parser->parseXml($adminops_file, true, true)) {
			// read the XML structure into arrays
			$this->titles = $lists['LISTS']['TITLES'][0];

			if (is_array($lists['LISTS']['ADMINS'][0])) {
				$this->admin_list = $lists['LISTS']['ADMINS'][0];
				// check admin list consistency
				if (empty($this->admin_list['IPADDRESS'])) {
					// fill <ipaddress> list to same length as <MPLOGIN> list
					if (($cnt = count($this->admin_list['MPLOGIN'])) > 0)
						$this->admin_list['IPADDRESS'] = array_fill(0, $cnt, '');
				} else {
					if (count($this->admin_list['MPLOGIN']) != count($this->admin_list['IPADDRESS']))
						trigger_error("Admin mismatch between <MPLOGIN>'s and <ipaddress>'s!", E_USER_WARNING);
				}
			}

			if (is_array($lists['LISTS']['OPERATORS'][0])) {
				$this->operator_list = $lists['LISTS']['OPERATORS'][0];
				// check operator list consistency
				if (empty($this->operator_list['IPADDRESS'])) {
					// fill <ipaddress> list to same length as <MPLOGIN> list
					if (($cnt = count($this->operator_list['MPLOGIN'])) > 0)
						$this->operator_list['IPADDRESS'] = array_fill(0, $cnt, '');
				} else {
					if (count($this->operator_list['MPLOGIN']) != count($this->operator_list['IPADDRESS']))
						trigger_error("Operators mismatch between <MPLOGIN>'s and <ipaddress>'s!", E_USER_WARNING);
				}
			}

			$this->adm_abilities = $lists['LISTS']['ADMIN_ABILITIES'][0];
			$this->op_abilities = $lists['LISTS']['OPERATOR_ABILITIES'][0];

			// convert strings to booleans
			foreach ($this->adm_abilities as $ability => $value) {
				if (strtoupper($value[0]) == 'TRUE') {
					$this->adm_abilities[$ability][0] = true;
				} else {
					$this->adm_abilities[$ability][0] = false;
				}
			}
			foreach ($this->op_abilities as $ability => $value) {
				if (strtoupper($value[0]) == 'TRUE') {
					$this->op_abilities[$ability][0] = true;
				} else {
					$this->op_abilities[$ability][0] = false;
				}
			}
			return true;
		} else {
			// could not parse XML file
			trigger_error('Could not read/parse adminops file ' . $adminops_file . ' !', E_USER_WARNING);
			return false;
		}
	}  // readLists

	/**
	 * Write Admin/Operator/Ability lists to save them for future runs.
	 */
	function writeLists() {

		// get lists file name
		$adminops_file = $this->settings['adminops_file'];

		// compile lists file contents
		$lists = "<?xml version=\"1.0\" encoding=\"utf-8\" ?>" . CRLF
		       . "<lists>" . CRLF
		       . "\t<titles>" . CRLF;
		foreach ($this->titles as $title => $value) {
			$lists .= "\t\t<" . strtolower($title) . ">" .
			          $value[0]
			           . "</" . strtolower($title) . ">" . CRLF;
		}
		$lists .= "\t</titles>" . CRLF
		        . CRLF
		        . "\t<admins>" . CRLF;
		$empty = true;
		if (isset($this->admin_list['MPLOGIN'])) {
			for ($i = 0; $i < count($this->admin_list['MPLOGIN']); $i++) {
				if ($this->admin_list['MPLOGIN'][$i] != '') {
					$lists .= "\t\t<mplogin>" . $this->admin_list['MPLOGIN'][$i] . "</mplogin>"
					         . " <ipaddress>" . $this->admin_list['IPADDRESS'][$i] . "</ipaddress>" . CRLF;
					$empty = false;
				}
			}
		}
		if ($empty) {
			$lists .= "<!-- format:" . CRLF
			        . "\t\t<mplogin>YOUR_ADMIN_LOGIN</mplogin> <ipaddress></ipaddress>" . CRLF
			        . "-->" . CRLF;
		}
		$lists .= "\t</admins>" . CRLF
		        . CRLF
		        . "\t<operators>" . CRLF;
		$empty = true;
		if (isset($this->operator_list['MPLOGIN'])) {
			for ($i = 0; $i < count($this->operator_list['MPLOGIN']); $i++) {
				if ($this->operator_list['MPLOGIN'][$i] != '') {
					$lists .= "\t\t<mplogin>" . $this->operator_list['MPLOGIN'][$i] . "</mplogin>"
					         . " <ipaddress>" . $this->operator_list['IPADDRESS'][$i] . "</ipaddress>" . CRLF;
					$empty = false;
				}
			}
		}
		if ($empty) {
			$lists .= "<!-- format:" . CRLF
			        . "\t\t<mplogin>YOUR_OPERATOR_LOGIN</mplogin> <ipaddress></ipaddress>" . CRLF
			        . "-->" . CRLF;
		}
		$lists .= "\t</operators>" . CRLF
		        . CRLF
		        . "\t<admin_abilities>" . CRLF;
		foreach ($this->adm_abilities as $ability => $value) {
			$lists .= "\t\t<" . strtolower($ability) . ">" .
			          ($value[0] ? "true" : "false")
			           . "</" . strtolower($ability) . ">" . CRLF;
		}
		$lists .= "\t</admin_abilities>" . CRLF
		        . CRLF
		        . "\t<operator_abilities>" . CRLF;
		foreach ($this->op_abilities as $ability => $value) {
			$lists .= "\t\t<" . strtolower($ability) . ">" .
			          ($value[0] ? "true" : "false")
			           . "</" . strtolower($ability) . ">" . CRLF;
		}
		$lists .= "\t</operator_abilities>" . CRLF
		        . "</lists>" . CRLF;

		// write out the lists file
		if (!@file_put_contents($adminops_file, $lists)) {
			trigger_error('Could not write adminops file ' . $adminops_file . ' !', E_USER_WARNING);
			return false;
		} else {
			return true;
		}
	}  // writeLists

  
	/**
	 * Loads files in the plugins directory.
	 */
	function loadPlugins() {

		// load and parse the plugins file
		if ($plugins = $this->xml_parser->parseXml('plugins.xml')) {
			if (!empty($plugins['MPASECO_PLUGINS']['PLUGIN'])) {
				// take each plugin tag
				foreach ($plugins['MPASECO_PLUGINS']['PLUGIN'] as $plugin) {
					// log plugin message
					$this->console_text('[MPAseco] Load plugin [' . $plugin . ']');
					// include the plugin
					require_once('plugins/' . $plugin);
					$this->plugins[] = $plugin;
				}
			}
		} else {
			trigger_error('Could not read/parse plugins list plugins.xml !', E_USER_ERROR);
		}
	}  // loadPlugins


	/**
	 * Runs the server.
	 */
	function run($config_file) {

		// load new settings, if available
		$this->console_text('[MPAseco] Load settings [{1}]', $config_file);
		$this->loadSettings($config_file);

		// load admin/operator/ability lists, if available
		$this->console_text('[MPAseco] Load admin/ops lists [{1}]', $this->settings['adminops_file']);
		$this->readLists();

		// load plugins and register chat commands
		$this->console_text('[MPAseco] Load plugins list [plugins.xml]');
		$this->loadPlugins();

		// connect to Trackmania Dedicated Server
		if (!$this->connect()) {
			// kill program with an error
			trigger_error('Connection could not be established !', E_USER_ERROR);
		}

		// log status message
		$this->console('Connection established successfully !');
		// log admin lock message
		if ($this->settings['lock_password'] != '')
			$this->console_text("[MPAseco] Locked admin commands & features with password '{1}'", $this->settings['lock_password']);

		// get basic server info
		$this->client->query('GetVersion');
		$response['version'] = $this->client->getResponse();
		$this->server->game = $response['version']['Name'];
		$this->server->version = $response['version']['Version'];
		$this->server->build = $response['version']['Build'];

		// throw 'starting up' event
		$this->releaseEvent('onStartup', null);

		// synchronize information with server
		$this->serverSync();

		// make a visual header
		$this->sendHeader();

		// get current game infos if server loaded a map yet
		if ($this->currstatus == 100) {
			$this->console_text('[MPAseco] Waiting for the server to start a map');
		} else {
			$this->beginMap(false);
		}

		// main loop
		$this->startup_phase = false;
		while (true) {
			$starttime = microtime(true);
			// get callbacks from the server
			$this->executeCallbacks();

			// sends calls to the server
			$this->executeCalls();

			// throw timing events
			$this->releaseEvent('onMainLoop', null);

			$this->currsecond = time();
			if ($this->prevsecond != $this->currsecond) {
				$this->prevsecond = $this->currsecond;
				$this->releaseEvent('onEverySecond', null);
			}

			// reduce CPU usage if main loop has time left
			$endtime = microtime(true);
			$delay = 200000 - ($endtime - $starttime) * 1000000;
			if ($delay > 0)
				usleep($delay);
			// make sure the script does not timeout
			@set_time_limit($this->settings['script_timeout']);
		}

		// close the client connection
		$this->client->Terminate();
	}  // run


	/**
	 * Authenticates MPAseco at the server.
	 */
	function connect() {

		// only if logins are set
		if ($this->server->ip && $this->server->port && $this->server->login && $this->server->pass) {
			// log console message
			$this->console('Try to connect to MP dedicated server on {1}:{2} timeout {3}s',
			               $this->server->ip, $this->server->port,
			               ($this->server->timeout !== null ? $this->server->timeout : 0));

			// connect to the server
			if (!$this->client->InitWithIp($this->server->ip, $this->server->port, $this->server->timeout)) {
				trigger_error('[' . $this->client->getErrorCode() . '] InitWithIp - ' . $this->client->getErrorMessage(), E_USER_WARNING);
				return false;
			}

			// log console message
			$this->console("Try to authenticate with login '{1}' and password '{2}'",
			               $this->server->login, $this->server->pass);

			// check login
			if ($this->server->login != 'SuperAdmin') {
				trigger_error("Invalid login '" . $this->server->login . "' - must be 'SuperAdmin' in config.xml !", E_USER_WARNING);
				return false;
			}
			// check password
			if ($this->server->pass == 'SuperAdmin') {
				trigger_error("Insecure password '" . $this->server->pass . "' - should be changed in dedicated config and config.xml !", E_USER_WARNING);
			}

			// log into the server
			if (!$this->client->query('Authenticate', $this->server->login, $this->server->pass)) {
				trigger_error('[' . $this->client->getErrorCode() . '] Authenticate - ' . $this->client->getErrorMessage(), E_USER_WARNING);
				return false;
			}

			// enable callback system
			$this->client->query('EnableCallbacks', true);

			// wait for server to be ready
			$this->waitServerReady();

      $this->client->query('SetApiVersion', API_VERSION);
      
			// connection established
			return true;
		} else {
			// connection failed
			return false;
		}
	}  // connect


	/**
	 * Waits for the server to be ready (status 4, 'Running - Play')
	 */
	function waitServerReady() {

		$this->client->query('GetStatus');
		$status = $this->client->getResponse();
		if ($status['Code'] != 4) {
			$this->console("Waiting for dedicated server to reach status 'Running - Play'...");
			$this->console('Status: ' . $status['Name']);
			$timeout = 0;
			$laststatus = $status['Name'];
			while ($status['Code'] != 4) {
				sleep(1);
				$this->client->query('GetStatus');
				$status = $this->client->getResponse();
				if ($laststatus != $status['Name']) {
					$this->console('Status: ' . $status['Name']);
					$laststatus = $status['Name'];
				}
				if (isset($this->server->timeout) && $timeout++ > $this->server->timeout)
					trigger_error('Timed out while waiting for dedicated server!', E_USER_ERROR);
			}
		}
	}  // waitServerReady

	/**
	 * Initializes the server and the player list.
	 * Reads a list of the players who are on the server already,
	 * and loads all server variables.
	 */
	function serverSync() {

		// check server build
		if (strlen($this->server->build) == 0 ||
		    ($this->server->getGame() == 'MP' && strcmp($this->server->build, MP_BUILD) < 0)) {
			trigger_error("Obsolete server build '" . $this->server->build . "' - must be at least '" . MP_BUILD . "' !", E_USER_ERROR);
		}

		// get server id, login, nickname, zone & packmask
		$this->server->isrelay = false;
		$this->server->relaymaster = null;
		$this->server->relayslist = array();
		$this->server->packmask = ' ';
		$this->client->query('GetSystemInfo');
		$response['system'] = $this->client->getResponse();
		$this->server->serverlogin = $response['system']['ServerLogin'];

		$this->client->query('GetDetailedPlayerInfo', $this->server->serverlogin);
		$response['info'] = $this->client->getResponse();
		$this->server->id = $response['info']['PlayerId'];
		$this->server->nickname = $response['info']['NickName'];
		$this->server->zone = substr($response['info']['Path'], 6);  // strip 'World|'

		$this->client->query('GetLadderServerLimits');
		$response['ladder'] = $this->client->getResponse();
		$this->server->laddermin = $response['ladder']['LadderServerLimitMin'];
		$this->server->laddermax = $response['ladder']['LadderServerLimitMax'];

		$this->client->query('IsRelayServer');
		$this->server->isrelay = ($this->client->getResponse() > 0);
		if ($this->server->isrelay) {
			$this->client->query('GetMainServerPlayerInfo', 1);
			$this->server->relaymaster = $this->client->getResponse();
		}

		// get MP packmask
//		$this->client->query('GetServerPackMask');
//		$this->server->packmask = $this->client->getResponse();
 
    //Temporary fix
    $this->client->query('GetVersion');   
    $titleid=$this->client->getResponse();  
    $this->server->packmask=$titleid['TitleId'];
     
		// clear possible leftover ManiaLinks
		$this->client->query('SendHideManialinkPage');

		// get mode & limits
		$this->client->query('GetCurrentGameInfo', 1);
		$response['gameinfo'] = $this->client->getResponse();
		$this->server->gameinfo = new Gameinfo($response['gameinfo']);

		// get status
		$this->client->query('GetStatus');
		$response['status'] = $this->client->getResponse();
		$this->currstatus = $response['status']['Code'];

		// get game & mapdir
		$this->client->query('GameDataDirectory');
		$this->server->gamedir = $this->client->getResponse();
		$this->client->query('GetMapsDirectory');
		$this->server->mapdir = $this->client->getResponse();

		// get server name & options
		$this->getServerOptions();

		// throw 'synchronisation' event
		$this->releaseEvent('onSync', null);

		// get current players/servers on the server (hardlimited to 300)
		$this->client->query('GetPlayerList', 300, 0, 2);
		$response['playerlist'] = $this->client->getResponse();

		// update players/relays lists
		if (!empty($response['playerlist'])) {
			foreach ($response['playerlist'] as $player) {
				// fake it into thinking it's a connecting player:
				// it gets team & ladder info this way & will also throw an
				// onPlayerConnect event for players (not relays) to all plugins
				$this->playerConnect(array($player['Login'], ''));
			}
		}
	}  // serverSync


	/**
	 * Sends program header to console and ingame chat.
	 */
	function sendHeader() {

    $this->client->query('GetVersion');   
    $titleid=$this->client->getResponse();
  
		$this->console_text('###############################################################################');
		$this->console_text('  MPAseco v' . MPASECO_VERSION . ' running on {1}:{2}', $this->server->ip, $this->server->port);
		$this->console_text('  Name   : {1} - {2}', stripColors($this->server->name, false), $this->server->serverlogin);
		if ($this->server->isrelay)
			$this->console_text('  Relays : {1} - {2}', stripColors($this->server->relaymaster['NickName'], false), $this->server->relaymaster['Login']);
		$this->console_text('  Game   : {1} - {2} - {3}', $this->server->game,
		                       $titleid['TitleId'], $this->server->gameinfo->getMode());
		$this->console_text('  Version: {1} / {2}', $this->server->version, $this->server->build);
		$this->console_text('  Author : Lukas Kremsmayr alias kremsy');
		$this->console_text('  Previous Authors: Xymph & Flo');		
		$this->console_text('###############################################################################');

		// format the text of the message
		$startup_msg = formatText($this->getChatMessage('STARTUP'),
		                          MPASECO_VERSION,
		                          $this->server->ip, $this->server->port);
		// show startup message
		$this->client->query('ChatSendServerMessage', $this->formatColors($startup_msg));
	}  // sendHeader


	/**
	 * Gets callbacks from the TM Dedicated Server and reacts on them.
	 */
	function executeCallbacks() {

		// receive callbacks with a timeout (default: 2 ms)
		$this->client->resetError();
		$this->client->readCB();

		// now get the responses out of the 'buffer'
		$calls = $this->client->getCBResponses();
		if ($this->client->isError()) {
			trigger_error('ExecCallbacks XMLRPC Error [' . $this->client->getErrorCode() . '] - ' . $this->client->getErrorMessage(), E_USER_ERROR);
		}
		         // Not used up to now:
             //ManiaPlanet.ServerStart(); 
             //ManiaPlanet.ServerStop(); 
             //ManiaPlanet.BeginMatch(SMapInfo Map); 
             //ManiaPlanet.EndMatch(SPlayerRanking Rankings[], SMapInfo Map); 
		if (!empty($calls)) {
			while ($call = array_shift($calls)) {
				switch ($call[0]) {   
    			case 'ManiaPlanet.PlayerConnect':  // [0]=Login, [1]=IsSpectator      new
						$this->playerConnect($call[1]);
						break;
						
					case 'ManiaPlanet.PlayerDisconnect':  // [0]=Login                    new
						$this->playerDisconnect($call[1]);
						break;

					case 'ManiaPlanet.PlayerChat':  // [0]=PlayerUid, [1]=Login, [2]=Text, [3]=IsRegistredCmd   new
						$this->playerChat($call[1]);
						$this->releaseEvent('onChat', $call[1]);
						break;

					case 'ManiaPlanet.BeginRound':  // none     new
						$this->beginRound();
						break;

					case 'ManiaPlanet.EndRound':  // none       new
						$this->endRound();
						break;

					case 'ManiaPlanet.StatusChanged':  // [0]=StatusCode, [1]=StatusName
						// update status changes
						$this->prevstatus = $this->currstatus;
						$this->currstatus = $call[1][0];
						// check WarmUp state
						if ($this->currstatus == 3 || $this->currstatus == 5) {
							$this->client->query('GetWarmUp');
							$this->warmup_phase = $this->client->getResponse();
						}
						if ($this->currstatus == 4) {  // Running - Play
							$this->runningPlay();
						}
						$this->releaseEvent('onStatusChangeTo' . $this->currstatus, $call[1]);
						break;

					case 'ManiaPlanet.BeginMap':  // [0]=Challenge
  					$this->beginMap($call[1]);
						break;

					case 'ManiaPlanet.EndMap':  // [0]=Challenge
					  if($this->endmapvar==0)
              $this->endMap($call[1]);
						break;

					case 'ManiaPlanet.PlayerManialinkPageAnswer':  // [0]=PlayerUid, [1]=Login, [2]=Answer, [3]=Entries
						$this->releaseEvent('onPlayerManialinkPageAnswer', $call[1]);
						break;

					case 'ManiaPlanet.BillUpdated':  // [0]=BillId, [1]=State, [2]=StateName, [3]=TransactionId
						$this->releaseEvent('onBillUpdated', $call[1]);
						break;

					case 'ManiaPlanet.MapListModified':  // [0]=CurChallengeIndex, [1]=NextChallengeIndex, [2]=IsListModified
						$this->releaseEvent('onMapListModified', $call[1]);
						break;

					case 'ManiaPlanet.PlayerInfoChanged':  // [0]=PlayerInfo
						$this->playerInfoChanged($call[1][0]);
						break;

					case 'ManiaPlanet.TunnelDataReceived':  // [0]=PlayerUid, [1]=Login, [2]=Data
						$this->releaseEvent('onTunnelDataReceived', $call[1]);
						break;

					case 'ManiaPlanet.Echo':  // [0]=Internal, [1]=Public
						$this->releaseEvent('onEcho', $call[1]);
						break;

					case 'ManiaPlanet.ManualFlowControlTransition':  // [0]=Transition
						$this->releaseEvent('onManualFlowControlTransition', $call[1]);
						break;

					case 'ManiaPlanet.VoteUpdated':  // [0]=StateName, [1]=Login, [2]=CmdName, [3]=CmdParam
						$this->releaseEvent('onVoteUpdated', $call[1]);
						break;

					// new MP callbacks:

					case 'ManiaPlanet.ModeScriptCallback':  // [0]=Param1, [1]=Param2
						$this->releaseEvent('onModeScriptCallback', $call[1]);
						break;

					default:
						// do nothing
				}
			}
			return $calls;
		} else {
			return false;
		}
	}  // executeCallbacks


	/**
	 * Adds calls to a multiquery.
	 * It's possible to set a callback function which
	 * will be executed on incoming response.
	 * You can also set an ID to read response later on.
	 */
	function addCall($call, $params = array(), $id = 0, $callback_func = false) {

		// adds call and registers a callback if needed
		$index = $this->client->addCall($call, $params);
		$rpc_call = new RPCCall($id, $index, $callback_func, array($call, $params));
		$this->rpc_calls[] = $rpc_call;
	}  // addCall


	/**
	 * Executes a multicall and gets responses.
	 * Saves responses in array with IDs as keys.
	 */
	function executeCalls() {

		// clear responses
		$this->rpc_responses = array();

		// stop if there are no rpc calls in query
		if (empty($this->client->calls)) {
			return true;
		}

		$this->client->resetError();
		$tmpcalls = $this->client->calls;  // debugging code to find UTF-8 errors
		// sends multiquery to the server and gets the response
		if ($this->client->multiquery()) {
			if ($this->client->isError()) {
				$this->console_text(print_r($tmpcalls, true));
				trigger_error('ExecCalls XMLRPC Error [' . $this->client->getErrorCode() . '] - ' . $this->client->getErrorMessage(), E_USER_ERROR);
			}

			// get new response from server
			$responses = $this->client->getResponse();

			// handle server responses
			foreach ($this->rpc_calls as $call) {
				// display error message if needed
				$err = false;
				if (isset($responses[$call->index]['faultString'])) {
					$this->rpcErrorResponse($responses[$call->index]);
					print_r($call->call);
					$err = true;
				}

				// if an id was set, then save the response under the specified id
				if ($call->id) {
					$this->rpc_responses[$call->id] = $responses[$call->index][0];
				}

				// if a callback function has been set, then execute it
				if ($call->callback && !$err) {
					if (function_exists($call->callback)) {
						// callback the function with the response as parameter
						call_user_func($call->callback, $responses[$call->index][0]);
					}

					// if a function with the name of the callback wasn't found, then
					// try to execute a method with its name
					elseif (method_exists($this, $call->callback)) {
						// callback the method with the response as parameter
						call_user_func(array($this, $call->callback), $responses[$call->index][0]);
					}
				}
			}
		}

		// clear calls
		$this->rpc_calls = array();
	}  // executeCalls


	/**
	 * Documents RPC Errors.
	 */
	function rpcErrorResponse($response) {

		$this->console_text('[RPC Error ' . $response['faultCode'] . '] ' . $response['faultString']);
	}  // rpcErrorResponse


	/**
	 * Registers functions which are called on specific events.
	 */
	function registerEvent($event_type, $callback_func) {

		// registers a new event
		$this->events[$event_type][] = $callback_func;
	}  // registerEvent

	/**
	 * Executes the functions which were registered for specified events.
	 */
	function releaseEvent($event_type, $func_param) {

		// executes registered event functions
		// if there are any events for that type
		if (!empty($this->events[$event_type])) {
			// for each registered function of this type
			foreach ($this->events[$event_type] as $func_name) {
				// if function for the specified player connect event can be found
				if (is_callable($func_name)) {
					// ... execute it!
					call_user_func($func_name, $this, $func_param);
				}
			}
		}
	}  // releaseEvent


	/**
	 * Stores a new user command.
	 */
	function addChatCommand($command_name, $command_help, $command_is_admin = false) {

		$chat_command = new ChatCommand($command_name, $command_help, $command_is_admin);
		$this->chat_commands[] = $chat_command;
	}  // addChatCommand

	/**
	 * When a round is started, signal the event.
	 */
	function beginRound() {

		$this->console_text('Begin Round');
		$this->releaseEvent('onBeginRound', null);
	}  // beginRound

	/**
	 * When a round is ended, signal the event.
	 */
	function endRound() {

		$this->console_text('End Round');
		$this->releaseEvent('onEndRound', null);
	}  // endRound


	/**
	 * When a player's info changed, signal the event.  Fields:
	 * Login, NickName, PlayerId, TeamId, SpectatorStatus, LadderRanking, Flags
	 */
	function playerInfoChanged($playerinfo) {

		// on relay, check for player from master server
		if ($this->server->isrelay && floor($playerinfo['Flags'] / 10000) % 10 != 0)
			return;

		// check for valid player
		if (!$player = $this->server->players->getPlayer($playerinfo['Login']))
			return;

		// check ladder ranking
		if ($playerinfo['LadderRanking'] > 0) {
			$player->ladderrank = $playerinfo['LadderRanking'];
			$player->isofficial = true;
		} else {
			$player->isofficial = false;
		}

		// check spectator status (ignoring temporary changes)
		$player->prevstatus = $player->isspectator;
		if (($playerinfo['SpectatorStatus'] % 10) != 0)
			$player->isspectator = true;
		else
			$player->isspectator = false;

		$this->releaseEvent('onPlayerInfoChanged', $playerinfo);
	}  // playerInfoChanged


	/**
	 * When a new map is started we have to get information
	 * about the new map and so on.
	 */
	function runningPlay() {
		// request information about the new map
		// ... and callback to function newMap()
	}  // runningPlay


	/**
	 * When a new map is started we have to get information
	 * about the new map and so on.
	 */
	function beginMap($race) {
		// request information about the new map
		// ... and callback to function newMap()
	    $this->endmapvar=0;

  	// if new map, check WarmUp state
  /*
		if ($race)
			$this->warmup_phase = $race[1];
      */
		if (!$race) {
			$this->addCall('GetCurrentMapInfo', array(), '', 'newMap');
		} else {
			$this->newMap($race[0]);
		}
	}  // beginMap


	/**
	 * Reacts on new maps.
	 * Gets record to current map etc.
	 */
	function newMap($map) {

		// log if not a restart
		if ($this->restarting == 0)
			$this->console_text('Begin Map');

		// refresh game info
		$this->client->query('GetCurrentGameInfo', 1);
		$gameinfo = $this->client->getResponse();
		$this->server->gameinfo = new Gameinfo($gameinfo);

		// check for restarting map
		$this->changingmode = false;
		if ($this->restarting > 0) {
			// check type of restart and signal an instant one
			if ($this->restarting == 2) {
				$this->restarting = 0;
			} else {  // == 1
				$this->restarting = 0;
				// throw postfix 'restart map' event
				$this->releaseEvent('onRestartMap2', $map);
				return;
			}
		}
		// refresh server name & options
		$this->getServerOptions();

		// reset record list
		$this->server->records->clear();
		// reset player votings
		//$this->server->players->resetVotings();

		// create new map object
		$map_item = new Map($map);

		// in Rounds/Team/Cup mode if multilap map, get forced laps
		if ($map_item->laprace &&
		    ($this->server->gameinfo->mode == Gameinfo::RNDS ||
		     $this->server->gameinfo->mode == Gameinfo::TEAM ||
		     $this->server->gameinfo->mode == Gameinfo::CUP)) {
			$map_item->forcedlaps = $this->server->gameinfo->forcedlaps;
		}

		// obtain map's GBX data, MX info & records
		$map_item->gbx = new GBXChallengeFetcher($this->server->mapdir . $map_item->filename, true);
		// check for XML parser error
		if (is_string($map_item->gbx->parsedxml))
			trigger_error($map_item->gbx->parsedxml, E_USER_WARNING);
		$map_item->mx = findMXdata($map_item->uid, true);
    
    // titleuid (is not in the GetMapInfos method..)
    $map_item->titleuid = $map_item->gbx->titleuid;
    
		// throw main 'begin map' event
		$this->releaseEvent('onBeginMap', $map_item);

		// log console message
		$this->console('map changed [{1}] >> [{2}]',
		               stripColors($this->server->map->name, false),
		               stripColors($map_item->name, false));


				// replace parameters
				$message = formatText($this->getChatMessage('MAP_BEGIN'),
				                      stripColors($map_item->name));

    $this->client->query('ChatSendServerMessage', $this->formatColors($message));
    
		// update the field which contains current map
		$this->server->map = $map_item;

		// throw postfix 'begin map' event (various)
		$this->releaseEvent('onBeginMap2', $map_item);
	}  // newMap


	/**
	 * End of current map.
	 * Write records to database and/or display final statistics.
	 */
	function endMap($race) {
  		// check for RestartChallenge flag
  	//	$this->console(print_r($race));
  //		$this->console($race[4]." Race4");
     /*
  		if ($race[4]) {
  			$this->restarting = 1;
  			// check whether changing game mode or any player has a time/score,
  			// then there will be ChatTime, otherwise it's an instant restart
  			if ($this->changingmode)
  				$this->restarting = 2;
  			else
  				foreach ($race[0] as $pl) {
  					if ($pl['BestTime'] > 0 || $pl['Score'] > 0) {
  						$this->restarting = 2;
  						break;
  					}
  				}
  			// log type of restart and signal an instant one
  			if ($this->restarting == 2) {
  				$this->console_text('Restart Map (with ChatTime)');
  			} else {  // == 1
  				$this->console_text('Restart Map (instant)');
  				// throw main 'restart map' event
  				$this->releaseEvent('onRestartMap', $race);
  				return;
  			}
  		}      */
  		// log if not a restart
  		if ($this->restarting == 0)
  			$this->console_text('End Map');
  
  		// get rankings and call endMapRanking as soon as we have them
  		// $this->addCall('GetCurrentRanking', array(2, 0), false, 'endMapRanking');
  /*		if (!$this->server->isrelay)
  			$this->endMapRanking($race[0]);*/  
        
        //$race[0] is not ranking anymore but challengeinfo
  
        $race[1]=$race[0];   //to make it compatible with other Plugins
  
  		// throw prefix 'end map' event (chat-based votes)
  		$this->releaseEvent('onEndMap1', $race);
  		// throw main 'end map' event
  		$this->releaseEvent('onEndMap', $race);
   
     
      $this->smrankings = array(); // XXX: Temporary rankings
	}  // endMap


	/**
	 * Check out who won the current map and increment his/her wins by one.
	 */
	function endMapRanking($ranking) {

		// check for online login
		if (isset($ranking[0]['Login']) &&
		    ($player = $this->server->players->getPlayer($ranking[0]['Login'])) !== false) {
			// check for winner if there's more than one player
			if ($ranking[0]['Rank'] == 1 && count($ranking) > 1 &&
			    ($this->server->gameinfo->mode == Gameinfo::STNT ?
			     ($ranking[0]['Score'] > 0) : ($ranking[0]['BestTime'] > 0))) {
				// increase the player's wins
				$player->newwins++;

				// log console message
				$this->console('{1} won for the {2}. time!',
				               $player->login, $player->getWins());

				if ($player->getWins() % $this->settings['global_win_multiple'] == 0) {
					// replace parameters
					$message = formatText($this->getChatMessage('WIN_MULTI'),
					                      stripColors($player->nickname), $player->getWins());

					// show chat message
					$this->client->query('ChatSendServerMessage', $this->formatColors($message));
				} else {
					// replace parameters
					$message = formatText($this->getChatMessage('WIN_NEW'),
					                      $player->getWins());

					// show chat message
					$this->client->query('ChatSendServerMessageToLogin', $this->formatColors($message), $player->login);
				}

				// throw 'player wins' event
				$this->releaseEvent('onPlayerWins', $player);
			}
		}
	}  // endMapRanking


	/**
	 * Handles connections of new players.
	 */
	function playerConnect($player) {

		// request information about the new player
		// (removed callback mechanism here, as GetPlayerInfo occasionally
		//  returns no data and then the connecting login would be lost)
		$login = $player[0];
		$this->client->query('GetDetailedPlayerInfo', $login);
		$playerd = $this->client->getResponse();
		$this->client->query('GetPlayerInfo', $login, 1);
		$player = $this->client->getResponse();

		// check for server
		if (isset($player['Flags']) && floor($player['Flags'] / 100000) % 10 != 0) {
			// register relay server
			if (!$this->server->isrelay && $player['Login'] != $this->server->serverlogin) {
				$this->server->relayslist[$player['Login']] = $player;

				// log console message
				$this->console('<<< relay server {1} ({2}) connected', $player['Login'],
				               stripColors($player['NickName'], false));
			}

		// on relay, check for player from master server
		} elseif ($this->server->isrelay && floor($player['Flags'] / 10000) % 10 != 0) {
			; // ignore
		} else {
			$ipaddr = isset($playerd['IPAddress']) ? preg_replace('/:\d+/', '', $playerd['IPAddress']) : '';  // strip port

			// if no data fetched, notify & kick the player
			if (!isset($player['Login']) || $player['Login'] == '') {
				$message = str_replace('{br}', LF, $this->getChatMessage('CONNECT_ERROR'));
				$message = $this->formatColors($message);
				$this->client->query('ChatSendServerMessageToLogin', str_replace(LF.LF, LF, $message), $login);
				sleep(5);  // allow time to connect and see the notice
				$this->client->addCall('Kick', array($login, $this->formatColors($this->getChatMessage('CONNECT_DIALOG'))));
				// log console message
				$this->console('GetPlayerInfo failed for ' . $login . ' -- notified & kicked');
				return;

			// if player IP in ban list, notify & kick the player
			} elseif (!empty($this->bannedips) && in_array($ipaddr, $this->bannedips)) {
				$message = str_replace('{br}', LF, $this->getChatMessage('BANIP_ERROR'));
				$message = $this->formatColors($message);
				$this->client->query('ChatSendServerMessageToLogin', str_replace(LF.LF, LF, $message), $login);
				sleep(5);  // allow time to connect and see the notice
				$this->client->addCall('Ban', array($login, $this->formatColors($this->getChatMessage('BANIP_DIALOG'))));
				// log console message
				$this->console('Player ' . $login . ' banned from ' . $ipaddr . ' -- notified & kicked');
				return;

			// client version checking
			} else {
				// extract version number
				$version = str_replace(')', '', preg_replace('/.*\(/', '', $playerd['ClientVersion']));
				if ($version == '') $version = '2.11.11';
				$message = str_replace('{br}', LF, $this->getChatMessage('CLIENT_ERROR'));

				// if invalid version, notify & kick the player
				if ($this->settings['player_client'] != '' &&
				    strcmp($version, $this->settings['player_client']) < 0) {
					$this->client->query('ChatSendServerMessageToLogin', $this->formatColors($message), $login);
					sleep(5);  // allow time to connect and see the notice
					$this->client->addCall('Kick', array($login, $this->formatColors($this->getChatMessage('CLIENT_DIALOG'))));
					// log console message
					$this->console('Obsolete player client version ' . $version . ' for ' . $login . ' -- notified & kicked');
					return;
				}

				// if invalid version, notify & kick the admin
				if ($this->settings['admin_client'] != '' && $this->isAnyAdminL($player['Login']) &&
				    strcmp($version, $this->settings['admin_client']) < 0) {
					$this->client->query('ChatSendServerMessageToLogin', $this->formatColors($message), $login);
					sleep(5);  // allow time to connect and see the notice
					$this->client->addCall('Kick', array($login, $this->formatColors($this->getChatMessage('CLIENT_DIALOG'))));
					// log console message
					$this->console('Obsolete admin client version ' . $version . ' for ' . $login . ' -- notified & kicked');
					return;
				}
			}

			// create player object
			$player_item = new Player($playerd);
			// set default window style, panels & background
			$player_item->style = $this->style;
			$player_item->panels['admin'] = set_panel_bg($this->panels['admin'], $this->panelbg);
			$player_item->panels['donate'] = set_panel_bg($this->panels['donate'], $this->panelbg);
		//	$player_item->panels['records'] = set_panel_bg($this->panels['records'], $this->panelbg);
			$player_item->panels['vote'] = set_panel_bg($this->panels['vote'], $this->panelbg);
			$player_item->panelbg = $this->panelbg;

			// adds a new player to the internal player list
			$this->server->players->addPlayer($player_item);

			// log console message
			$this->console('<< player {1} joined the game [{2} : {3} : {4} : {5} : {6}]',
			               $player_item->pid,
			               $player_item->login,
			               $player_item->nickname,
			               $player_item->nation,
			               $player_item->ladderrank,
			               $player_item->ip);

			// replace parameters
			$message = formatText($this->getChatMessage('WELCOME'),
			                      stripColors($player_item->nickname),
			                      $this->server->name, MPASECO_VERSION);
			// hyperlink package name & version number
			$message = preg_replace('/MPAseco.+' . MPASECO_VERSION . '/', '$l[' . XASECO_ORG . ']$0$l', $message);

			// send welcome popup or chat message
			if ($this->settings['welcome_msg_window']) {
				$message = str_replace('{#highlite}', '{#message}', $message);
				$message = preg_split('/{br}/', $this->formatColors($message));
				// repack all lines
				foreach ($message as &$line)
					$line = array($line);
				display_manialink($player_item->login, '',
				                  array('Icons64x64_1', 'Inbox'), $message,
				                  array(1.2), 'OK');
			} else {
				$message = str_replace('{br}', LF, $this->formatColors($message));
				$this->client->query('ChatSendServerMessageToLogin', str_replace(LF.LF, LF, $message), $player_item->login);
			}

			// if there's a record on current map
		/*	$cur_record = $this->server->records->getRecord(0);
			if ($cur_record !== false && $cur_record->score > 0) {
				// set message to the current record
				$message = formatText($this->getChatMessage('RECORD_CURRENT'),
				                      stripColors($this->server->map->name),
				                      ($this->server->gameinfo->mode == Gameinfo::STNT ?
				                       $cur_record->score : formatTime($cur_record->score)),
				                      stripColors($cur_record->player->nickname));
			} else {  // if there should be no record to display
				// display a no-record message
				$message = formatText($this->getChatMessage('MAP_BEGIN'),
				                      stripColors($this->server->map->name));
			}          

			// show top-8 & records of all online players before map
			if (($this->settings['show_recs_before'] & 2) == 2 && function_exists('show_maprecs')) {
				show_maprecs($this, $player_item->login, 1, 0);  // from chat.records2.php
			} elseif (($this->settings['show_recs_before'] & 1) == 1) {
				// or show original record message
				$this->client->query('ChatSendServerMessageToLogin', $this->formatColors($message), $player_item->login);
			}           */

			// throw main 'player connects' event
			$this->releaseEvent('onPlayerConnect', $player_item);
			// throw postfix 'player connects' event (access control)
			$this->releaseEvent('onPlayerConnect2', $player_item);
		}
	}  // playerConnect

	/**
	 * Handles disconnections of players.
	 */
	function playerDisconnect($player) {

		// check for relay server
		if (!$this->server->isrelay && array_key_exists($player[0], $this->server->relayslist)) {
			// log console message
			$this->console('>>> relay server {1} ({2}) disconnected', $player[0],
			               stripColors($this->server->relayslist[$player[0]]['NickName'], false));

			unset($this->server->relayslist[$player[0]]);
			return;
		}

		// delete player and put him into the player item
		// ignore event if disconnect fluke after player already left,
		// or on relay if player from master server (which wasn't added)
		if (!$player_item = $this->server->players->removePlayer($player[0]))
			return;

		// log console message
		$this->console('>> player {1} left the game [{2} : {3} : {4}]',
		               $player_item->pid,
		               $player_item->login,
		               $player_item->nickname,
		               formatTimeH($player_item->getTimeOnline() * 1000, false));

		// throw 'player disconnects' event
		$this->releaseEvent('onPlayerDisconnect', $player_item);
	}  // playerDisconnect


	/**
	 * Player reaches finish.
	 */
	function playerFinish($finish) {

		// if no map info, or if server 'finish', bail out immediately
		if ($this->server->map->name == '' || $finish[0] == 0)
			return;

		// if relay server or not in Play status, bail out immediately
		if ($this->server->isrelay || $this->currstatus != 4)
			return;

		// check for valid player
		if ((!$player = $this->server->players->getPlayer($finish[1])) ||
		    $player->login == '')
			return;

		// build a record object with the current finish information
		$finish_item = new Record();
		$finish_item->player = $player;
		$finish_item->score = $finish[2];
		$finish_item->date = strftime('%Y-%m-%d %H:%M:%S');
		$finish_item->new = false;
		$finish_item->map = clone $this->server->map;
		unset($finish_item->map->gbx);  // reduce memory usage
		unset($finish_item->map->mx);

		// throw prefix 'player finishes' event (checkpoints)
		$this->releaseEvent('onPlayerFinish1', $finish_item);
		// throw main 'player finishes' event
		$this->releaseEvent('onPlayerFinish', $finish_item);
	}  // playerFinish


	/**
	 * Receives chat messages and reacts on them.
	 * Reactions are done by the chat plugins.
	 */
	function playerChat($chat) {

		// verify login
		if ($chat[1] == '' || $chat[1] == '???') {
			trigger_error('playerUid ' . $chat[0] . 'has login [' . $chat[1] . ']!', E_USER_WARNING);
			$this->console('playerUid {1} attempted to use chat command "{2}"',
			               $chat[0], $chat[2]);
			return;
		}

		// ignore master server messages on relay
		if ($this->server->isrelay && $chat[1] == $this->server->relaymaster['Login'])
			return;

		// check for chat command '/' prefix
		$command = $chat[2];
		if ($command != '' && $command[0] == '/') {
			// remove '/' prefix
			$command = substr($command, 1);

			// split strings at spaces and add them into an array
			$params = explode(' ', $command, 2);
			$translated_name = str_replace('+', 'plus', $params[0]);
			$translated_name = str_replace('-', 'dash', $translated_name);

			// check if the function and the command exist
			if (function_exists('chat_' . $translated_name)) {
				// insure parameter exists & is trimmed
				if (isset($params[1]))
					$params[1] = trim($params[1]);
				else
					$params[1] = '';

				// get & verify player object
				if (($author = $this->server->players->getPlayer($chat[1])) &&
				    $author->login != '') {

					// log console message
					$this->console('player {1} used chat command "/{2} {3}"',
					               $chat[1], $params[0], $params[1]);

					// save circumstances in array
					$chat_command = array();
					$chat_command['author'] = $author;
					$chat_command['params'] = $params[1];
	
					// call the function which belongs to the command
					call_user_func('chat_' . $translated_name, $this, $chat_command);
				} else {
					trigger_error('Player object for \'' . $chat[1] . '\' not found!', E_USER_WARNING);
					$this->console('player {1} attempted to use chat command "/{2} {3}"',
					               $chat[1], $params[0], $params[1]);
				}
			} elseif ($params[0] == 'version' || $params[0] == 'serverlogin') {
				// log built-in commands
				$this->console('player {1} used built-in command "/{2}"',
				               $chat[1], $command);
			} else {
				// optionally log bogus chat commands too
				if ($this->settings['log_all_chat']) {
					if ($chat[0] != $this->server->id) {
						$this->console('({1}) {2}', $chat[1], stripColors($chat[2], false));
					}
				}
			}
		} else {
			// optionally log all normal chat too
			if ($this->settings['log_all_chat']) {
				if ($chat[0] != $this->server->id && $chat[2] != '') {
					$this->console('({1}) {2}', $chat[1], stripColors($chat[2], false));
				}
			}
		}
	}  // playerChat


	/**
	 * Gets the specified chat message out of the settings file.
	 */
	function getChatMessage($name) {

		return htmlspecialchars_decode($this->chat_messages[$name][0]);
	}  // getChatMessage


	/**
	 * Checks if an admin is allowed to perform this ability
	 */
	function allowAdminAbility($ability) {

		// map to uppercase before checking list
		$ability = strtoupper($ability);
		if (isset($this->adm_abilities[$ability])) {
			return $this->adm_abilities[$ability][0];
		} else {
			return false;
		}
	}  // allowAdminAbility

	/**
	 * Checks if an operator is allowed to perform this ability
	 */
	function allowOpAbility($ability) {

		// map to uppercase before checking list
		$ability = strtoupper($ability);
		if (isset($this->op_abilities[$ability])) {
			return $this->op_abilities[$ability][0];
		} else {
			return false;
		}
	}  // allowOpAbility

	/**
	 * Checks if the given player is allowed to perform this ability
	 */
	function allowAbility($player, $ability) {

		// check for unlocked password
		if ($this->settings['lock_password'] != '' && !$player->unlocked)
			return false;

		// MasterAdmins can always do everything
		if ($this->isMasterAdmin($player))
			return true;

		// check Admins & their abilities
		if ($this->isAdmin($player))
			return $this->allowAdminAbility($ability);

		// check Operators & their abilities
		if ($this->isOperator($player))
			return $this->allowOpAbility($ability);

		return false;
	}  // allowAbility


	/**
	 * Checks if the given player IP matches the corresponding list IP,
	 * allowing for class C and B wildcards, and multiple comma-separated
	 * IPs / wildcards.
	 */
	function ip_match($playerip, $listip) {

		// check for offline player (removeadmin / removeop)
		if ($playerip == '')
			return true;

		$match = false;
		// check all comma-separated IPs/wildcards
		foreach (explode(',', $listip) as $ip) {
			// check for complete list IP
			if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $ip))
				$match = ($playerip == $ip);
			// check class B wildcard
			elseif (substr($ip, -4) == '.*.*')
				$match = (preg_replace('/\.\d+\.\d+$/', '', $playerip) == substr($ip, 0, -4));
			// check class C wildcard
			elseif (substr($ip, -2) == '.*')
				$match = (preg_replace('/\.\d+$/', '', $playerip) == substr($ip, 0, -2));

			if ($match) return true;
		}
		return false;
	}

	/**
	 * Checks if the given player is in masteradmin list with, optionally,
	 * an authorized IP.
	 */
	function isMasterAdmin($player) {

		// check for masteradmin list entry
		if (isset($player->login) && $player->login != '' && isset($this->masteradmin_list['MPLOGIN']))
			if (($i = array_search($player->login, $this->masteradmin_list['MPLOGIN'])) !== false)
				// check for matching IP if set
				if ($this->masteradmin_list['IPADDRESS'][$i] != '')
					if (!$this->ip_match($player->ip, $this->masteradmin_list['IPADDRESS'][$i])) {
						trigger_error("Attempt to use MasterAdmin login '" . $player->login . "' from IP " . $player->ip . " !", E_USER_WARNING);
						return false;
					} else
						return true;
				else
					return true;
			else
				return false;
		else
			return false;
	}  // isMasterAdmin

	/**
	 * Checks if the given player is in admin list with, optionally,
	 * an authorized IP.
	 */
	function isAdmin($player) {

		// check for admin list entry
		if (isset($player->login) && $player->login != '' && isset($this->admin_list['MPLOGIN']))
			if (($i = array_search($player->login, $this->admin_list['MPLOGIN'])) !== false)
				// check for matching IP if set
				if ($this->admin_list['IPADDRESS'][$i] != '')
					if (!$this->ip_match($player->ip, $this->admin_list['IPADDRESS'][$i])) {
						trigger_error("Attempt to use Admin login '" . $player->login . "' from IP " . $player->ip . " !", E_USER_WARNING);
						return false;
					} else
						return true;
				else
					return true;
			else
				return false;
		else
			return false;
	}  // isAdmin

	/**
	 * Checks if the given player is in operator list with, optionally,
	 * an authorized IP.
	 */
	function isOperator($player) {

		// check for operator list entry
		if (isset($player->login) && $player->login != '' && isset($this->operator_list['MPLOGIN']))
			if (($i = array_search($player->login, $this->operator_list['MPLOGIN'])) !== false)
				// check for matching IP if set
				if ($this->operator_list['IPADDRESS'][$i] != '')
					if (!$this->ip_match($player->ip, $this->operator_list['IPADDRESS'][$i])) {
						trigger_error("Attempt to use Operator login '" . $player->login . "' from IP " . $player->ip . " !", E_USER_WARNING);
						return false;
					} else
						return true;
				else
					return true;
			else
				return false;
		else
			return false;
	}  // isOperator

	/**
	 * Checks if the given player is in any admin tier with, optionally,
	 * an authorized IP.
	 */
	function isAnyAdmin($player) {

		return ($this->isMasterAdmin($player) || $this->isAdmin($player) || $this->isOperator($player));
	}  // isAnyAdmin


	/**
	 * Checks if the given player login is in masteradmin list.
	 */
	function isMasterAdminL($login) {

		if ($login != '' && isset($this->masteradmin_list['MPLOGIN'])) {
			return in_array($login, $this->masteradmin_list['MPLOGIN']);
		} else {
			return false;
		}
	}  // isMasterAdminL

	/**
	 * Checks if the given player login is in admin list.
	 */
	function isAdminL($login) {

		if ($login != '' && isset($this->admin_list['MPLOGIN'])) {
			return in_array($login, $this->admin_list['MPLOGIN']);
		} else {
			return false;
		}
	}  // isAdminL

	/**
	 * Checks if the given player login is in operator list.
	 */
	function isOperatorL($login) {

		// check for operator list entry
		if ($login != '' && isset($this->operator_list['MPLOGIN']))
			return in_array($login, $this->operator_list['MPLOGIN']);
		else
			return false;
	}  // isOperatorL

	/**
	 * Checks if the given player login is in any admin tier.
	 */
	function isAnyAdminL($login) {

		return ($this->isMasterAdminL($login) || $this->isAdminL($login) || $this->isOperatorL($login));
	}  // isAnyAdminL


	/**
	 * Checks if the given player is a spectator.
	 */
	function isSpectator($player) {

		return $player->isspectator;
	}  // isSpectator

	/**
	 * Handles cheating player.
	 */
	function processCheater($login, $checkpoints, $chkpt, $finish) {

		// collect checkpoints
		$cps = '';
		foreach ($checkpoints as $cp)
			$cps .= formatTime($cp) . '/';
		$cps = substr($cps, 0, strlen($cps)-1);  // strip trailing '/'

		// report cheat
		if ($finish == -1)
			trigger_error('Cheat by \'' . $login . '\' detected! CPs: ' . $cps . ' Last: ' . formatTime($chkpt[2]) . ' index: ' . $chkpt[4], E_USER_WARNING);
		else
			trigger_error('Cheat by \'' . $login . '\' detected! CPs: ' . $cps . ' Finish: ' . formatTime($finish), E_USER_WARNING);

		// check for valid player
		if (!$player = $this->server->players->getPlayer($login)) {
			trigger_error('Player object for \'' . $login . '\' not found!', E_USER_WARNING);
			return;
		}

		switch($this->settings['cheater_action']) {

		case 1:  // set to spec
			$rtn = $this->client->query('ForceSpectator', $login, 1);
			if (!$rtn) {
				trigger_error('[' . $this->client->getErrorCode() . '] ForceSpectator - ' . $this->client->getErrorMessage(), E_USER_WARNING);
			} else {
				// allow spectator to switch back to player
				$rtn = $this->client->query('ForceSpectator', $login, 0);
			}
			// force free camera mode on spectator
			$this->client->addCall('ForceSpectatorTarget', array($login, '', 2));
			// free up player slot
			$this->client->addCall('SpectatorReleasePlayerSlot', array($login));

			// log console message
			$this->console('Cheater [{1} : {2}] forced into free spectator!', $login, stripColors($player->nickname, false));

			// show chat message
			$message = formatText('{#server}>> {#admin}Cheater {#highlite}{1}$z$s{#admin} forced into spectator!',
			                      str_ireplace('$w', '', $player->nickname));
			$this->client->query('ChatSendServerMessage', $this->formatColors($message));
			break;

		case 2:  // kick
			// log console message
			$this->console('Cheater [{1} : {2}] kicked!', $login, stripColors($player->nickname, false));

			// show chat message
			$message = formatText('{#server}>> {#admin}Cheater {#highlite}{1}$z$s{#admin} kicked!',
			                      str_ireplace('$w', '', $player->nickname));
			$this->client->query('ChatSendServerMessage', $this->formatColors($message));

			// kick the cheater
			$this->client->query('Kick', $login);
			break;

		case 3:  // ban (& kick)
			// log console message
			$this->console('Cheater [{1} : {2}] banned!', $login, stripColors($player->nickname, false));

			// show chat message
			$message = formatText('{#server}>> {#admin}Cheater {#highlite}{1}$z$s{#admin} banned!',
			                      str_ireplace('$w', '', $player->nickname));
			$this->client->query('ChatSendServerMessage', $this->formatColors($message));

			// update banned IPs file
			$this->bannedips[] = $player->ip;
			$this->writeIPs();

			// ban the cheater and also kick him
			$this->client->query('Ban', $player->login);
			break;

		case 4:  // blacklist & kick
			// log console message
			$this->console('Cheater [{1} : {2}] blacklisted!', $login, stripColors($player->nickname, false));

			// show chat message
			$message = formatText('{#server}>> {#admin}Cheater {#highlite}{1}$z$s{#admin} blacklisted!',
			                      str_ireplace('$w', '', $player->nickname));
			$this->client->query('ChatSendServerMessage', $this->formatColors($message));

			// blacklist the cheater and then kick him
			$this->client->query('BlackList', $player->login);
			$this->client->query('Kick', $player->login);

			// update blacklist file
			$filename = $this->settings['blacklist_file'];
			$rtn = $this->client->query('SaveBlackList', $filename);
			if (!$rtn) {
				trigger_error('[' . $this->client->getErrorCode() . '] SaveBlackList (kick) - ' . $this->client->getErrorMessage(), E_USER_WARNING);
			}
			break;

		case 5:  // blacklist & ban
			// log console message
			$this->console('Cheater [{1} : {2}] blacklisted & banned!', $login, stripColors($player->nickname, false));

			// show chat message
			$message = formatText('{#server}>> {#admin}Cheater {#highlite}{1}$z$s{#admin} blacklisted & banned!',
			                      str_ireplace('$w', '', $player->nickname));
			$this->client->query('ChatSendServerMessage', $this->formatColors($message));

			// update banned IPs file
			$this->bannedips[] = $player->ip;
			$this->writeIPs();

			// blacklist & ban the cheater
			$this->client->query('BlackList', $player->login);
			$this->client->query('Ban', $player->login);

			// update blacklist file
			$filename = $this->settings['blacklist_file'];
			$rtn = $this->client->query('SaveBlackList', $filename);
			if (!$rtn) {
				trigger_error('[' . $this->client->getErrorCode() . '] SaveBlackList (ban) - ' . $this->client->getErrorMessage(), E_USER_WARNING);
			}
			break;

		default: // ignore
		}
	}  // processCheater


	/**
	 * Finds a player ID from its login.
	 */
	function getPlayerId($login, $forcequery = false) {

		if (isset($this->server->players->player_list[$login]) &&
		    $this->server->players->player_list[$login]->id > 0 && !$forcequery) {
			$rtn = $this->server->players->player_list[$login]->id;
		} else {
			$query = 'SELECT Id FROM players
			          WHERE Login=' . quotedString($login);
			$result = mysql_query($query);
			if (mysql_num_rows($result) > 0) {
				$row = mysql_fetch_row($result);
				$rtn = $row[0];
			} else {
				$rtn = 0;
			}
			mysql_free_result($result);
		}
		return $rtn;
	}  // getPlayerId

	/**
	 * Finds a player Nickname from its login.
	 */
	function getPlayerNick($login, $forcequery = false) {

		if (isset($this->server->players->player_list[$login]) &&
		    $this->server->players->player_list[$login]->nickname != '' && !$forcequery) {
			$rtn = $this->server->players->player_list[$login]->nickname;
		} else {
			$query = 'SELECT NickName FROM players
			          WHERE Login=' . quotedString($login);
			$result = mysql_query($query);
			if (mysql_num_rows($result) > 0) {
				$row = mysql_fetch_row($result);
				$rtn = $row[0];
			} else {
				$rtn = '';
			}
			mysql_free_result($result);
		}
		return $rtn;
	}  // getPlayerNick


	/**
	 * Finds an online player object from its login or Player_ID
	 * If $offline = true, search player database instead
	 * Returns false if not found
	 */
	function getPlayerParam($player, $param, $offline = false) {

		// if numeric param, find Player_ID from /players list (hardlimited to 300)
		if (is_numeric($param) && $param >= 0 && $param < 300) {
			if (empty($player->playerlist)) {
				$message = '{#server}> {#error}Use {#highlite}$i/players {#error}first (optionally {#highlite}$i/players <string>{#error})';
				$this->client->query('ChatSendServerMessageToLogin', $this->formatColors($message), $player->login);
				return false;
			}
			$pid = ltrim($param, '0');
			$pid--;
			// find player by given #
			if (array_key_exists($pid, $player->playerlist)) {
				$param = $player->playerlist[$pid]['login'];
				// check online players list
				$target = $this->server->players->getPlayer($param);
			} else {
				// try param as login string as yet
				$target = $this->server->players->getPlayer($param);
				if (!$target) {
					$message = '{#server}> {#error}Player_ID not found! Type {#highlite}$i/players {#error}to see all players.';
					$this->client->query('ChatSendServerMessageToLogin', $this->formatColors($message), $player->login);
					return false;
				}
			}
		} else {  // otherwise login string
			// check online players list
			$target = $this->server->players->getPlayer($param);
		}

		// not found and offline allowed?
		if (!$target && $offline) {
			// check offline players database
			$query = 'SELECT * FROM players
			          WHERE Login=' . quotedString($param);
			$result = mysql_query($query);
			if (mysql_num_rows($result) > 0) {
				$row = mysql_fetch_object($result);
				// create dummy player object
				$target = new Player();
				$target->id = $row->Id;
				$target->login = $row->Login;
				$target->nickname = $row->NickName;
				$target->nation = $row->Nation;
				$target->teamname = $row->TeamName;
				$target->wins = $row->Wins;
				$target->timeplayed = $row->TimePlayed;
			}
			mysql_free_result($result);
		}

		// found anyone anywhere?
		if (!$target) {
			$message = '{#server}> {#highlite}' . $param . ' {#error}is not a valid player! Use {#highlite}$i/players {#error}to find the correct login or Player_ID.';
			$this->client->query('ChatSendServerMessageToLogin', $this->formatColors($message), $player->login);
		}
		return $target;
	}  // getPlayerParam


	/**
	 * Finds a map ID from its UID.
	 */
	function getMapId($uid) {

		$query = 'SELECT Id FROM maps WHERE Uid=' . quotedString($uid);
		$res = mysql_query($query);
		if (mysql_num_rows($res) > 0) {
			$row = mysql_fetch_row($res);
			$rtn = $row[0];
		} else {
			$rtn = 0;
		}
		mysql_free_result($res);
		return $rtn;
	}  // getMapId

	/**
	 * Gets current servername
	 */
	function getServerName() {

		$this->client->query('GetServerName');
		$this->server->name = $this->client->getResponse();
		return $this->server->name;
	}

	/**
	 * Gets current server name & options
	 */
	function getServerOptions() {

		$this->client->query('GetServerOptions');
		$options = $this->client->getResponse();
		$this->server->name = $options['Name'];
		$this->server->maxplay = $options['CurrentMaxPlayers'];
		$this->server->maxspec = $options['CurrentMaxSpectators'];
		$this->server->votetime = $options['CurrentCallVoteTimeOut'];
		$this->server->voterate = $options['CallVoteRatio'];
	}


	/**
	 * Formats aseco color codes in a string,
	 * for example '{#server} hello' will end up as '$ff0 hello'.
	 * It depends on what you've set in the config file.
	 */
	function formatColors($text) {

		// replace all chat colors
		foreach ($this->chat_colors as $key => $value) {
			$text = str_replace('{#'.strtolower($key).'}', $value[0], $text);
		}
		return $text;
	}  // formatColors


	/**
	 * Outputs a formatted string without datetime.
	 */
	function console_text() {

		$args = func_get_args();
		$message = call_user_func_array('formatText', $args) . CRLF;
		echo $message;
		doLog($message);
		flush();
	}  // console_text

	/**
	 * Outputs a string to console with datetime prefix.
	 */
	function console() {

		$args = func_get_args();
		$message = '[' . date('m/d,H:i:s') . '] ' . call_user_func_array('formatText', $args) . CRLF;
		echo $message;
		doLog($message);
		flush();
	}  // console

}  // class Aseco

// define process settings
if (function_exists('date_default_timezone_get') && function_exists('date_default_timezone_set'))
	date_default_timezone_set(@date_default_timezone_get());
ini_set('memory_limit', '50M');
setlocale(LC_NUMERIC, 'C');

// create an instance of MPAseco and run it
$aseco = new Aseco(false);
$aseco->run('config.xml');
?>
