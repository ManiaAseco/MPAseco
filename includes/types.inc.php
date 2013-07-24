<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

// Updated by kremsy

/**
 * Structure of a Record.
 */
class Record {
  var $player;
  var $map;
  var $score;
  var $date;
  var $checks;
  var $new;
  var $pos;
}  // class Record

/**
 * Manages a list of records.
 * Add records to the list and remove them.
 */
class RecordList {
  var $record_list;
  var $max;

  // instantiates a record list with max $limit records
  function RecordList($limit) {
    $this->record_list = array();
    $this->max = $limit;
  }

  function setLimit($limit) {
    $this->max = $limit;
  }

  function getRecord($rank) {
    if (isset($this->record_list[$rank]))
      return $this->record_list[$rank];
    else
      return false;
  }

  function setRecord($rank, $record) {
    if (isset($this->record_list[$rank])) {
      return $this->record_list[$rank] = $record;
    } else {
      return false;
    }
  }

  function moveRecord($from, $to) {
    moveArrayElement($this->record_list, $from, $to);
  }

  function addRecord($record, $rank = -1) {

    // if no rank was set for this record, then put it to the end of the list
    if ($rank == -1) {
      $rank = count($this->record_list);
    }

    // do not insert a record behind the border of the list
    if ($rank >= $this->max) return;

    // do not insert a record with no score
    if ($record->score <= 0) return;

    // if the given object is a record
    if (get_class($record) == 'Record') {

      // if records are getting too much, drop the last from the list
      if (count($this->record_list) >= $this->max) {
        array_pop($this->record_list);
      }

      // insert the record at the specified position
      return insertArrayElement($this->record_list, $record, $rank);
    }
  }

  function delRecord($rank = -1) {

    // do not remove a record outside the current list
    if ($rank < 0 || $rank >= count($this->record_list)) return;

    // remove the record from the specified position
    return removeArrayElement($this->record_list, $rank);
  }

  function count() {
    return count($this->record_list);
  }

  function clear() {
    $this->record_list = array();
  }
}  // class RecordList


/**
 * Structure of a Player.
 * Can be instantiated with an RPC 'GetPlayerInfo' or
 * 'GetDetailedPlayerInfo' response.
 */
class Player {
  var $id;
  var $pid;
  var $login;
  var $nickname;
  var $teamname;
  var $ip;
  var $client;
  var $ipport;
  var $zone;
  var $continent;  
  var $nation;
  var $prevstatus;
  var $isspectator;
  var $isofficial;
  var $language;
  var $avatar;
  var $teamid;
  var $unlocked;
  var $ladderrank;
  var $ladderscore;
  var $created;
  var $wins;
  var $newwins;
  var $timeplayed;
  var $maplist;
  var $playerlist;
  var $msgs;
  var $pmbuf;
  var $mutelist;
  var $mutebuf;
  var $style;
  var $panels;
  var $panelbg;
  var $speclogin;
  var $dedirank;
  var $disconnectionreason;

  function getWins() {
    return $this->wins + $this->newwins;
  }

  function getTimePlayed() {
    return $this->timeplayed + $this->getTimeOnline();
  }

  function getTimeOnline() {
    return $this->created > 0 ? time() - $this->created : 0;
  }

  // instantiates the player with an RPC response
  function Player($rpc_infos = null) {
    $this->id = 0;
    if ($rpc_infos) {
      $this->pid = $rpc_infos['PlayerId'];
      $this->login = $rpc_infos['Login'];
      $this->nickname = $rpc_infos['NickName'];
      $this->ipport = $rpc_infos['IPAddress'];
      $this->ip = preg_replace('/:\d+/', '', $rpc_infos['IPAddress']);  // strip port
      $this->prevstatus = false;
      $this->isspectator = $rpc_infos['IsSpectator'];
      $this->isofficial = $rpc_infos['IsInOfficialMode'];
      $this->teamname = $rpc_infos['LadderStats']['TeamName'];
      $this->zone = substr($rpc_infos['Path'], 6);  // strip 'World|'
      $zones = explode('|', $rpc_infos['Path']);
      if (isset($zones[1])) {
        switch ($zones[1]) {
          case 'Europe':
          case 'Africa':
          case 'Asia':
          case 'Middle East':
          case 'North America':
          case 'South America':
          case 'Oceania':
            $this->continent = $zones[1];
            $this->nation = $zones[2];
            break;
          default:
            $this->continent = '';
            $this->nation = $zones[1];
        }
      } else {
        $this->continent = '';
        $this->nation = '';
      }
      $this->ladderrank = $rpc_infos['LadderStats']['PlayerRankings'][0]['Ranking'];
      $this->ladderscore = round($rpc_infos['LadderStats']['PlayerRankings'][0]['Score'], 2);
      $this->client = $rpc_infos['ClientVersion'];
      $this->language = $rpc_infos['Language'];
      $this->avatar = $rpc_infos['Avatar']['FileName'];
      $this->teamid = $rpc_infos['TeamId'];
      $this->created = time();
    } else {
      // set defaults
      $this->pid = 0;
      $this->login = '';
      $this->nickname = '';
      $this->ipport = '';
      $this->ip = '';
      $this->prevstatus = false;
      $this->isspectator = false;
      $this->isofficial = false;
      $this->teamname = '';
      $this->zone = '';
      $this->continent = '';
      $this->nation = '';
      $this->ladderrank = 0;
      $this->ladderscore = 0;
      $this->created = 0;
    }
    $this->wins = 0;
    $this->newwins = 0;
    $this->timeplayed = 0;
    $this->unlocked = false;
    $this->pmbuf = array();
    $this->mutelist = array();
    $this->mutebuf = array();
    $this->style = array();
    $this->panels = array();
    $this->panelbg = array();
    $this->speclogin = '';
    $this->dedirank = 0;
  }
}  // class Player

/**
 * Manages players on the server.
 * Add player and remove them.
 */
class PlayerList {
  var $player_list;

  // instantiates the empty player list
  function PlayerList() {
    $this->player_list = array();
  }

  function nextPlayer() {
    if (is_array($this->player_list)) {
      $player_item = current($this->player_list);
      next($this->player_list);
      return $player_item;
    } else {
      $this->resetPlayers();
      return false;
    }
  }

  function resetPlayers() {
    if (is_array($this->player_list)) {
      reset($this->player_list);
    }
  }

  function addPlayer($player) {
    if (get_class($player) == 'Player' && $player->login != '') {
      $this->player_list[$player->login] = $player;
      return true;
    } else {
      return false;
    }
  }

  function removePlayer($login) {
    if (isset($this->player_list[$login])) {
      $player = $this->player_list[$login];
      unset($this->player_list[$login]);
    } else {
      $player = false;
    }
    return $player;
  }

  function getPlayer($login) {
    if (isset($this->player_list[$login]))
      return $this->player_list[$login];
    else
      return false;
  }
}  // class PlayerList


/**
 * Can store map information.
 * You can instantiate with an RPC 'GetMapInfo' response.
 */
class Map {
  var $id;
  var $name;
  var $uid;
  var $filename;
  var $author;
  var $environment;
  var $mood;
  var $bronzetime;
  var $silvertime;
  var $goldtime;
  var $authortime;
  var $copperprice;
  var $laprace;
  var $forcedlaps;
  var $nblaps;
  var $nbchecks;
  var $score;
  var $starttime;
  var $maptype;
  var $mapstyle;
  var $titleuid;
  var $gbx;
  var $mx;
  var $authorNick;
  var $authorZone;
  var $authorEInfo;

  // instantiates the map with an RPC response
  function Map($rpc_infos = null) {
    $this->id = 0;
    if ($rpc_infos) {
      $this->name = stripNewlines($rpc_infos['Name']);
      $this->uid = $rpc_infos['UId'];
      $this->filename = $rpc_infos['FileName'];
      $this->author = $rpc_infos['Author'];
      $this->environment = $rpc_infos['Environnement'];
      $this->mood = $rpc_infos['Mood'];
      $this->copperprice = $rpc_infos['CopperPrice'];
      $this->laprace = $rpc_infos['LapRace'];
      $this->forcedlaps = 0;
      $this->nblaps = $rpc_infos['NbLaps'];
      $this->nbchecks = $rpc_infos['NbCheckpoints'];
      $this->maptype = $rpc_infos['MapType'];
      $this->mapstyle = $rpc_infos['MapStyle'];
    } else {
      // set defaults
      $this->name = 'undefined';
    }
	if ($this->bronzetime == -1){
	return "None";
	}
	if ($this->silvertime == -1){
	return "None";
	}
	if ($this->goldtime == -1){
	return "None";
	}
	if ($this->authortime == -1){
	return "None";
	}
	
  }
}  // class Map


/**
 * Contains information about an RPC call.
 */
class RPCCall {
  var $index;
  var $id;
  var $callback;
  var $call;

  // instantiates the RPC call with the parameters
  function RPCCall($id, $index, $callback, $call) {
    $this->id = $id;
    $this->index = $index;
    $this->callback = $callback;
    $this->call = $call;
  }
}  // class RPCCall


/**
 * Contains information about a chat command.
 * added Command Numbers by the MPAseco Team 
 */
class ChatCommand {
  var $name;
  var $help;
  var $isadmin;
  var $commandNr;
  private static $adminCommandCount = 0;
  private static $userCommandCount = 0;
  // instantiates the chat command with the parameters
  function ChatCommand($name, $help, $isadmin) {
    $this->name = $name;
    $this->help = $help;
    $this->isadmin = $isadmin;
    if($isadmin){
     // self::$adminCommandCount++;
      $this->commandNr = self::$adminCommandCount;
    }else{
      self::$userCommandCount++;  
      $this->commandNr = self::$userCommandCount;      
    } 
  }
  public function getAdminCommandCount(){
    return self::$adminCommandCount;
  }
  public function getUserCommandCount(){
    return self::$userCommandCount;
  }  
}  // class ChatCommand


/**
 * Stores basic information of the server MPAseco is running on.
 */
class Server {
  var $id;
  var $name;
  var $game;
  var $serverlogin;
  var $nickname;
  var $zone;
  var $ip;
  var $port;
  var $timeout;
  var $version;
  var $build;
  var $title;
  var $packmask;
  var $laddermin;
  var $laddermax;
  var $login;
  var $pass;
  var $maxplay;
  var $maxspec;
  var $map;
  var $records;
  var $players;
  var $mutelist;
  var $gameinfo;
  var $gamestate;
  var $gamedir;
  var $mapdir;
  var $votetime;
  var $voterate;
  var $uptime;
  var $starttime;
  var $isrelay;
  var $relaymaster;
  var $relayslist;

  // game states
  const RACE  = 'race';
  const SCORE = 'score';
  
  function getGame() {
    switch ($this->game) {
      case 'ManiaPlanet':
        return 'MP';
      default:  // SM/QM is supported in MPAseco
        return 'Unknown';
    }
  }

  // instantiates the server with default parameters
  function Server($ip, $port, $login, $pass) {
    $this->ip = $ip;
    $this->port = $port;
    $this->login = $login;
    $this->pass = $pass;
    $this->starttime = time();
  }
}  // class Server

/**
 * Contains information to the current game which is played.
 */
class Gameinfo {
  var $mode;
  var $type;
  var $numchall;
  var $rndslimit;
  var $timelimit;
  var $teamlimit;
  var $lapslimit;
  var $cuplimit;
  var $forcedlaps;
  var $scriptname;

  const SCPT = 0;
  const RNDS = 1;
  const TA   = 2;
  const TEAM = 3;
  const LAPS = 4;
  const CUP  = 5;
  const STNT = 6;
  

  // returns current game mode as string
  function getMode() {
    switch ($this->mode) {
      case self::SCPT:
        return 'Script';
      case self::RNDS:
        return 'Rounds';
      case self::TA:
        return 'TimeAttack';
      case self::TEAM:
        return 'Team';
      case self::LAPS:
        return 'Laps';
      case self::CUP:
        return 'Cup';
      case self::STNT:
        return 'Stunts';
      default:
        return 'Undefined';
    }
  }

  function getType() {
    return $this->type;
  }

  // instantiates the game info with an RPC response
  function Gameinfo($rpc_infos = null) {
    if ($rpc_infos) {
      $this->mode = $rpc_infos['GameMode'];
      if($this->mode == 0) {
        $this->type = $rpc_infos['ScriptName'];
        
        // XXX: Temporary fix
        if($this->type == '<in-development>') {
          global $aseco;
          
          if(isset($aseco->server->map->gbx)) {
            $this->type = $aseco->server->map->gbx->maptype;
          } else {
            $aseco->client->query('GetCurrentMapInfo', array());
            $challenge = $aseco->client->getResponse();
            $map = new map($challenge);
            $map->gbx = new GBXChallengeFetcher($aseco->server->mapdir . $map->filename, true);
            
            $this->type = $map->gbx->maptype;
          }
        }
        
        $this->scriptname = str_ireplace('shootmania\\', '', $this->type);  
        $this->scriptname = str_replace('Arena', '', $this->scriptname);
        $this->type = str_replace('.Script.txt', '', $this->scriptname);
      }
  /*    $this->numchall = $rpc_infos['NbChallenge'];
      if ($rpc_infos['RoundsUseNewRules'])
        $this->rndslimit = $rpc_infos['RoundsPointsLimitNewRules'];
      else
        $this->rndslimit = $rpc_infos['RoundsPointsLimit'];
      $this->timelimit = $rpc_infos['TimeAttackLimit'];
      if ($rpc_infos['TeamUseNewRules'])
        $this->teamlimit = $rpc_infos['TeamPointsLimitNewRules'];
      else
        $this->teamlimit = $rpc_infos['TeamPointsLimit'];
      $this->lapslimit = $rpc_infos['LapsTimeLimit'];
      $this->cuplimit = $rpc_infos['CupPointsLimit'];
      $this->forcedlaps = $rpc_infos['RoundsForcedLaps']; */
    } else {
      $this->mode = -1;
    }
  }
}  // class Gameinfo

/**
 * Contains information about MPAseco Plugin.
 */
class Plugin{
  var $author, $version, $description, $Aseco, $dependencies;
  function setAuthor($auth){
    $this->author = $auth;
  }
  function setVersion($version){
    $this->version = $version;
  }
  function setDescription($description){
    $this->description = $description;
  }
  function addDependence($plugin_name, $id_variable){
    if (!$this->dependencies) $this->dependencies = array();
    $this->dependencies[$id_variable] = $plugin_name;
  }
  function checkDependencies(){
    if (!$this->dependencies) return;
    foreach ($this->dependencies as $id_variable => $plugin_name) {
      $checkFor = null;
      eval('global $'.$id_variable.'; $checkFor = $'.$id_variable.';');
      if (!$checkFor){
        $this->Aseco->console('['.get_class($this).'] Unmet Dependency! With your current configuration you need to activate "'.$plugin_name.'" to run this plugin!');
        die();
      }
    }
  }
} //class Plugin
  
?>