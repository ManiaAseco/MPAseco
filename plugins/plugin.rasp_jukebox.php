<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Jukebox plugin.
 * Allow players to add maps to the 'jukebox' so they can play favorites
 * without waiting. Each player can only have one map in jukebox at a time.
 * Also allows to add a map from MX, and provides related chat commands,
 * including MX searches.
 * Finally, handles the voting and passing for chat-based votes.
 * Updated by Xymph
 * updated by kremsy for Shootmania
 * 
 * Dependencies: requires plugin.rasp_votes.php, plugin.map.php, chat.records2.php;
 *               used by plugin.rasp_votes.php
 */

require_once('includes/rasp.funcs.php');  // functions for the RASP plugins
require_once('includes/mxinfosearcher.inc.php');  // provides MX searches

// Register events and chat commands with aseco
Aseco::registerEvent('onSync', 'init_jbhistory');
Aseco::registerEvent('onEndMap', 'rasp_endmap');
Aseco::registerEvent('onBeginMap', 'rasp_newmap');
Aseco::registerEvent('onJukeboxChanged', 'rasp_updateMaplist');
Aseco::registerEvent('onPlayerDisconnect', 'rasp_playerDisconnect');

// handles action id's "101"-"2000" for jukeboxing max. 1900 maps
// handles action id's "-101"-"-2000" for listing max. 1900 authors
// handles action id's "-2001"-"-2100" for dropping max. 100 jukeboxed maps
// handles action id's "-6001"-"-7900" for invoking /karma on max. 1900 maps
// handles action id's "5201"-"5700" for invoking /mxinfo on max. 500 maps
// handles action id's "5701"-"6200" for invoking /add on max. 500 maps
// handles action id's "6201"-"6700" for invoking /admin add on max. 500 maps
// handles action id's "6701"-"7200" for invoking /xlist auth: on max. 500 authors
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'event_jukebox');

Aseco::addChatCommand('list', 'Lists maps currently on the server (see: /list help)');
Aseco::addChatCommand('jukebox', 'Sets map to be played next (see: /jukebox help)');
if (ABBREV_COMMANDS) {
  Aseco::addChatCommand('jb', 'Sets a map to be played next (see: /jb help)');
  function chat_jb($aseco, $command) { chat_jukebox($aseco, $command); }
}
Aseco::addChatCommand('autojuke', 'Jukeboxes map from /list (see: /autojuke help)');
if (ABBREV_COMMANDS) {
  Aseco::addChatCommand('aj', 'Jukeboxes map from /list (see: /aj help)');
  function chat_aj($aseco, $command) { chat_autojuke($aseco, $command); }
}
Aseco::addChatCommand('add', 'Adds a map directly from MX (<ID>)');
Aseco::addChatCommand('y', 'Votes Yes for a MX map or chat-based vote');
Aseco::addChatCommand('history', 'Shows the 10 most recently played maps');
Aseco::addChatCommand('xlist', 'Lists maps on MX (see: /xlist help)');


function rasp_playerDisconnect($aseco,$player){
  global $jukebox_skipleft, $jukebox, $jukebox_adminnoskip, $jukebox_in_window, $rasp;
  if($jukebox_skipleft){
      while ($next = array_shift($jukebox)) {
        if ($player->login != $next['Login'] ||
            ($jukebox_adminnoskip && $aseco->isAnyAdminL($next['Login']))) {
          // found player, so proceed to play this map
          // put it back for rasp_newmap to remove
          $uid = $next['uid'];
          $jukebox = array_merge(array($uid => $next), $jukebox);
          break; //break the while
        }
        // if jukebox went empty, bail out
        if (!isset($next)) return;
        // player offline, so report skip
        $message = '{RASP Jukebox} Skipping Next Map ' . stripColors($next['Name'], false) . ' because requester ' . stripColors($next['Nick'], false) . ' left';
        $aseco->console_text($message);
        $message = formatText($rasp->messages['JUKEBOX_SKIPLEFT'][0],
                              stripColors($next['Name']), stripColors($next['Nick']));
        if ($jukebox_in_window && function_exists('send_window_message'))
          send_window_message($aseco, $message, true);
        else
          $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
        $aseco->releaseEvent('onJukeboxChanged',array('skip', $next));
      } 
      rasp_updateMaplist($aseco,array()); 
  }
}   
// called @ onJukeboxChanged
function rasp_updateMaplist($aseco,$array){
  global $rasp, $mxadd, $jukebox, $jukebox_check, $jukebox_skipleft, $jukebox_adminnoskip,
         $jukebox_in_window, $mxplaying, $autosave_matchsettings, $replays_counter, $replays_total;
  if (!empty($jukebox)) {
    $keys = array_keys($jukebox); 
    $next = $jukebox[$keys[0]];
    $uid = $next['uid'];
    $jukebox = array_merge(array($uid => $next), $jukebox);
    
    // if a MX map, add it to server
    if ($next['mx']) {
      if ($aseco->debug) {
        $aseco->console_text('{RASP Jukebox} ' . $next['source'] . ' map filename is ' . $next['FileName']);
      }
      $rtn = $aseco->client->query('AddMap', $next['FileName']);
      if (!$rtn) {
        trigger_error('[' . $aseco->client->getErrorCode() . '] AddMap - ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
        return;
      } else {
        // throw 'maplist changed' event
        $aseco->releaseEvent('onMaplistChanged', array('juke', $next['FileName']));
      }
    }
    
    var_dump($next['FileName']);    
    // select jukebox/MX map as next map
    $rtn = $aseco->client->query('ChooseNextMap', $next['FileName']);
    if (!$rtn) {
      trigger_error('[' . $aseco->client->getErrorCode() . '] ChooseNextMap - ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
    } else {
      // report map change from MX or jukebox
      if ($next['mx']) {
        $logmsg = '{RASP Jukebox} Setting Next Map to ' . stripColors($next['Name'], false) . ', file downloaded from ' . $next['source'];
        // remember it for later removal
        $mxplaying = $next['FileName'];
      } else {
        $logmsg = '{RASP Jukebox} Setting Next Map to ' . stripColors($next['Name'], false) . ', requested by ' . stripColors($next['Nick'], false);
      }
  /*    $message = formatText($rasp->messages['JUKEBOX_NEXT'][0],
                             stripColors($next['Name']), stripColors($next['Nick']));
      $aseco->console_text($logmsg);
      if ($jukebox_in_window && function_exists('send_window_message'))
        send_window_message($aseco, $message, true);
      else
        $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));*/
    } 
  }  

}      

// called @ onEndMap
function rasp_endmap($aseco, $data) {
  global $rasp, $mxadd, $jukebox, $jukebox_check, $jukebox_skipleft, $jukebox_adminnoskip,
         $jukebox_in_window, $mxplaying, $autosave_matchsettings, $replays_counter, $replays_total;

  // check for relay server
  if ($aseco->server->isrelay) return;

  // check for & cancel ongoing MX vote
  if (!empty($mxadd)) {
    $aseco->console('Vote by {1} to add {2} reset!',
                    $mxadd['login'], stripColors($mxadd['name'], false));
    $message = $rasp->messages['JUKEBOX_CANCEL'][0];
    if ($jukebox_in_window && function_exists('send_window_message'))
      send_window_message($aseco, $message, true);
    else
      $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
    $mxadd = array();
    // disable all vote panels
    allvotepanels_off($aseco);
  }

  // reset UID check
  $jukebox_check = '';

  // check for jukeboxed map(s)
  if (!empty($jukebox)) {
    $next = array_shift($jukebox);
    if ($aseco->debug) {
      $aseco->console_text('rasp_endmap step1 - $jukebox:' . CRLF .
                           print_r($jukebox, true));
    }

    $message = formatText($rasp->messages['JUKEBOX_NEXT'][0],
                           stripColors($next['Name']), stripColors($next['Nick']));
    $aseco->console_text($logmsg);
    if ($jukebox_in_window && function_exists('send_window_message'))
      send_window_message($aseco, $message, true);
    else
      $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
     

  } else {
    // reset just in case current map was replayed
    $replays_counter = 0;
    $replays_total = 0;
  }

  // check for autosaving maplist
  if ($autosave_matchsettings != '') {
    $rtn = $aseco->client->query('SaveMatchSettings', 'MatchSettings/' . $autosave_matchsettings);
    if (!$rtn) {
      trigger_error('[' . $aseco->client->getErrorCode() . '] SaveMatchSettings - ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
    } else {
      // should a random filter be added?
      if ($aseco->settings['writemaplist_random']) {
        $mapsfile = $aseco->server->mapdir . 'MatchSettings/' . $autosave_matchsettings;
        // read the match settings file
        if (!$list = @file_get_contents($mapsfile)) {
          trigger_error('Could not read match settings file ' . $mapsfile . ' !', E_USER_WARNING);
        } else {
          // insert random filter after <gameinfos> section
          $list = preg_replace('/<\/gameinfos>/', '$0' . CRLF . CRLF .
                               "\t<filter>" . CRLF .
                               "\t\t<random_map_order>1</random_map_order>" . CRLF .
                               "\t</filter>", $list);
 
          $startPos = strpos($list, "<script_name>") + 13;
          $endPos = strpos($list, "</script_name>");
          $substr = substr($list, $startPos, $endPos - $startPos);
          $list = str_ireplace($substr, $aseco->server->gameinfo->scriptname, $list);

          // write out the match settings file
          if (!@file_put_contents($mapsfile, $list)) {
            trigger_error('Could not write match settings file ' . $mapsfile . ' !', E_USER_WARNING);
          }
        }
      }
    }
  }
}  // rasp_endmap

// called @ onBeginMap
function rasp_newmap($aseco, $data) {
  global $rasp, $buffersize, $jukebox, $jb_buffer, $mxplaying, $mxplayed,
         $jukebox_check, $jukebox_failed, $jukebox_permadd, $replays_counter, $replays_total;

  // check for relay server
  if ($aseco->server->isrelay) return;

  // don't duplicate replayed map in history
  if (!empty($jb_buffer)) {
    $previous = array_pop($jb_buffer);
    // put back previous map if different
    if ($previous != $data->uid)
      $jb_buffer[] = $previous;
  }
  // remember current map in history
  if (count($jb_buffer) >= $buffersize) {
    // drop oldest map if buffer full
    array_shift($jb_buffer);
  }
  // append current map to history
  $jb_buffer[] = $data->uid;

  // write map history to file in case of MPASECO restart
  if ($fp = @fopen($aseco->server->mapdir . $aseco->settings['maphist_file'], 'wb')) {
    foreach ($jb_buffer as $uid)
      fwrite($fp, $uid . CRLF);
    fclose($fp);
  } else {
    trigger_error('Could not write map history file ' . $aseco->server->mapdir . $aseco->settings['maphist_file'] . ' !', E_USER_WARNING);
  }

  // process jukebox
  if (!empty($jukebox)) {
    if ($aseco->debug) {
      $aseco->console_text('rasp_newmap step1 - $data->uid: ' . $data->uid);
      $aseco->console_text('rasp_newmap step1 - $jukebox_check: ' . $jukebox_check);
      $aseco->console_text('rasp_newmap step1 - $jukebox:' . CRLF .
                           print_r($jukebox, true));
    }
    // look for current map in jukebox
    if (array_key_exists($data->uid, $jukebox)) {
      if ($aseco->debug) {
        $message = '{RASP Jukebox} Current Map ' .
                   stripColors($jukebox[$data->uid]['Name'], false) . ' loaded - index: ' .
                   array_search($data->uid, array_keys($jukebox));
        $aseco->console_text($message);
      }

      // check for /replay-ed map
      if ($jukebox[$data->uid]['source'] == 'Replay')
        $replays_counter++;
      else
        $replays_counter = 0;
      if (substr($jukebox[$data->uid]['source'], -6) == 'Replay') // AdminReplay
        $replays_total++;
      else
        $replays_total = 0;

      // remove loaded map
      $play = $jukebox[$data->uid];
      unset($jukebox[$data->uid]);

      if ($aseco->debug) {
        $aseco->console_text('rasp_newmap step2a - $jukebox:' . CRLF .
                             print_r($jukebox, true));
      }

      // throw 'jukebox changed' event
      $aseco->releaseEvent('onJukeboxChanged', array('play', $play));
    } else {
      // look for intended map in jukebox
      if ($jukebox_check != '') {
        if (array_key_exists($jukebox_check, $jukebox)) {
          if ($aseco->debug) {
            $message = '{RASP Jukebox} Intended Map ' .
                       stripColors($jukebox[$jukebox_check]['Name'], false) . ' dropped - index: ' .
                       array_search($jukebox_check, array_keys($jukebox));
            $aseco->console_text($message);
          }

          // drop stuck map
          $stuck = $jukebox[$jukebox_check];
          unset($jukebox[$jukebox_check]);

          if ($aseco->debug) {
            $aseco->console_text('rasp_newmap step2b - $jukebox:' . CRLF .
                                 print_r($jukebox, true));
          }

          // throw 'jukebox changed' event
          $aseco->releaseEvent('onJukeboxChanged', array('drop', $stuck));
        } else {
          if ($aseco->debug) {
            $message = '{RASP Jukebox} Intended Map ' . $jukebox_check . ' not found!';
            $aseco->console_text($message);
          }
        }
      }
    }
  }

  // remove previous MX map from server
  if ($mxplayed) {
    // unless it is permanent
    if (!$jukebox_permadd) {
      if ($aseco->debug) {
        $aseco->console_text('rasp_newmap step3 - remove: ' . $mxplayed);
      }
      $rtn = $aseco->client->query('RemoveMap', $mxplayed);
      if (!$rtn) {
        trigger_error('[' . $aseco->client->getErrorCode() . '] RemoveMap - ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
      } else {
        // throw 'maplist changed' event
        $aseco->releaseEvent('onMaplistChanged', array('unjuke', $mxplayed));
      }
    }
    $mxplayed = false;
  }
  // check whether current map was from MX
  if ($mxplaying) {
    // remember it for removal afterwards
    $mxplayed = $mxplaying;
    $mxplaying = false;
  }
  rasp_updateMaplist($aseco,array());
}  // rasp_newmap

// calls function disp_recs() from chat.records2.php
function chat_list($aseco, $command) {
  global $feature_karma;  // from rasp.settings.php

  $player = $command['author'];
  $login = $player->login;

  $recsActive = $aseco->settings['records_activated'];
  // check for relay server
  if ($aseco->server->isrelay) {
    $message = formatText($aseco->getChatMessage('NOTONRELAY'));
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
    return;
  }

  // split params into array
  $arglist = preg_replace('/ +/', ' ', $command['params']);
  $command['params'] = explode(' ', $arglist);
  $cmdcount = count($command['params']);

  if ($cmdcount == 1 && $command['params'][0] == 'help') {
    $header = '{#black}/list$g will show maps in rotation on the server:';
    $help = array();
    $help[] = array('...', '{#black}help',
                    'Displays this help information');
                    
    if($recsActive){               
      $help[] = array('...', '{#black}nofinish',
                      'Shows maps you haven\'t completed');
      $help[] = array('...', '{#black}norank',
                      'Shows maps you don\'t have a rank on');
      $help[] = array('...', '{#black}nogold',
                      'Shows maps you didn\'t beat gold ' .
                       ($aseco->server->gameinfo->mode == Gameinfo::STNT ? 'score on' : 'time on'));
      $help[] = array('...', '{#black}noauthor',
                      'Shows maps you didn\'t beat author '.
                       ($aseco->server->gameinfo->mode == Gameinfo::STNT ? 'score on' : 'time on'));    
  
      $help[] = array('...', '{#black}best$g/{#black}worst',
                      'Shows maps with your best/worst records');
    }
     /*
      $help[] = array('...', '{#black}longest$g/{#black}shortest',
                      'Shows the longest/shortest maps');
    }                                            */
    $help[] = array('...', '{#black}norecent',
                    'Shows maps you didn\'t play recently');                                                            
    $help[] = array('...', '{#black}newest$g/{#black}oldest #',
                    'Shows newest/oldest # maps (def: 50)');
    $help[] = array('...', '{#black}xxx',
                    'Where xxx is part of a map or author name');
  if ($aseco->server->packmask != 'SMStorm') {
    $help[] = array('...', '{#black}env:zzz',
                    'Where zzz is an environment from: SMStorm,');
    $help[] = array('', '',
                    'valley');
    $help[] = array('...', '{#black}xxx env:zzz',
                    'Combines the name and environment searches');
  }
  if ($feature_karma) {
    $help[] = array('...', '{#black}novote',
                    'Shows maps you didn\'t karma vote for');
    $help[] = array('...', '{#black}karma +/-#',
                    'Shows all maps with karma >= or <=');
    $help[] = array('', '',
                    'given value (example: {#black}/list karma -3$g shows all');
    $help[] = array('', '',
                    'maps with karma equal or worse than -3)');
  }
    $help[] = array();
    $help[] = array('Pick an Id number from the list, and use {#black}/jukebox #');
    // display ManiaLink message
    display_manialink($login, $header, array('Icons64x64_1', 'TrackInfo', -0.01), $help, array(1.1, 0.05, 0.3, 0.75), 'OK');
    return;
  } 
  elseif ($cmdcount == 1 && $command['params'][0] == 'norecent') {
    getMapsNoRecent($player);
  }    
             
  elseif ($recsActive && $cmdcount == 1 && $command['params'][0] == 'nofinish') {
    getMapsNoFinish($player);
  }
  elseif ($recsActive && $cmdcount == 1 && $command['params'][0] == 'norank') {  //not working
    getMapsNoRank($player);
  }        /*
  elseif ($cmdcount == 1 && $command['params'][0] == 'nogold') {
    getMapsNoGold($player);
  }
  elseif ($cmdcount == 1 && $command['params'][0] == 'noauthor') {
    getMapsNoAuthor($player);  
  }         */
    
    
  elseif ($recsActive && $cmdcount == 1 && $command['params'][0] == 'best') {
    // avoid interference from possible parameters
    $command['params'] = '';
    // display player records, best first
    disp_recs($aseco, $command, true);  // from chat.records2.php
    return;
  }
  elseif ($recsActive && $cmdcount == 1 && $command['params'][0] == 'worst') {
    // avoid interference from possible parameters
    $command['params'] = '';
    // display player records, worst first
    disp_recs($aseco, $command, false);  // from chat.records2.php
    return;
  }
/*  elseif ($cmdcount == 1 && $command['params'][0] == 'longest') {  
    getMapsByLength($player, false);
  }
  elseif ($cmdcount == 1 && $command['params'][0] == 'shortest') {
    getMapsByLength($player, true);    
  }              */                        
  
  
  
  elseif ($cmdcount >= 1 && $command['params'][0] == 'newest') {
    $count = 50;  // default
    if ($cmdcount == 2 && is_numeric($command['params'][1]) && $command['params'][1] > 0)
      $count = intval($command['params'][1]);
    getMapsByAdd($player, true, $count);
  }
  elseif ($cmdcount >= 1 && $command['params'][0] == 'oldest') {
    $count = 50;  // default
    if ($cmdcount == 2 && is_numeric($command['params'][1]) && $command['params'][1] > 0)
      $count = intval($command['params'][1]);
    getMapsByAdd($player, false, $count);
  }
  elseif ($cmdcount == 1 && $command['params'][0] == 'novote' && $feature_karma) {
    getMapsNoVote($player);
  }
  elseif ($cmdcount == 2 && $command['params'][0] == 'karma' && $feature_karma) {
    $karmaval = intval($command['params'][1]);
    getMapsByKarma($player, $karmaval);
  }
  elseif ($cmdcount >= 1 && strlen($command['params'][0]) > 0) {
    // check for future envs
    if ($aseco->server->packmask != 'SMStorm') {
      $env = '*';  // wildcard
      // find and delete optional env: parameter
      foreach ($command['params'] as &$param) {
        if (strtolower(substr($param, 0, 4)) == 'env:') {
          $env = substr($param, 4);
          $param = '';  // drop env:zzz from arglist
        }
      }
      // rebuild parameter list
      $arglist = trim(implode(' ', $command['params']));
      // set wildcard name if searching for env
      if ($arglist == '') $arglist = '*';
      getAllMaps($player, $arglist, $env);
    } else { // Canyon
      getAllMaps($player, $arglist, '*');  // wildcard
    }
  }
  else {
    getAllMaps($player, '*', '*');  // wildcards
  }

  if (empty($player->maplist)) {
    $message = '{#server}> {#error}No maps found, try again!';
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
    return;
  }
  // display ManiaLink message
  display_manialink_multi($player);
}  // chat_list

function chat_jukebox($aseco, $command) {
  global $rasp, $feature_jukebox, $jukebox_in_window, $jukebox, $jb_buffer;

  $player = $command['author'];
  $login = $player->login;

  // check for relay server
  if ($aseco->server->isrelay) {
    $message = formatText($aseco->getChatMessage('NOTONRELAY'));
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
    return;
  }

  if ($feature_jukebox || $aseco->allowAbility($player, 'chat_jukebox')) {
    // check parameter
    $param = $command['params'];
    if (is_numeric($param) && $param >= 0) {
      if (empty($player->maplist)) {
        $message = $rasp->messages['LIST_HELP'][0];
        $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
        return;
      }

      // check for map by this player in jukebox
      if (!$aseco->allowAbility($player, 'chat_jb_multi')) {
        foreach ($jukebox as $key) {
          if ($login == $key['Login']) {
            $message = $rasp->messages['JUKEBOX_ALREADY'][0];
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
            return;
          }
        }
      }

      // find map by given #
      $jid = ltrim($param, '0');
      $jid--;
      if (array_key_exists($jid, $player->maplist)) {
        $uid = $player->maplist[$jid]['uid'];
        // check if map is already queued in jukebox
        if (array_key_exists($uid, $jukebox)) {  // find by uid in jukebox
          $message = $rasp->messages['JUKEBOX_DUPL'][0];
          $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
          return;
        }
        // check if map was recently played
        elseif (in_array($uid, $jb_buffer)) {
          $message = $rasp->messages['JUKEBOX_REPEAT'][0];
          $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
          // if not an admin with this ability, bail out
          if (!$aseco->allowAbility($player, 'chat_jb_recent'))
            return;
        }

        // check map vs. server settings
        $rtn = $aseco->client->query('CheckMapForCurrentServerParams', $player->maplist[$jid]['filename']);
        if (!$rtn) {
          trigger_error('[' . $aseco->client->getErrorCode() . '] CheckMapForCurrentServerParams - ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
          $message = formatText($rasp->messages['JUKEBOX_IGNORED'][0],
                                stripColors($player->maplist[$jid]['name']), $aseco->client->getErrorMessage());
          $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
        } else {
          // add map to jukebox
          $jukebox[$uid]['FileName'] = $player->maplist[$jid]['filename'];
          $jukebox[$uid]['Name'] = $player->maplist[$jid]['name'];
          $jukebox[$uid]['Env'] = $player->maplist[$jid]['environment'];
          $jukebox[$uid]['Login'] = $player->login;
          $jukebox[$uid]['Nick'] = $player->nickname;
          $jukebox[$uid]['source'] = 'Jukebox';
          $jukebox[$uid]['mx'] = false;
          $jukebox[$uid]['uid'] = $uid;
          $message = formatText($rasp->messages['JUKEBOX'][0],
                                stripColors($player->maplist[$jid]['name']),
                                stripColors($player->nickname));
          if ($jukebox_in_window && function_exists('send_window_message'))
            send_window_message($aseco, $message, false);
          else
            $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));

          // throw 'jukebox changed' event
          $aseco->releaseEvent('onJukeboxChanged', array('add', $jukebox[$uid]));
        }
      } else {
        $message = $rasp->messages['JUKEBOX_NOTFOUND'][0];
        $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
      }
    }
    elseif ($param == 'list') {
      if (!empty($jukebox)) {
        $message = $rasp->messages['JUKEBOX_LIST'][0];
        $i = 1;
        foreach ($jukebox as $item) {
          $message .= '{#highlite}' . $i . '{#emotic}.[{#highlite}' . stripColors($item['Name']) . '{#emotic}], ';
          $i++;
        }
        $message = substr($message, 0, strlen($message)-2);  // strip trailing ", "
        $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
      } else {
        $message = $rasp->messages['JUKEBOX_EMPTY'][0];
        $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
      }
    }
    elseif ($param == 'display') {
      if (!empty($jukebox)) {
        // determine admin ability to drop all jukeboxed maps
        $dropall = $aseco->allowAbility($player, 'dropjukebox');
        $head = 'Upcoming maps in the jukebox:';
        $page = array();
        if ($aseco->server->packmask != 'SMStorm')
          if ($aseco->settings['clickable_lists'])
            $page[] = array('Id', 'Name (click to drop)', 'Env', 'Requester');
          else
            $page[] = array('Id', 'Name', 'Env', 'Requester');
        else
          if ($aseco->settings['clickable_lists'])
            $page[] = array('Id', 'Name (click to drop)', 'Requester');
          else
            $page[] = array('Id', 'Name', 'Requester');

        $tid = 1;
        $lines = 0;
        $player->msgs = array();
        // reserve extra width for $w tags
        $extra = ($aseco->settings['lists_colormaps'] ? 0.2 : 0);
        if ($aseco->server->packmask != 'SMStorm')
          $player->msgs[0] = array(1, $head, array(1.25+$extra, 0.1, 0.6+$extra, 0.15, 0.4), array('Icons128x128_1', 'LoadTrack', 0.02));
        else
          $player->msgs[0] = array(1, $head, array(1.10+$extra, 0.1, 0.6+$extra, 0.4), array('Icons128x128_1', 'LoadTrack', 0.02));
        foreach ($jukebox as $item) {
          $mapname = $item['Name'];
          if (!$aseco->settings['lists_colormaps'])
            $mapname = stripColors($mapname);
          // add clickable button if admin with 'dropjukebox' ability or map by this player
          if ($aseco->settings['clickable_lists'] && $tid <= 100 &&
              ($dropall || $item['Login'] == $login))
            $mapname = array('{#black}' . $mapname, -2000-$tid);  // action id
          else
            $mapname = '{#black}' . $mapname;
          if ($aseco->server->packmask != 'SMStorm')
            $page[] = array(str_pad($tid, 2, '0', STR_PAD_LEFT) . '.',
                            $mapname, $item['Env'],
                            '{#black}' . stripColors($item['Nick']));
          else
            $page[] = array(str_pad($tid, 2, '0', STR_PAD_LEFT) . '.',
                            $mapname,
                            '{#black}' . stripColors($item['Nick']));
          $tid++;
          if (++$lines > 14) {
            if ($aseco->allowAbility($player, 'clearjukebox')) {
              $page[] = array();
              if ($aseco->server->packmask != 'SMStorm')
                $page[] = array('', array('{#emotic}                  Clear Entire Jukebox', 20), '', '');  // action id
              else
                $page[] = array('', array('{#emotic}                  Clear Entire Jukebox', 20), '');  // action id
            }
            $player->msgs[] = $page;
            $lines = 0;
            $page = array();
            if ($aseco->server->packmask != 'SMStorm')
              if ($aseco->settings['clickable_lists'])
                $page[] = array('Id', 'Name (click to drop)', 'Env', 'Requester');
              else
                $page[] = array('Id', 'Name', 'Env', 'Requester');
            else
              if ($aseco->settings['clickable_lists'])
                $page[] = array('Id', 'Name (click to drop)', 'Requester');
              else
                $page[] = array('Id', 'Name', 'Requester');
          }
        }
        // add if last batch exists
        if (count($page) > 1) {
          if ($aseco->allowAbility($player, 'clearjukebox')) {
            $page[] = array();
            if ($aseco->server->packmask != 'SMStorm')
              $page[] = array('', array('{#emotic}                  Clear Entire Jukebox', 20), '', '');  // action id
            else
              $page[] = array('', array('{#emotic}                  Clear Entire Jukebox', 20), '');  // action id
          }
          $player->msgs[] = $page;
        }
        // display ManiaLink message
        display_manialink_multi($player);
      } else {
        $message = $rasp->messages['JUKEBOX_EMPTY'][0];
        $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
      }
    }
    elseif ($param == 'drop') {
      // find map by current player
      $uid = '';
      foreach ($jukebox as $item) {
        if ($item['Login'] == $login) {
          $name = $item['Name'];
          $uid = $item['uid'];
          break;
        }
      }
      if ($uid) {
        // drop it from the jukebox
        $drop = $jukebox[$uid];
        unset($jukebox[$uid]);

        $message = formatText($rasp->messages['JUKEBOX_DROP'][0],
                              stripColors($player->nickname), stripColors($name));
        if ($jukebox_in_window && function_exists('send_window_message'))
          send_window_message($aseco, $message, false);
        else
          $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));

        // throw 'jukebox changed' event
        $aseco->releaseEvent('onJukeboxChanged', array('drop', $drop));
      } else {
        $message = $rasp->messages['JUKEBOX_NODROP'][0];
        $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
      }
    }
    elseif ($param == 'help') {
      $header = '{#black}/jukebox$g will add a map to the jukebox:';
      $help = array();
      $help[] = array('...', '{#black}help',
                      'Displays this help information');
      $help[] = array('...', '{#black}list',
                      'Shows upcoming maps');
      $help[] = array('...', '{#black}display',
                      'Displays upcoming maps and requesters');
      $help[] = array('...', '{#black}drop',
                      'Drops your currently added map');
      $help[] = array('...', '{#black}##',
                      'Adds a map where ## is the Map_ID');
      $help[] = array('', '',
                      'from your most recent {#black}/list$g command');
      // display ManiaLink message
      display_manialink($login, $header, array('Icons64x64_1', 'TrackInfo', -0.01), $help, array(0.9, 0.05, 0.15, 0.7), 'OK');
    } else {
      $message = $rasp->messages['JUKEBOX_HELP'][0];
      $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
    }
  } else {
    $message = $rasp->messages['NO_JUKEBOX'][0];
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
  }
}  // chat_jukebox

function chat_autojuke($aseco, $command) {
  global $feature_karma, $buffersize, $jukebox, $jb_buffer;  // from rasp.settings.php
  $recsActive = $aseco->settings['records_activated'];
  $player = $command['author'];
  $login = $player->login;

  // check for relay server
  if ($aseco->server->isrelay) {
    $message = formatText($aseco->getChatMessage('NOTONRELAY'));
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
    return;
  }

  // split params into array
  $command['params'] = explode(' ', preg_replace('/ +/', ' ', $command['params']));
  $cmdcount = count($command['params']);

  if ($cmdcount == 1 && $command['params'][0] == 'help') {
    $header = '{#black}/autojuke$g will jukebox a map from /list selection:';
    $help = array();
    $help[] = array('...', '{#black}help',
                    'Displays this help information');
    if($recsActive){
      $help[] = array('...', '{#black}nofinish',
                    'Selects maps you haven\'t completed');
      $help[] = array('...', '{#black}norank',
                      'Selects maps you don\'t have a rank on');   
    }
/*  
    $help[] = array('...', '{#black}nogold',
                    'Selects maps you didn\'t beat gold ' .
                     ($aseco->server->gameinfo->mode == Gameinfo::STNT ? 'score on' : 'time on'));
    $help[] = array('...', '{#black}noauthor',
                    'Selects maps you didn\'t beat author '.
                     ($aseco->server->gameinfo->mode == Gameinfo::STNT ? 'score on' : 'time on'));      */
    $help[] = array('...', '{#black}norecent',
                    'Selects maps you didn\'t play recently');
/*  if ($aseco->server->gameinfo->mode != Gameinfo::STNT) {
    $help[] = array('...', '{#black}longest$g/{#black}shortest',
                    'Selects the longest/shortest maps');
  }                                                          */
    $help[] = array('...', '{#black}newest$g/{#black}oldest',
                    'Selects the newest/oldest maps');
  if ($feature_karma) {
    $help[] = array('...', '{#black}novote',
                    'Selects maps you didn\'t karma vote for');
  }
    $help[] = array();
    $help[] = array('The jukeboxed map is the first one from the chosen selection');
    $help[] = array('that is not in the map history.');
    // display ManiaLink message
    display_manialink($login, $header, array('Icons64x64_1', 'TrackInfo', -0.01), $help, array(1.1, 0.05, 0.3, 0.75), 'OK');
    return;
  }
  elseif ($recsActive && $cmdcount == 1 && $command['params'][0] == 'nofinish') {
    getMapsNoFinish($player);
  }
  elseif ($recsActive && $cmdcount == 1 && $command['params'][0] == 'norank') {
    getMapsNoRank($player);
  }  /*
  elseif ($cmdcount == 1 && $command['params'][0] == 'nogold') {
    getMapsNoGold($player);
  }
  elseif ($cmdcount == 1 && $command['params'][0] == 'noauthor') {
    getMapsNoAuthor($player);
  }                                                                    */
  elseif ($cmdcount == 1 && $command['params'][0] == 'norecent') {
    getMapsNoRecent($player);
  }                                                              /*
  elseif ($cmdcount == 1 && $command['params'][0] == 'longest') {
    getMapsByLength($player, false);
  }
  elseif ($cmdcount == 1 && $command['params'][0] == 'shortest') {
    getMapsByLength($player, true);
  }                                                                */
  elseif ($cmdcount == 1 && $command['params'][0] == 'newest') {
    getMapsByAdd($player, true, $buffersize+1);
  }
  elseif ($cmdcount == 1 && $command['params'][0] == 'oldest') {
    getMapsByAdd($player, false, $buffersize+1);
  }
  elseif ($cmdcount == 1 && $command['params'][0] == 'novote' && $feature_karma) {
    getMapsNoVote($player);
  }
  else {
    $message = '{#server}> {#error}Invalid selection, try again!';
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
    return;
  }

  if (empty($player->maplist)) {
    $message = '{#server}> {#error}No maps found, try again!';
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
    return;
  }
  // find first available map
  $ctr = 1;
  $found = false;
  foreach ($player->maplist as $key) {
    if (!array_key_exists($key['uid'], $jukebox) && !in_array($key['uid'], $jb_buffer)) {
      $found = true;
      break;
    }
    $ctr++;
  }
  if ($found) {
    // jukebox it
    $command['params'] = $ctr;
    chat_jukebox($aseco, $command);
  } else {
    $message = '{#server}> {#highlite}' . $command['params'][0] . '{#error} maps currently unavailable, try again later!';
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
  }
}  // chat_autojuke

function chat_add($aseco, $command) {
  global $rasp, $feature_jukebox, $feature_mxadd, $jukebox_in_window, $jukebox,
         $mxadd, $mxtmpdir, $chatvote, $plrvotes, $allow_spec_startvote,
         $r_expire_num, $ta_show_num, $ta_expire_start, $auto_vote_starter;

  $player = $command['author'];
  $login = $player->login;

  // check for relay server
  if ($aseco->server->isrelay) {
    $message = formatText($aseco->getChatMessage('NOTONRELAY'));
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
    return;
  }

  // check whether jukebox & /add are enabled
  if ($feature_jukebox && $feature_mxadd) {
    // check whether this player is spectator
    if (!$allow_spec_startvote && $aseco->isSpectator($player)) {
      $message = $rasp->messages['NO_SPECTATORS'][0];
      $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
      return;
    }

    // check for ongoing MX or chat vote
    if (!empty($mxadd) || !empty($chatvote)) {
      $message = $rasp->messages['VOTE_ALREADY'][0];
      $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
      return;
    }
    // check for special 'mapref' parameter & write file
    if ($command['params'] == 'mapref' && $aseco->allowAbility($player, 'chat_add_mref')) {
      build_mx_mapref($aseco);
      $message = '{#server}> {#emotic}Wrote mapref.txt files';
      $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
      return;
    }

    // split params into array
    $command['params'] = explode(' ', preg_replace('/ +/', ' ', $command['params']));
    // check for valid MX ID
    if (is_numeric($command['params'][0]) && $command['params'][0] >= 0) {
      $trkid = ltrim($command['params'][0], '0');
      $source = 'MX';
      // try to load the map from MX
      $remotefile = 'http://sm.mania-exchange.com/tracks/download/' . $trkid;

      $file = http_get_file($remotefile);
      if ($file === false || $file == -1) {
        $message = '{#server}> {#error}Error downloading, or MX is down!';
        $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
      } else {
        // check for maximum online map size (1024 KB)
        if (strlen($file) >= 1024 * 1024) {
          $message = formatText($rasp->messages['MAP_TOO_LARGE'][0],
                                round(strlen($file) / 1024));
          $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
          return;
        }
        $sepchar = substr($aseco->server->mapdir, -1, 1);
        $partialdir = $mxtmpdir . $sepchar . $trkid . '.Map.gbx';
        $localfile = $aseco->server->mapdir . $partialdir;
        if ($aseco->debug) {
          $aseco->console_text('/add - mxtmpdir=' . $mxtmpdir);
          $aseco->console_text('/add - path + filename=' . $partialdir);
          $aseco->console_text('/add - aseco->server->mapdir = ' . $aseco->server->mapdir);
        }
        if ($nocasepath = file_exists_nocase($localfile)) {
          if (!unlink($nocasepath)) {
            $message = '{#server}> {#error}Error erasing old file. Please contact admin.';
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
            return;
          }
        }
        if (!$lfile = @fopen($localfile, 'wb')) {
          $message = '{#server}> {#error}Error creating file. Please contact admin.';
          $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
          return;
        }
        if (!fwrite($lfile, $file)) {
          $message = '{#server}> {#error}Error saving file - unable to write data. Please contact admin.';
          $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
          fclose($lfile);
          return;
        }
        fclose($lfile);
        $newtrk = getMapData($localfile, true);  // 2nd parm is whether or not to get players & votes required
        if ($newtrk['votes'] == 500 && $newtrk['name'] == 'Not a GBX file') {
          $message = '{#server}> {#error}No such map on ' . $source . '!';
          $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
          unlink($localfile);
          return;
        }
        // dummy player to easily obtain entire map list
        $list = new Player();
        getAllMaps($list, '*', '*');
        // check for map presence on server
        $ctr = 1;
        foreach ($list->maplist as $key) {
          if ($key['uid'] == $newtrk['uid']) {
            $message = $rasp->messages['ADD_PRESENTJB'][0];
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
            unlink($localfile);
            // jukebox already available map
            $player->maplist = $list->maplist;
            $command['params'] = $ctr;
            chat_jukebox($aseco, $command);
            unset($list);
            return;
          }
          $ctr++;
        }
        unset($list);
        // check for map presence in jukebox via previous /add
        if (isset($jukebox[$newtrk['uid']])) {
          $message = $rasp->messages['ADD_DUPL'][0];
          $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
          unlink($localfile);
          return;
        }
        // rename ID filename to map's name
        $md5new = md5_file($localfile);
        $filename = trim(utf8_decode(stripColors($newtrk['name'])));
        $filename = preg_replace('/[^A-Za-z0-9 \'#=+~_,.-]/', '_', $filename);
        $filename = preg_replace('/ +/', ' ', preg_replace('/_+/', '_', $filename));
        $partialdir = $mxtmpdir . $sepchar . $filename . '_' . $trkid . '.Map.gbx';
        // insure unique filename by incrementing sequence number,
        // if not a duplicate map
        $i = 1;
        $dupl = false;
        while ($nocasepath = file_exists_nocase($aseco->server->mapdir . $partialdir)) {
          $md5old = md5_file($nocasepath);
          if ($md5old == $md5new) {
            $dupl = true;
            $partialdir = str_replace($aseco->server->mapdir, '', $nocasepath);
            break;
          } else {
            $partialdir = $mxtmpdir . $sepchar . $filename . '_' . $trkid . '-' . $i++ . '.Map.gbx';
          }
        }
        if ($dupl) {
          unlink($localfile);
        } else {
          rename($localfile, $aseco->server->mapdir . $partialdir);
        }

        // check map vs. server settings
        $rtn = $aseco->client->query('CheckMapForCurrentServerParams', $partialdir);
        if (!$rtn) {
          trigger_error('[' . $aseco->client->getErrorCode() . '] CheckMapForCurrentServerParams - ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
          $message = formatText($rasp->messages['JUKEBOX_IGNORED'][0],
                                stripColors($newtrk['name']), $aseco->client->getErrorMessage());
          $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
        } else {
          // start /add vote
          $mxadd['filename'] = $partialdir;
          $mxadd['votes'] = $newtrk['votes'];
          $mxadd['name'] = $newtrk['name'];
          $mxadd['environment'] = $newtrk['environment'];
          $mxadd['login'] = $player->login;
          $mxadd['nick'] = $player->nickname;
          $mxadd['source'] = $source;
          $mxadd['uid'] = $newtrk['uid'];
  
          // reset votes, rounds counter, TA interval counter & start time
          $plrvotes = array();
          $r_expire_num = 0;
          $ta_show_num = 0;
          $ta_expire_start = time_playing($aseco);  // from plugin.map.php
          // compile & show chat message
          $message = formatText($rasp->messages['JUKEBOX_ADD'][0],
                                stripColors($mxadd['nick']),
                                stripColors($mxadd['name']),
                                $mxadd['source'], $mxadd['votes']);
          $message = str_replace('{br}', LF, $message);  // split long message
          if ($jukebox_in_window && function_exists('send_window_message'))
            send_window_message($aseco, $message, true);
          else
            $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
  
          // enable all vote panels
          if (function_exists('allvotepanels_on'))
            allvotepanels_on($aseco, $login, $aseco->formatColors('{#emotic}'));
          // vote automatically by vote starter?
          if ($auto_vote_starter) chat_y($aseco, $command);
        }
      }
    } else {
      $message = '{#server}> {#error}You must include a MX map ID!';
      $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
    }
  } else {
    $message = $rasp->messages['NO_ADD'][0];
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
  }
}  // chat_add

function build_mx_mapref($aseco) {
  global $mxdir, $mxtmpdir;

  $td = $aseco->server->mapdir . $mxdir;
  if (is_dir($td)) {
    $dir = opendir($td);
    $fp = fopen($td . '/mapref.txt', 'w');
    while (($file = readdir($dir)) !== false) {
      if (strtolower(substr($file, -4)) == '.gbx') {
        $ci = getMapData($td . '/' . $file, false);
        $file = str_ireplace('.map.gbx', '', $file);
        fwrite($fp, $file . "\t" . $ci['environment'] . "\t" . $ci['author'] . "\t" . stripColors($ci['name']) . "\t" . $ci['cost'] . CRLF);
      }
    }
    fclose($fp);
    closedir($dir);
  }

  $td = $aseco->server->mapdir . $mxtmpdir;
  if (is_dir($td)) {
    $dir = opendir($td);
    $fp = fopen($td . '/mapref.txt', 'w');
    while (($file = readdir($dir)) !== false) {
      if (strtolower(substr($file, -4)) == '.gbx') {
        $ci = getMapData($td . '/' . $file, false);
        $file = str_ireplace('.map.gbx', '', $file);
        fwrite($fp, $file . "\t" . $ci['environment'] . "\t" . $ci['author'] . "\t" . stripColors($ci['name']) . "\t" . $ci['cost'] . CRLF);
      }
    }
    fclose($fp);
    closedir($dir);
  }

}  // build_mx_mapref

function chat_y($aseco, $command) {
  global $rasp, $mxadd, $plrvotes, $chatvote, $jukebox, $allow_spec_voting,
         $jukebox_in_window, $vote_in_window, $feature_mxadd, $feature_votes,
         $ladder_fast_restart;

  $player = $command['author'];
  $login = $player->login;

  // check for relay server
  if ($aseco->server->isrelay) {
    $message = formatText($aseco->getChatMessage('NOTONRELAY'));
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
    return;
  }

  // check whether this player is spectator but not any admin
  if (!$allow_spec_voting && $aseco->isSpectator($player) && !$aseco->isAnyAdmin($player)) {
    $message = $rasp->messages['NO_SPECTATORS'][0];
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
    return;
  }

  // check whether this player already voted
  if (in_array($login, $plrvotes)) {
    $message = '{#server}> {#error}You have already voted!';
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
    return;
  }

  // check for ongoing MX vote
  if (!empty($mxadd) && $mxadd['votes'] >= 0) {
    $votereq = $mxadd['votes'];
    $votereq--;
    // check for sufficient votes
    if ($votereq > 0) {
      // remind all players to vote
      $mxadd['votes'] = $votereq;
      $message = formatText($rasp->messages['JUKEBOX_Y'][0],
                            $votereq, ($votereq == 1 ? '' : 's'),
                            stripColors($mxadd['name']));
      if ($jukebox_in_window && function_exists('send_window_message'))
        send_window_message($aseco, $message, false);
      else
        $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
      // register this player's vote
      $plrvotes[] = $login;
      // disable panel in case /y was used to vote
      votepanel_off($aseco, $login);
    } else {
      // pass, so add it to jukebox
      $uid = $mxadd['uid'];
      $jukebox[$uid]['FileName'] = $mxadd['filename'];
      $jukebox[$uid]['Name'] = $mxadd['name'];
      $jukebox[$uid]['Env'] = $mxadd['environment'];
      $jukebox[$uid]['Login'] = $mxadd['login'];
      $jukebox[$uid]['Nick'] = $mxadd['nick'];
      $jukebox[$uid]['source'] = $mxadd['source'];
      $jukebox[$uid]['mx'] = true;
      $jukebox[$uid]['uid'] = $uid;

      // show chat message
      $message = formatText($rasp->messages['JUKEBOX_PASS'][0],
                            stripColors($mxadd['name']));
      if ($jukebox_in_window && function_exists('send_window_message'))
        send_window_message($aseco, $message, false);
      else
        $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
      // clear for next vote
      $mxadd = array();
      // disable all vote panels
      allvotepanels_off($aseco);

      // throw 'jukebox changed' event
      $aseco->releaseEvent('onJukeboxChanged', array('add', $jukebox[$uid]));
    }
  }
  // check for ongoing chat vote
  elseif (!empty($chatvote) && $chatvote['votes'] >= 0) {
    $votereq = $chatvote['votes'];
    $votereq--;
    // check for sufficient votes
    if ($votereq > 0) {
      // remind players to vote
      $chatvote['votes'] = $votereq;
      $message = formatText($rasp->messages['VOTE_Y'][0],
                            $votereq, ($votereq == 1 ? '' : 's'),
                            $chatvote['desc']);
      if ($vote_in_window && function_exists('send_window_message'))
        send_window_message($aseco, $message, false);
      else
        $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
      // register this player's vote
      $plrvotes[] = $login;
      // disable panel in case /y was used to vote
      votepanel_off($aseco, $login);
    } else {
      // show chat message
      $message = formatText($rasp->messages['VOTE_PASS'][0],
                            $chatvote['desc']);
      if ($vote_in_window && function_exists('send_window_message'))
        send_window_message($aseco, $message, false);
      else
        $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));

      // pass, so perform action
      switch ($chatvote['type']) {
      case 0:  // endround
        $aseco->client->query('ForceEndRound');
        $aseco->console('Vote by {1} forced round end!',
                        $chatvote['login']);
        break;
      case 1:  // ladder
        if ($ladder_fast_restart) {
          global $atl_restart;  // from plugin.autotime.php

          // perform quick restart
          if (isset($atl_restart)) $atl_restart = true;
          if ($aseco->server->gameinfo->mode == Gameinfo::CUP)
            // don't clear scores if in Cup mode
            $aseco->client->query('RestartMap', true);
          else
            $aseco->client->query('RestartMap');
        } else {
          // prepend current map to start of jukebox
          $uid = $aseco->server->map->uid;
          $jukebox = array_reverse($jukebox, true);
          $jukebox[$uid]['FileName'] = $aseco->server->map->filename;
          $jukebox[$uid]['Name'] = $aseco->server->map->name;
          $jukebox[$uid]['Env'] = $aseco->server->map->environment;
          $jukebox[$uid]['Login'] = $chatvote['login'];
          $jukebox[$uid]['Nick'] = $chatvote['nick'];
          $jukebox[$uid]['source'] = 'Ladder';
          $jukebox[$uid]['mx'] = false;
          $jukebox[$uid]['uid'] = $uid;
          $jukebox = array_reverse($jukebox, true);

          if ($aseco->debug) {
            $aseco->console_text('/ladder pass - $jukebox:' . CRLF .
                                 print_r($jukebox, true));
          }

          // throw 'jukebox changed' event
          $aseco->releaseEvent('onJukeboxChanged', array('restart', $jukebox[$uid]));

          // ...and skip to it
          if ($aseco->server->gameinfo->mode == Gameinfo::CUP)
            // don't clear scores if in Cup mode
            $aseco->client->query('NextMap', true);
          else
            $aseco->client->query('NextMap');
        }
        $aseco->console('Vote by {1} restarted map for ladder!',
                        $chatvote['login']);
        break;
      case 2:  // replay
        // prepend current map to start of jukebox
        $uid = $aseco->server->map->uid;
        $jukebox = array_reverse($jukebox, true);
        $jukebox[$uid]['FileName'] = $aseco->server->map->filename;
        $jukebox[$uid]['Name'] = $aseco->server->map->name;
        $jukebox[$uid]['Env'] = $aseco->server->map->environment;
        $jukebox[$uid]['Login'] = $chatvote['login'];
        $jukebox[$uid]['Nick'] = $chatvote['nick'];
        $jukebox[$uid]['source'] = 'Replay';
        $jukebox[$uid]['mx'] = false;
        $jukebox[$uid]['uid'] = $uid;
        $jukebox = array_reverse($jukebox, true);

        if ($aseco->debug) {
          $aseco->console_text('/replay pass - $jukebox:' . CRLF .
                               print_r($jukebox, true));
        }

        $aseco->console('Vote by {1} replays map after finish!',
                        $chatvote['login']);

        // throw 'jukebox changed' event
        $aseco->releaseEvent('onJukeboxChanged', array('replay', $jukebox[$uid]));
        break;
      case 3:  // skip
        // skip immediately to next map
        if ($aseco->server->gameinfo->mode == Gameinfo::CUP)
          // don't clear scores if in Cup mode
          $aseco->client->query('NextMap', true);
        else
          $aseco->client->query('NextMap');
        $aseco->console('Vote by {1} skips this map!',
                        $chatvote['login']);
        break;
      case 4:  // kick
        $rtn = $aseco->client->query('Kick', $chatvote['target']);
        if (!$rtn) {
          trigger_error('[' . $aseco->client->getErrorCode() . '] Kick - ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
        } else {
          $aseco->console('Vote by {1} kicked player {2}!',
                          $chatvote['login'], $chatvote['target']);
        }
        break;
      case 6:  // ignore
        $rtn = $aseco->client->query('Ignore', $chatvote['target']);
        if (!$rtn) {
          trigger_error('[' . $aseco->client->getErrorCode() . '] Ignore - ' . $aseco->client->getErrorMessage(), E_USER_WARNING);
        } else {
          // check if in global mute/ignore list
          if (!in_array($chatvote['target'], $aseco->server->mutelist)) {
            // add player to list
            $aseco->server->mutelist[] = $chatvote['target'];
          }
          $aseco->console('Vote by {1} ignored player {2}!',
                          $chatvote['login'], $chatvote['target']);
        }
        break;
      case 5:  // add - can't occur here
        break;
      }

      // clear for next vote
      $chatvote = array();
      // disable all vote panels
      allvotepanels_off($aseco);
    }
  // all quiet on the voting front :)
  } else {
    $message = '{#server}> {#error}There is no vote right now!';
    if ($feature_mxadd) {
      if ($feature_votes) {
        $message .= ' Use {#highlite}$i/add <ID>{#error} or see {#highlite}$i/helpvote{#error} to start one.';
      } else {
        $message .= ' Use {#highlite}$i/add <ID>{#error} to start one.';
      }
    } else {
      if ($feature_votes) {
        $message .= ' See {#highlite}$i/helpvote{#error} to start one.';
      } else {
        $message .= '';
      }
    }
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
  }
}  // chat_y

// called @ onSync
function init_jbhistory($aseco, $data) {
  global $buffersize, $jb_buffer;

  // read map history from file in case of MPASECO restart
  $jb_buffer = array();
  if ($fp = @fopen($aseco->server->mapdir . $aseco->settings['maphist_file'], 'rb')) {
    while (!feof($fp)) {
      $uid = rtrim(fgets($fp));
      if ($uid != '') $jb_buffer[] = $uid;
    }
    fclose($fp);
    // keep only most recent $buffersize entries
    $jb_buffer = array_slice($jb_buffer, -$buffersize);
    // drop current (last) map as rasp_newmap() will add it back
    array_pop($jb_buffer);
  }
}  // init_jbhistory

function chat_history($aseco, $command) {
  global $rasp, $jb_buffer;

  $player = $command['author'];

  // check for relay server
  if ($aseco->server->isrelay) {
    $message = formatText($aseco->getChatMessage('NOTONRELAY'));
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
    return;
  }

  if (!empty($jb_buffer)) {
    $message = $rasp->messages['HISTORY'][0];
    // loop over last 10 (max) entries in buffer
    for ($i = 1, $j = count($jb_buffer)-1; $i <= 10 && $j >= 0; $i++, $j--) {
      // get map name from UID
      $query = 'SELECT Name FROM maps
                WHERE Uid=' . quotedString($jb_buffer[$j]);
      $res = mysql_query($query);
      $row = mysql_fetch_object($res);
      mysql_free_result($res);

      $message .= '{#highlite}' . $i . '{#emotic}.[{#highlite}' . stripColors($row->Name) . '{#emotic}], ';
    }

    $message = substr($message, 0, strlen($message)-2);  // strip trailing ", "
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
    return;
  } else {
    $message = '{#server}> {#error}No map history available!';
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $player->login);
    return;
  }
}  // chat_history

function chat_xlist($aseco, $command) {

  $player = $command['author'];
  $login = $player->login;

  // split params into array
  $command['params'] = explode(' ', preg_replace('/ +/', ' ', $command['params']));
  $cmdcount = count($command['params']);

  $section = 'SM';

  if ($cmdcount == 1 && $command['params'][0] == 'help') {
    $header = '{#black}/xlist$g will show maps on MX:';
    $help = array();
    $help[] = array('...', '{#black}help',
                    'Displays this help information');
    $help[] = array('...', '{#black}recent',
                    'Lists the 10 most recent maps');
    $help[] = array('...', '{#black}xxx',
                    'Lists maps matching (partial) name');
    $help[] = array('...', '{#black}auth:yyy',
                    'Lists maps matching (partial) author');
    $help[] = array('...', '{#black}env:zzz',
                    'Where zzz is an environment from: canyon,');
    $help[] = array('', '',
                    'valley');
    $help[] = array('...', '{#black}xxx auth:yyy env:zzz',
                    'Combines the name, author and/or env searches');
    $help[] = array();
    $help[] = array('Pick a MX Id number from the list, and use {#black}/add #');
    // display ManiaLink message
    display_manialink($login, $header, array('Icons64x64_1', 'TrackInfo', -0.01), $help, array(1.2, 0.05, 0.35, 0.8), 'OK');
    return;
  }
  elseif ($command['params'][0] == 'recent') {
    // get 10 most recent maps
    $maps = new MXInfoSearcher($section, '', '', '', true);
  } else {
    $name = '';
    $auth = '';
    $env = '';
    // collect search parameters
    foreach ($command['params'] as $param) {
      if (strtolower(substr($param, 0, 5)) == 'auth:') {
        $auth = substr($param, 5);
      } elseif (strtolower(substr($param, 0, 4)) == 'env:') {
        $env = substr($param, 4);
      } else {
        if ($name == '')
          $name = $param;
        else  // concatenate words in name
          $name .= '%20' . $param;
      }
    }

    // search for matching maps
    $maps = new MXInfoSearcher($section, $name, $auth, $env, false);
  }

  // check for any results
  if (!$maps->valid()) {
    $message = '{#server}> {#error}No maps found, or MX is down!';
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
    if ($maps->error != '')
      trigger_error($maps->error, E_USER_WARNING);
    return;
  }
  $player->maplist = array();

  $adminadd = $aseco->allowAbility($player, 'add');
  $head = 'Maps On MX Section {#black}SM$g:';
  $msg = array();
  if ($aseco->settings['clickable_lists'])
    if ($adminadd)
      $msg[] = array('Id', 'MX', 'Name (click to /add)', '$nAdmin', 'Author', 'GameType');
    else
      $msg[] = array('Id', 'MX', 'Name (click to /add)', 'Author', 'GameType');
  else
    $msg[] = array('Id', 'MX', 'Name', 'Author', 'GameType');

  $tid = 1;
  $lines = 0;
  $player->msgs = array();
  if ($adminadd && $aseco->settings['clickable_lists'])
    $player->msgs[0] = array(1, $head, array(1.55, 0.12, 0.16, 0.6, 0.1, 0.4, 0.17), array('Icons128x128_1', 'LoadTrack', 0.02));
  else
    $player->msgs[0] = array(1, $head, array(1.45, 0.12, 0.16, 0.6, 0.4, 0.17), array('Icons128x128_1', 'LoadTrack', 0.02));

  // list all found maps
  foreach ($maps as $row) {
    $mxid = '{#black}' . $row->id;
    $name = '{#black}' . $row->name;
    $author = $row->author;
    // add clickable buttons
    if ($aseco->settings['clickable_lists'] && $tid <= 500) {
      $mxid = array($mxid, $tid+5200);  // action ids
      $name = array($name, $tid+5700);
      $author = array($author, $tid+6700);

      // store map in player object for action buttons
      $trkarr = array();
      $trkarr['id'] = $row->id;
      $trkarr['author'] = $row->author;
      $player->maplist[] = $trkarr;
    }

    if ($adminadd)
      if ($aseco->settings['clickable_lists'] && $tid <= 500)
        $msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
                       $mxid, $name, array('Add', $tid+6200), $author, $row->maptype);
      else
        $msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
                       $mxid, $name, 'Add', $author, $row->maptype);
    else
      $msg[] = array(str_pad($tid, 3, '0', STR_PAD_LEFT) . '.',
                     $mxid, $name, $author, $row->maptype);
    $tid++;
    if (++$lines > 14) {
      $player->msgs[] = $msg;
      $lines = 0;
      $msg = array();
      if ($aseco->settings['clickable_lists'])
        if ($adminadd)
          $msg[] = array('Id', 'MX', 'Name (click to /add)', '$nAdmin', 'Author', 'GameType');
        else
          $msg[] = array('Id', 'MX', 'Name (click to /add)', 'Author', 'GameType');
      else
        $msg[] = array('Id', 'MX', 'Name', 'Author', 'GameType');
    }
  }
  // add if last batch exists
  if (count($msg) > 1)
    $player->msgs[] = $msg;

  // display ManiaLink message
  display_manialink_multi($player);
}  // chat_xlist


// called @ onPlayerManialinkPageAnswer
// Handles ManiaLink jukebox responses
// [0]=PlayerUid, [1]=Login, [2]=Answer, [3]=Entries
function event_jukebox($aseco, $answer) {
  global $jukebox;

  // leave actions outside 101 - 2000, -2000 - -101, -2100 - -2001,
  // -6001 - -7900 & 5201 - 7200 to other handlers
  $action = (int) $answer[2];
  if ($action >= 101 && $action <= 2000) {
    // get player
    $player = $aseco->server->players->getPlayer($answer[1]);

    // log clicked command
    $aseco->console('player {1} clicked command "/jukebox {2}"',
                    $player->login, $action-100);

    // jukebox selected map
    $command = array();
    $command['author'] = $player;
    $command['params'] = $action-100;
    chat_jukebox($aseco, $command);
  }
  elseif ($action >= -7900 && $action <= -6001) {
    // get player
    $player = $aseco->server->players->getPlayer($answer[1]);

    // log clicked command
    $aseco->console('player {1} clicked command "/karma {2}"',
                    $player->login, abs($action)-6000);

    // karma selected map
    $command = array();
    $command['author'] = $player;
    $command['params'] = abs($action)-6000;
    chat_karma($aseco, $command);
  }
  elseif ($action >= -2000 && $action <= -101) {
    // get player
    $player = $aseco->server->players->getPlayer($answer[1]);
    $author = $player->maplist[abs($action)-101]['author'];

    // close main window because /list can take a while
    mainwindow_off($aseco, $player->login);
    // log clicked command
    $aseco->console('player {1} clicked command "/list {2}"',
                    $player->login, $author);

    // search for maps by author
    $command = array();
    $command['author'] = $player;
    $command['params'] = $author;
    chat_list($aseco, $command);
  }
  elseif ($action >= -2100 && $action <= -2001) {
    // get player
    $player = $aseco->server->players->getPlayer($answer[1]);
    $login = $player->login;

    // determine admin ability to drop all jukeboxed maps
    if ($aseco->allowAbility($player, 'dropjukebox')) {
      // log clicked command
      $aseco->console('player {1} clicked command "/admin dropjukebox {2}"',
                      $login, abs($action)-2000);

      // drop any jukeboxed map by admin
      $command = array();
      $command['author'] = $player;
      $command['params'] = 'dropjukebox ' . (abs($action)-2000);
      chat_admin($aseco, $command);

      // check whether last map was dropped
      if (empty($jukebox)) {
        // close main window
        mainwindow_off($aseco, $login);
      } else {
        // log clicked command
        $aseco->console('player {1} clicked command "/jukebox display"', $login);
        // display updated list
        $command['params'] = 'display';
        chat_jukebox($aseco, $command);
      }
    } else {
      // log clicked command
      $aseco->console('player {1} clicked command "/jukebox drop"', $login);

      // drop user's jukeboxed map
      $command = array();
      $command['author'] = $player;
      $command['params'] = 'drop';
      chat_jukebox($aseco, $command);

      // check whether last map was dropped
      if (empty($jukebox)) {
        // close main window
        mainwindow_off($aseco, $login);
      } else {
        // log clicked command
        $aseco->console('player {1} clicked command "/jukebox display"', $login);
        // display updated list
        $command['params'] = 'display';
        chat_jukebox($aseco, $command);
      }
    }
  }
  elseif ($action >= 5201 && $action <= 5700) {
    // get player & map ID
    $player = $aseco->server->players->getPlayer($answer[1]);
    $mxid = $player->maplist[$action-5201]['id'];

    // log clicked command
    $aseco->console('player {1} clicked command "/mxinfo {2}"',
                    $player->login, $mxid);

    // /mxinfo selected map
    $command = array();
    $command['author'] = $player;
    $command['params'] = $mxid;
    chat_mxinfo($aseco, $command);
  }
  elseif ($action >= 5701 && $action <= 6200) {
    // get player & map ID
    $player = $aseco->server->players->getPlayer($answer[1]);
    $mxid = $player->maplist[$action-5701]['id'];

    // log clicked command
    $aseco->console('player {1} clicked command "/add {2}"',
                    $player->login, $mxid);

    // /add selected map
    $command = array();
    $command['author'] = $player;
    $command['params'] = $mxid;
    chat_add($aseco, $command);
  }
  elseif ($action >= 6201 && $action <= 6700) {
    // get player & map ID
    $player = $aseco->server->players->getPlayer($answer[1]);
    $mxid = $player->maplist[$action-6201]['id'];

    // log clicked command
    $aseco->console('player {1} clicked command "/admin add {2}"',
                    $player->login, $mxid);

    // /admin add selected map
    $command = array();
    $command['author'] = $player;
    $command['params'] = 'add ' . $mxid;
    chat_admin($aseco, $command);
  }
  elseif ($action >= 6701 && $action <= 7200) {
    // get player & map author
    $player = $aseco->server->players->getPlayer($answer[1]);
    $author = $player->maplist[$action-6701]['author'];
    // insure multi-word author is single parameter
    $author = str_replace(' ', '%20', $author);

    // log clicked command
    $aseco->console('player {1} clicked command "/xlist auth:{2}"',
                    $player->login, $author);

    // /xlist auth: selected author
    $command = array();
    $command['author'] = $player;
    $command['params'] = 'auth:' . $author;
    chat_xlist($aseco, $command);
  }
}  // event_jukebox
?>