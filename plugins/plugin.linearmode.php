<?php
/**
 * Royal linear Mode
 * Changes the Royal Poitlimit automatically 
 * Dependencies: none
 */


//Aseco::addChatCommand('linearmode', 'Changes the linearmode settings', true);
//Todo: chatcommand

	
class linearmode extends Plugin {
  private $multiplier;
  private $offset;
  private $min_value;
  private $max_value;
  
  function init(){
    $this->pluginVersion = '0.01';	
    $this->pluginAuthor = 'Lukas Kremsmayr';
  }
  
	function mpasecoStartup(){
	$this->Aseco->client->query('GetModeScriptInfo');
	$ScriptInfo = $this->Aseco->client->getResponse();
	$TitleId = $ScriptInfo['Name'];
	if($TitleId == "Royal.Script.txt"){
   $conf_file     = 'configs/plugins/linearmode.xml'; 
    if (file_exists($conf_file)) { 
     $this->Aseco->console('Load linearmode config file [' . $conf_file . ']');
  	 if ($xml = $this->Aseco->xml_parser->parseXml($conf_file)) {
  	    /* Xml Settings_ */
        $this->multiplier  = $xml['LINEARMODE_SETTINGS']['FACTOR'][0];
        $this->offset      = $xml['LINEARMODE_SETTINGS']['OFFSET'][0];
        $this->min_value   = $xml['LINEARMODE_SETTINGS']['MIN_VALUE'][0];
        $this->max_value   = $xml['LINEARMODE_SETTINGS']['MAX_VALUE'][0];
    } else {
    trigger_error('Could not read/parse linearmode config file ' . $conf_file . ' !', E_USER_WARNING);
    }
   } else {
    trigger_error('Could not find jfreu linearmode config file ' . $conf_file . ' !', E_USER_WARNING);
   }
	}
	else{
	trigger_error('["plugin.linearmode"] Will not work on this Script! Please use Royal', E_USER_WARNING);
	}
	}

  function changePointlimit() {
    /* Playercount: */
    $CurrentPlayerCount = count($this->Aseco->server->players->player_list);
    /* Spectatorcount: */  
    $CurrentSpectatorCount = 0;
    foreach ($this->Aseco->server->players->player_list as &$player) {
      if ($player->isspectator == 1) 
      	$CurrentSpectatorCount++;
    }
    unset($player);
    $playercount = $CurrentPlayerCount - $CurrentSpectatorCount;
      
    $pointlimit=($playercount * $this->multiplier) + $this->offset;
    if($pointlimit < $this->min_value)     
      $pointlimit = $this->min_value;        //Min Value
    if($pointlimit > $this->max_value)
      $pointlimit = $this->max_value;       //Max Value
                  
    $scriptset = array('S_MapPointsLimit' => $pointlimit);   
      
    $this->Aseco->client->query('SetModeScriptSettings',  $scriptset);         
  }  
}

global $linearmode;
$linearmode = new linearmode();
$linearmode->init();
$linearmode->setAuthor($linearmode->pluginAuthor); 
$linearmode->setVersion($linearmode->pluginVersion);
$linearmode->setDescription('Changes automatically the Pointlimit in Royal Mode.');
	
/* Register the used Events */
Aseco::registerEvent('onStartup', 'linearmode_mpasecoStartup');  
Aseco::registerEvent('onPlayerConnect', 'linearmode_changePointlimit');
Aseco::registerEvent('onPlayerDisconnect', 'linearmode_changePointlimit');

/* Events: */  
function linearmode_mpasecoStartup($aseco){
	global $linearmode;
	if (!$linearmode->Aseco){
			$linearmode->Aseco = $aseco;
	}
	$linearmode->mpasecoStartup();
}

function linearmode_changePointlimit($aseco){
	global $linearmode;
	$linearmode->changePointlimit();
}
	
?>