<?php
/**
 *   Plugin Information    v1 test //TODO  Rights
 *   Plugin Version
 *   Plugin Author 
 */

class ScriptSettings extends Plugin {
  private $xy; //private variables
  private $manialinksID, $showWidgetID;
  public $pluginVersion, $pluginAuthor; 
  public $Aseco;
  /**
   * Initializes the plugin, loads the XML settings
   */
  public function init(){
    $this->pluginMainId = "99957";
    $this->showWidgetID = "1"; //mainwindow
    
    $this->pluginVersion = '0.01';  
    $this->pluginAuthor = 'Lukas Kremsmayr';
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
      
      if ($recipient==998){  //Show Plugin
        $this->showPlugin($command[1]);
      }else if ($recipient==999){  //Apply Values
        $this->setScriptSettings($command[1], $command[3]);
      } else {  //Checkboxes
        $this->setScriptSettings($command[1], $command[3]); 
        $this->setCheckboxSetting($command[1], $recipient); 
      }           

    }
  } //onManiaPlayerPageAnswers

  private function setScriptSettings($login, $settings){
    $admin = $this->Aseco->server->players->getPlayer($login);
    // check if chat command was allowed for a masteradmin/admin/operator
    if ($this->Aseco->isMasterAdmin($admin)) {
      $logtitle = 'MasterAdmin';
      $chattitle = $this->Aseco->titles['MASTERADMIN'][0];
    } else if ($this->Aseco->isAdmin($admin)){
        $logtitle = 'Admin';
        $chattitle = $this->Aseco->titles['ADMIN'][0];
    } else if ($this->Aseco->isOperator($admin) /* && $aseco->allowOpAbility($command['params'][0] )*/) {
        $logtitle = 'Operator';
        $chattitle = $this->Aseco->titles['OPERATOR'][0];
    }

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
    // check if chat command was allowed for a masteradmin/admin/operator
    if ($this->Aseco->isMasterAdmin($admin)) {
      $logtitle = 'MasterAdmin';
      $chattitle = $this->Aseco->titles['MASTERADMIN'][0];
    } else if ($this->Aseco->isAdmin($admin)){
        $logtitle = 'Admin';
        $chattitle = $this->Aseco->titles['ADMIN'][0];
    } else if ($this->Aseco->isOperator($admin)) {
        $logtitle = 'Operator';
        $chattitle = $this->Aseco->titles['OPERATOR'][0];
    }

     
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
    
    $xml.=   '<quad pos="-0.71 -0.74 -0.2" size="0.08 0.08" halign="center" style="Icons64x64_1" substyle="Close" action="0"/>';
    if($changePermission){ 
      $xml.= '<label pos="-1.281 -0.744 -0.2" halign="center" style="CardButtonMedium" text="Apply Values" action="'.$this->pluginMainId.'999"/>';
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

/* Events: */ 
function scriptSettings_show($login){
  global $scriptSettings;
  $scriptSettings->showPlugin($login);
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