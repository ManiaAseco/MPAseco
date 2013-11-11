<?php
/**
 * Royal linear Mode
 * Changes the Royal Poitlimit automatically 
 * Dependencies: none
 */
	
class linearmode extends Plugin {
  private $multiplier;
  private $offset;
  private $min_value;
  private $max_value;
  private $enabled;
  private $nextround;

  private $nextlimit;
  

  function init(){
    $this->pluginVersion = '0.02';	
    $this->pluginAuthor = 'Lukas Kremsmayr';
    $this->pluginMainId = '99958';
  }
  
	function mpasecoStartup(){
/*	$this->Aseco->client->query('GetModeScriptInfo');
	$ScriptInfo = $this->Aseco->client->getResponse();
	$TitleId = $ScriptInfo['Name'];
	if($TitleId == "Royal.Script.txt"){   */
   $conf_file     = 'configs/plugins/linearmode.xml'; 
    if (file_exists($conf_file)) { 
     $this->Aseco->console('Load linearmode config file [' . $conf_file . ']');
  	 if ($xml = $this->Aseco->xml_parser->parseXml($conf_file)) {
  	    /* Xml Settings_ */
        $this->multiplier  = $xml['LINEARMODE_SETTINGS']['FACTOR'][0];
        $this->offset      = $xml['LINEARMODE_SETTINGS']['OFFSET'][0];
        $this->min_value   = $xml['LINEARMODE_SETTINGS']['MIN_VALUE'][0];
        $this->max_value   = $xml['LINEARMODE_SETTINGS']['MAX_VALUE'][0];
        $this->nextround   = $xml['LINEARMODE_SETTINGS']['NEXT_ROUND'][0];
        $this->enabled     = true;
    } else {
    trigger_error('Could not read/parse linearmode config file ' . $conf_file . ' !', E_USER_WARNING);
    }
   } else {
    trigger_error('Could not find linearmode config file ' . $conf_file . ' !', E_USER_WARNING);
   }
   $this->nextlimit = -1;
/*	}
	else{
	trigger_error('["plugin.linearmode"] Will not work on this Script! Please use Royal', E_USER_WARNING);
	}   */
	} 

  function changePointlimit($force = false) {
    if(!$this->enabled) return;

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

    if ($this->nextround && $force == false) {
      $this->nextlimit = $pointlimit;
      //$this->Aseco->console("POINTLIMIT SAVE, CHANGE NEXT ROUND"); // debug
    }else{
      if($force) $this->nextlimit = -1;
      $scriptset = array('S_MapPointsLimit' => $pointlimit);
      
      $this->Aseco->client->query('SetModeScriptSettings',  $scriptset);
    }
  }  


  function beginRound() {
    if ($this->nextround && $this->nextlimit <> -1){
      $scriptset = array('S_MapPointsLimit' => $this->nextlimit);
      
      $this->Aseco->client->query('SetModeScriptSettings',  $scriptset);

      $this->nextlimit = -1;
      //$this->Aseco->console("POINTLIMIT CHANGED"); // debug
    }
  }


  function openSettings($login) {
    if($this->hasPermissions($login)){
      $xml  = '<?xml version="1.0" encoding="UTF-8"?>';                                       
      $xml .= '<manialinks>';
      $xml .= '  <manialink id=1>';

      $xml .= '<frame pos="0.71 0.53 -0.6">
              <quad size="1.42 0.92" style="BgsPlayerCard" substyle="BgCard"/>
              <quad pos="-0.71 -0.01 -0.1" size="1.4 0.07" halign="center" style="Bgs1InRace" substyle="BgCardList"/>
              <quad pos="-0.055 -0.045 -0.3" size="0.09 0.09" halign="center" valign="center" style="Icons128x128_1" substyle="ProfileAdvanced"/>
              <label pos="-0.10 -0.025 -0.2" size="1.17 0.07" halign="left" style="TextValueMedium" text="Linearmode Settings:"/>
              <quad pos="-0.71 -0.09 -0.1" size="1.4 0.755" halign="center" style="BgsPlayerCard" substyle="BgCard"/>
              <format style="TextCardSmallScores2"/>';

      $px = -0.025; // left
      $py = -0.120; // top

      $xml .= '<label pos="'.$px.' -0.102 -0.2" size="0.75 0.06" halign="left" style="TextCardSmallScores2" text="Name"/>
             <label pos="'.($px-0.72).' -0.102 -0.2" size="0.75 0.06" halign="left" style="TextCardSmallScores2" text="Values"/>';

      $xml .= '<label pos="'.$px.' '.($py - 0.04).' -0.14" size="0.75 0.06" halign="left" style="TextCardSmallScores2" text="Turn linearmode on/off"/>';
      if($this->enabled)
        $xml .= '<quad pos="'.($px-0.75).' '.($py - 0.04).' -0.14" size="0.03 0.03" halign="center" style="Icons64x64_1" substyle="LvlGreen" action="'.$this->pluginMainId.'991"/>';   
      else
        $xml .= '<quad pos="'.($px-0.75).' '.($py - 0.04).' -0.14" size="0.03 0.03" halign="center" style="Icons64x64_1" substyle="LvlRed" action="'.$this->pluginMainId.'991"/>';

      $xml .= '<label pos="'.$px.' '.($py - 0.12).' -0.14" size="0.75 0.06" halign="left" style="TextCardSmallScores2" text="Offset"/>'; 
      $xml .= '<entry pos="'.($px-0.75).' '.($py - 0.12).' -0.14" sizen="10 2" style="TextValueSmall" halign="center"  focusareacolor1="555A" substyle="BgCard" name="offset" default="'.intval($this->offset).'"/>';

      $xml .= '<label pos="'.$px.' '.($py - 0.16).' -0.14" size="0.75 0.06" halign="left" style="TextCardSmallScores2" text="Player multiplier"/>'; 
      $xml .= '<entry pos="'.($px-0.75).' '.($py - 0.16).' -0.14" sizen="10 2" style="TextValueSmall" halign="center"  focusareacolor1="555A" substyle="BgCard" name="multiplier" default="'.intval($this->multiplier).'"/>';

      $xml .= '<label pos="'.$px.' '.($py - 0.20).' -0.14" size="0.75 0.06" halign="left" style="TextCardSmallScores2" text="Minimal Pointlimit"/>'; 
      $xml .= '<entry pos="'.($px-0.75).' '.($py - 0.20).' -0.14" sizen="10 2" style="TextValueSmall" halign="center"  focusareacolor1="555A" substyle="BgCard" name="min_value" default="'.intval($this->min_value).'"/>';

      $xml .= '<label pos="'.$px.' '.($py - 0.24).' -0.14" size="0.75 0.06" halign="left" style="TextCardSmallScores2" text="Maximal Pointlimit"/>'; 
      $xml .= '<entry pos="'.($px-0.75).' '.($py - 0.24).' -0.14" sizen="10 2" style="TextValueSmall" halign="center"  focusareacolor1="555A" substyle="BgCard" name="max_value" default="'.intval($this->max_value).'"/>';

      $xml .= '<label pos="'.$px.' '.($py - 0.28).' -0.14" size="0.75 0.06" halign="left" style="TextCardSmallScores2" text="Change pointlimit after round"/>';
      if($this->nextround)
        $xml .= '<quad pos="'.($px-0.75).' '.($py - 0.28).' -0.14" size="0.03 0.03" halign="center" style="Icons64x64_1" substyle="LvlGreen" action="'.$this->pluginMainId.'992"/>';   
      else
        $xml .= '<quad pos="'.($px-0.75).' '.($py - 0.28).' -0.14" size="0.03 0.03" halign="center" style="Icons64x64_1" substyle="LvlRed" action="'.$this->pluginMainId.'992"/>';

      $xml .= '<label pos="'.$px.' -0.800 -0.14" size="0.75 0.06" halign="left" style="TextCardSmallScores2" text="Settings will not be writen to the config file!"/>';

      $xml .= '<label pos="-1.281 -0.844 -0.2" halign="center" style="CardButtonMedium" text="Apply" action="'.$this->pluginMainId.'999"/>';   //Apply
            
      $xml .=   '<quad pos="-0.71 -0.84 -0.2" size="0.08 0.08" halign="center" style="Icons64x64_1" substyle="Close" action="0"/>'; //Close Button 
      $xml .= '</frame>';  


      $xml .= '  </manialink>';  
      $xml .= getCustomUIBlock();
      $xml .= '</manialinks>';  

      $this->Aseco->client->query('TriggerModeScriptEvent', 'LibXmlRpc_DisableAltMenu', $login);                                                                     
      $this->Aseco->client->query('SendDisplayManialinkPageToLogin', $login, $xml, (0 * 1000), false);   
    }

  }

  function handleClick($command){
    $action = $command[2].'';
    if (substr($action, 0, strlen($this->pluginMainId)) == $this->pluginMainId){

      $action = substr($action, strlen($this->pluginMainId));
      $action = intval(substr($action, 0, 3));
     
      switch($action){
        case 999:
          $this->setSettings($command[1],$command[3]);
          break;
        case 991:
          $this->setCheckboxSetting($command[1], $action);
          $this->openSettings($command[1]);
          break;
        case 992:
          $this->setCheckboxSetting($command[1], $action);
          $this->openSettings($command[1]);
          break;
      }
    }
  }


  function setSettings($login, $settings){
    $admin = $this->Aseco->server->players->getPlayer($login);
    $logtitle  = $this->getLogTitle($admin);
    $chattitle = $this->Aseco->titles[strtoupper($logtitle)][0];

    foreach($settings as $setting){
      if($setting['Name'] == 'offset')      $this->offset = $setting['Value'];
      if($setting['Name'] == 'multiplier')  $this->multiplier = $setting['Value'];
      if($setting['Name'] == 'min_value')   $this->min_value = $setting['Value'];
      if($setting['Name'] == 'max_value')   $this->max_value = $setting['Value'];
    }
    $this->changePointlimit(true);
    $message = '{#server}> {#admin}Linearmode settings applied!';
    $this->Aseco->client->query('ChatSendServerMessageToLogin', $this->Aseco->formatColors($message), $login);   
  }

  function setCheckboxSetting($login, $action){
    $admin = $this->Aseco->server->players->getPlayer($login);
    $logtitle  = $this->getLogTitle($admin);
    $chattitle = $this->Aseco->titles[strtoupper($logtitle)][0];

    if($action == 991){
      if($this->enabled == false){
        $this->enabled = true;
        $message = formatText('{#server}>> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} enabled Linearmode!', $chattitle, $admin->nickname);
      }else{
        $this->enabled = false;
        $message = formatText('{#server}>> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} disabled Linearmode!', $chattitle, $admin->nickname);
      }
    }elseif($action == 992){
      if($this->nextround == false){
        $this->nextround = true;
        $message = formatText('{#server}>> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} set Linearmode setting \'Change pointlimit after round\' to \'true\'!', $chattitle, $admin->nickname);
      }else{
        $this->nextround = false;
        $message = formatText('{#server}>> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} set Linearmode setting \'Change pointlimit after round\' to \'false\'!', $chattitle, $admin->nickname);
      }
    }
    
    $this->changePointlimit();
    $this->Aseco->client->query('ChatSendServerMessage', $this->Aseco->formatColors($message));  
  }

  private function hasPermissions($login){
    $admin = $this->Aseco->server->players->getPlayer($login);
    if($this->Aseco->isMasterAdmin($admin) || 
       $this->Aseco->isAdmin($admin) && $this->Aseco->allowAdminAbility("linearmode") || 
       $this->Aseco->isOperator($admin) && $this->Aseco->allowOpAbility("linearmode"))
      return true;
    else
      return false;
  }

  private function getLogTitle($admin){
    if ($this->Aseco->isMasterAdmin($admin)) {
      $logtitle = 'MasterAdmin';
    } else if ($this->Aseco->isAdmin($admin)){
        $logtitle = 'Admin';
    } else if ($this->Aseco->isOperator($admin) /* && $aseco->allowOpAbility($command['params'][0] )*/) {
        $logtitle = 'Operator';
    }  
    return $logtitle;
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
Aseco::registerEvent('onBeginRound','linearmode_beginRound');
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'linearmode_handleClick');

/* ChatCommand */
Aseco::addChatCommand('linearmode', 'Changes the linearmode settings', true);

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

function linearmode_beginRound($aseco){
  global $linearmode;
  $linearmode->beginRound();
}

function linearmode_handleClick($aseco, $answer){
  global $linearmode;
  $linearmode->handleClick($answer);
}

/* ChatCommands: */
/*function chat_linearmode($aseco, $command){
  // This is not a normal call. 
  global $linearmode;
  $admin = $command['author'];
  $linearmode->openSettings($admin->login);
}*/
	
?>