<?php
/**
 *   This Plugin handles the Scriptsetttings graphically.
 *   Version: v0.3
 *   Author: Lukas Kremsmayr
 *   Dependencies: none 
 */

class ScriptSettings extends Plugin {
  private $manialinksID, $showWidgetID;
  private $defaultBeginMap;
  public $pluginVersion, $pluginAuthor; 
  public $Aseco, $settingsFile;
  /**
   * Initializes the plugin, loads the XML settings
   */
  public function init(){
    $this->pluginMainId = "99957";
    $this->showWidgetID = "1"; //mainwindow
    
    $this->pluginVersion = '0.3';  
    $this->pluginAuthor = 'Lukas Kremsmayr';
    $this->defaultBeginMap = false;
  }

  /**
   * Handles mouse clicks on the widgets
   *
   * @param mixed $command
   * $command[1] = login
   * $recipent == "Manialink addition"     
   */
  public function doHandleClick($command){
    $action = $command[2].'';
    if (substr($action, 0, strlen($this->pluginMainId)) == $this->pluginMainId){

      $action = substr($action, strlen($this->pluginMainId));
      $recipient = intval(substr($action, 0, 3));
      $action = substr($action, 3);
      
      if ($recipient==995){ //OnBeginMap
        $this->toggleDefaultOnBeginMap($command[1]);
      }else if ($recipient==996){ //Save Default Settings
        $this->saveDefaultSettings($command[1]);
      }else if ($recipient==997){  //Load Default Settings
        $this->loadDefaultSettings($command[1]);  
      }else if ($recipient==998){  //Show Plugin
        $this->showPlugin($command[1]);
      }else if ($recipient==999){  //Apply Values
        $this->setScriptSettings($command[1], $command[3]);
      } else {  //Checkboxes
        $this->setScriptSettings($command[1], $command[3]); 
        $this->setCheckboxSetting($command[1], $recipient); 
      }           

    }
  } //onManiaPlayerPageAnswers

  private function toggleDefaultOnBeginMap($login){
    $admin = $this->Aseco->server->players->getPlayer($login);
    $logtitle  = $this->getLogTitle($admin);
            
    if($this->defaultBeginMap == true)
      $this->defaultBeginMap = false;
    else
      $this->defaultBeginMap = true;
  
    $this->showPlugin($login);   
    $message = formatText('{#server}>> {#admin}Loading default settings on map begin: {#highlite}{1}{#admin}!', bool2text($this->defaultBeginMap));
    $this->Aseco->client->query('ChatSendServerMessageToLogin', $this->Aseco->formatColors($message), $login);   
    $this->Aseco->console('{1} [{2}] loading default settings on map begin: {3}!', $logtitle, $login, bool2text($this->defaultBeginMap));   
  }
  
  public function beginMap(){
    if($this->defaultBeginMap == true)
      $this->loadDefaultSettings();
  }

  private function loadDefaultSettings($login = false){
    if (file_exists($this->settingsFile)) {
      if($login){
        $admin = $this->Aseco->server->players->getPlayer($login);
        $logtitle  = $this->getLogTitle($admin);
        $chattitle = $this->Aseco->titles[strtoupper($logtitle)][0];
      }
      
      $this->Aseco->console('[Admin] Load default scriptsetting file['.$this->settingsFile.']');
      if (!$settings = $this->Aseco->xml_parser->parseXml($this->settingsFile)) {
        trigger_error('Could not read/parse default scriptsetting file '.$this->settingsFile.' !', E_USER_ERROR);
      }

      $this->Aseco->client->query('GetModeScriptSettings');
      $scriptSettings = $this->Aseco->client->getResponse();
      
      foreach($scriptSettings as $key => $value){
         $type = gettype($value);
         $newvalue = $settings['SCRIPTSETTINGS'][strtoupper($key)][0];
         if($type == "boolean")
          $newvalue = text2bool($newvalue);
         settype($newvalue, $type);
         $scriptSettings[$key] = $newvalue;
      }
      $this->Aseco->client->query('SetModeScriptSettings', $scriptSettings);
      var_dump($scriptSettings);
      if($login){
        $this->showPlugin($login); 
        
        $message = formatText('{#server}>> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} loaded default Scriptsettings!', $chattitle, $admin->nickname);
        $this->Aseco->client->query('ChatSendServerMessage', $this->Aseco->formatColors($message));                             
        $this->Aseco->console('{1} [{2}] loaded default Scriptsettings!', $logtitle, $login);      
     }  
    } else {
      trigger_error('Could not find default scriptsetting file ' . $this->settingsFile . ' !', E_USER_WARNING);
    }
  }
  
  
  private function saveDefaultSettings($login){
    if(!is_dir("configs/scriptsettings/")){
      mkdir("configs/scriptsettings/");
    }
    $handle = fopen($this->settingsFile,"w");
    if($handle){
      $admin = $this->Aseco->server->players->getPlayer($login);
      $logtitle  = $this->getLogTitle($admin);
 
      $this->Aseco->client->query('GetModeScriptSettings');
      $settings = $this->Aseco->client->getResponse();
      
      $dom = new DOMDocument('1.0', 'utf-8');
      $dom->formatOutput = true;
      $root = $dom->createElement('scriptsettings');
      $dom->appendChild($root);
      
      foreach($settings as $key => $value){
        if(is_bool($value))
          $value = bool2text($value);
        $root->appendChild($firstNode = $dom->createElement($key, $value));
      }
      fwrite($handle,$dom->saveXML());
  
      $message = formatText('{#server}>> {#admin}Default Scripsettings saved successfully!');
      $this->Aseco->client->query('ChatSendServerMessageToLogin', $this->Aseco->formatColors($message), $login);                         
      $this->Aseco->console('{1} [{2}] saved default Scriptsettings!', $logtitle, $login);   
      $this->showPlugin($login); 
    }
    fclose($handle);
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
  
  private function setScriptSettings($login, $settings){
    $admin = $this->Aseco->server->players->getPlayer($login);
    $logtitle  = $this->getLogTitle($admin);
    $chattitle = $this->Aseco->titles[strtoupper($logtitle)][0];

    $this->Aseco->client->query('GetModeScriptSettings');
    $oldSettings = $this->Aseco->client->getResponse();

    $msg = '';
    $logmsg = '';
    $scriptSettings = array();
    foreach($settings as $sett){
      foreach($oldSettings as $okey => $ovalue){ //Build Messages
         if($okey == $sett["Name"]){
           if($ovalue != $sett["Value"]){
            $msg .= '{#highlite}'.$sett["Name"].' $z$s{#admin}to {#highlite}'.$sett["Value"].', ';
            $logmsg .= $sett["Name"].' to '.$sett["Value"].', ';                
           }
           settype($sett["Value"],gettype($ovalue)); //TypeCasts
           break;
         }
      }     
      $scriptSettings[$sett["Name"]] = $sett["Value"]; 
    }

    $this->Aseco->client->query('SetModeScriptSettings', $scriptSettings);
    $this->showPlugin($login);  
    
    $msg = substr($msg, 0, strlen($msg)-2);
    $msg = str_replace("S_","",$msg);  
    $logmsg = substr($logmsg, 0, strlen($logmsg)-2);
    $logmsg = str_replace("S_","",$logmsg);  
    if($msg != ''){
      $message = formatText('{#server}>> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} set Scriptsettings {3}$z$s{#admin}!',
      $chattitle, $admin->nickname,$msg);
      $this->Aseco->client->query('ChatSendServerMessage', $this->Aseco->formatColors($message));   
      $this->Aseco->console('{1} [{2}] set Scriptsettings "{3}"!', $logtitle, $login, $logmsg);     
    }
 
                  
  }

  private function setCheckboxSetting($login, $recipient){
    $admin = $this->Aseco->server->players->getPlayer($login);
    $logtitle  = $this->getLogTitle($admin);
    $chattitle = $this->Aseco->titles[strtoupper($logtitle)][0];

     
    $this->Aseco->client->query('GetModeScriptSettings');
    $settings = $this->Aseco->client->getResponse();
   
    $scriptSettings = array();
    $i = 0;
    foreach($settings as $key => $value){
      if($recipient == $i){
        $newVal = $value == true ? false : true;  //toggle setting
        $scriptSettings[$key] = $newVal; 
        break;
      }  
      $i++;
    }
    $this->Aseco->client->query('SetModeScriptSettings', $scriptSettings);
    $this->showPlugin($login);  
   
    //Chat command    
    $message = formatText('{#server}>> {#admin}{1}$z$s {#highlite}{2}$z$s{#admin} set Scriptsettings {#highlite}{3} $z$s{#admin}to {#highlite}{4}$z$s{#admin}!',
    $chattitle, $admin->nickname,str_replace("S_","",$key), bool2text($newVal));
    $this->Aseco->client->query('ChatSendServerMessage', $this->Aseco->formatColors($message));                             
    $this->Aseco->console('{1} [{2}] set Scriptsettings "{3}" to {4}!', $logtitle, $login, $key, bool2text($newVal));   
  }
  
  public function showPlugin($login){  
                                             
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>';                                       
    $xml .= '<manialinks>';
    $xml .= '  <manialink id='.$this->showWidgetID.'>';
    $xml .= $this->maniaLink($login);
    $xml .= '  </manialink>';  
    $xml .= getCustomUIBlock();
    $xml .= '</manialinks>';   
                         
    $close = false;
    $timeout = 0;
   
    $this->Aseco->client->query('TriggerModeScriptEvent', 'disableAltMenu', $login);                                                                     
    $this->Aseco->client->query('SendDisplayManialinkPageToLogin', $login, $xml, ($timeout * 1000), $close);   
      

  }  

  private function hasPermissions($login){
    $admin = $this->Aseco->server->players->getPlayer($login);
    if($this->Aseco->isMasterAdmin($admin) || 
       $this->Aseco->isAdmin($admin) && $this->Aseco->allowAdminAbility("scriptsettings") || 
       $this->Aseco->isOperator($admin) && $this->Aseco->allowOpAbility("scriptsettings"))
      return true;
    else
      return false;
  }
  
  private function maniaLink($login){
    $this->settingsFile = 'configs/scriptsettings/'.$this->Aseco->server->gameinfo->type.'.xml';
    
    $this->Aseco->client->query('GetModeScriptSettings');
    $scriptSettings = $this->Aseco->client->getResponse();
    $cnt = count($scriptSettings);
    $rowsPerColums = 13;
    $changePermission = $this->hasPermissions($login);
    
    $xml= '<frame pos="0.71 0.47 -0.6">
              <quad size="1.42 0.82" style="BgsPlayerCard" substyle="BgCard"/>
              <quad pos="-0.71 -0.01 -0.1" size="1.4 0.07" halign="center" style="Bgs1InRace" substyle="BgCardList"/>
              <quad pos="-0.055 -0.045 -0.3" size="0.09 0.09" halign="center" valign="center" style="Icons128x128_1" substyle="ProfileAdvanced"/>
              <label pos="-0.10 -0.025 -0.2" size="1.17 0.07" halign="left" style="TextValueMedium" text="Scriptsettings:"/>
              <quad pos="-0.71 -0.09 -0.1" size="1.4 0.655" halign="center" style="BgsPlayerCard" substyle="BgCard"/>
              <format style="TextCardSmallScores2"/>
              <label pos="-0.065 -0.102 -0.2" size="0.75 0.06" halign="left" style="TextCardSmallScores2" text="Name"/>
              <label pos="-0.425 -0.102 -0.2" size="0.75 0.06" halign="left" style="TextCardSmallScores2" text="Values"/>';
    if($cnt > $rowsPerColums){
      $xml .=  '<label pos="-0.865 -0.102 -0.2" size="0.75 0.06" halign="left" style="TextCardSmallScores2" text="Name"/>
                <label pos="-1.225 -0.102 -0.2" size="0.75 0.06" halign="left" style="TextCardSmallScores2" text="Values"/>';
    }
    $i = 0;
    foreach($scriptSettings as $key => $value) {
      $substyle = 0;
      if($value === false){
       $value = 'false';
       $substyle ='LvlRed';
      }
      if($value === true){
       $value = 'true';
       $substyle ='LvlGreen';
      }
      
      if($i <= $rowsPerColums){
        $px = -0.025;
        $py = (-0.162 - 0.04 * $i);
      }else{
        $px = -0.825;
        $py = (-0.162 - 0.04 * ($i - $rowsPerColums - 1));        
      }
      
      $xml .= '<label pos="'.$px.' '.$py.' -0.14" size="0.75 0.06" halign="left" style="TextCardSmallScores2" text="'.$key.'"/>'; 
      
      if($changePermission){  
        if($substyle)
          $xml .= '<quad pos="'.($px-0.426).' '.$py.' -0.14" size="0.03 0.03" halign="center" style="Icons64x64_1" substyle="'.$substyle.'" action="'.$this->pluginMainId.$i.'"/>';   
        else
          $xml .= '<entry pos="'.($px-0.43).' '.$py.' -0.14" sizen="10 2" style="TextValueSmall" halign="center"  focusareacolor1="555A" substyle="BgCard" name="'.$key.'" default="'.$value.'"/>';
      }else{
        if($substyle)
          $xml .= '<quad pos="'.($px-0.426).' '.$py.' -0.14" size="0.03 0.03" halign="center" style="Icons64x64_1" substyle="'.$substyle.'"/>';   
        else
          $xml .= '<label pos="'.($px-0.43).' '.$py.' -0.14" sizen="10 2" style="TextValueSmall" halign="center"  focusareacolor1="555A" substyle="BgCard" text="'.$value.'"/>';
      }
      
      $i++;   

    }                                                       
    
    $xml .= '<label pos="-0.55 -0.75 -0.2" sizen="11 1.5" style="TextValueSmall" halign="center"  focusareacolor1="555A" substyle="BgCard" text="Load default Settings"/>';
    $xml .= '<label pos="-0.55 -0.78 -0.2" sizen="11 1.5" style="TextValueSmall" halign="center"  focusareacolor1="555A" substyle="BgCard" text="on map begin"/>';
    
    if($this->defaultBeginMap == true)
      $xml .= '<quad pos="-0.66 -0.765 -0.14" size="0.03 0.03" halign="center" style="Icons64x64_1" substyle="LvlGreen" action="'.$this->pluginMainId.'995"/>';   
    else
      $xml .= '<quad pos="-0.66 -0.765 -0.14" size="0.03 0.03" halign="center" style="Icons64x64_1" substyle="LvlRed" action="'.$this->pluginMainId.'995"/>';
          
    $xml.=   '<quad pos="-0.71 -0.74 -0.2" size="0.08 0.08" halign="center" style="Icons64x64_1" substyle="Close" action="0"/>'; //Close Button
    if($changePermission){ 
      $xml.= '<label pos="-0.12 -0.744 -0.2" halign="center" style="CardButtonMedium" text="Save Default Settings" action="'.$this->pluginMainId.'996"/>'; //Save
      if(file_exists($this->settingsFile))    
        $xml.= '<label pos="-0.35 -0.744 -0.2" halign="center" style="CardButtonMedium" text="Load Default Settings" action="'.$this->pluginMainId.'997"/>'; //Load
      $xml.= '<label pos="-1.281 -0.744 -0.2" halign="center" style="CardButtonMedium" text="Apply Values" action="'.$this->pluginMainId.'999"/>';   //Apply
    }
    $xml.='</frame>';  
    return $xml; 
  }   
}
      
   
global $scriptSettings;
$scriptSettings = new ScriptSettings();
$scriptSettings->init();
$scriptSettings->setAuthor($scriptSettings->pluginAuthor); 
$scriptSettings->setVersion($scriptSettings->pluginVersion);
$scriptSettings->setDescription('Manages Scriptsettings');


/* Register the used Events */
Aseco::registerEvent('onStartup', 'scriptSettings_mpasecoStartup');  
Aseco::registerEvent('onPlayerManialinkPageAnswer', 'scriptSettings_handleClick');
Aseco::registerEvent('onBeginMap', 'scriptSettings_beginMap');

/* Events: */ 
function scriptSettings_show($login){
  global $scriptSettings;
  $scriptSettings->showPlugin($login);
}   

function scriptSettings_beginMap(){
  global $scriptSettings;
  $scriptSettings->beginMap();
}   
 
function scriptSettings_mpasecoStartup($aseco){
  global $scriptSettings;
  if (!$scriptSettings->Aseco){
    $scriptSettings->Aseco = $aseco;
  }
}     

function scriptSettings_handleClick($scriptSettings, $command){
   global $scriptSettings;
   $scriptSettings->doHandleClick($command);
}   //onPlayerManialinkPageAnswer

?>