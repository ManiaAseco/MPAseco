<?php
/*
*******************************************************************************
***** FufiWidgets by oorf | fuckfish, updated by ManiacTwister (MPASECO) *****
*******************************************************************************
**************************** http://fish.stabb.de *****************************
*******************************************************************************
* First of all thanks to the SATO developers Trabtown and Phil who had most of
* the ideas first and developed the visual style.
*
* This plugin displays multiple graphical widgets on your server.
* There are the Local Records widget, a Live Rankings widget, a Karma widget,
* a Challenge widget and a Clock widget.
*
*
*******************************************************************************
* INSTALLATION                                                                *
*******************************************************************************
* Important! If you don't follow the steps carefully, the plugin will not be
* working properly.
* 1. Unrar the archive into the XASECO2 folder
* 2. Configure some stuff if you like to (see configuration section)
* 3. Done! Have fun!
*
*
*******************************************************************************
* CONFIGURATION                                                               *
*******************************************************************************
* You can adjust the configuration in the pretty self explaining
* "fufi_widgets_config.xml".
* Some more specific settings on when to show which widget on which place with
* which size you can find in the files "./plugins/fufi/fufi_widgets_aseco.xml"
* and  "./plugins/fufi/fufi_widgets_aseco.xml" (be sure to edit the one which
* matches your system).
*
* You can also take a shot in pimping the graphical output by editing the
* other XML files in the "./plugins/fufi" folder.
*
*
*******************************************************************************
* The Challenge widget                                                        *
*******************************************************************************
* This one replaces the original challenge info in the upper right of the
* screen with something more fancy =) It adds the display of a logo, the
* author time and if you click it it will also show gold, silver and
* bronze time and a MX link (if you installed oliverde8's MX-Info plugin
* (http://www.tm-forum.com/viewtopic.php?f=28&t=14926))
*
*
*******************************************************************************
* The Local Records widget                                                    *
*******************************************************************************
* This one just displays the local records on your server in a defined area
* customized for every player individually.
*
*
*******************************************************************************
* The Live Rankings widget                                                    *
*******************************************************************************
* This widget displays the current scoreboard in the same style like the local
* records widget.
*
*
*******************************************************************************
* The Dedimania widget                                                        *
*******************************************************************************
* Like the local records widget, but with dedimania records (needs a running
* Dedimania plugin to work)
*
*
*******************************************************************************
* The Karma Widget                                                            *
*******************************************************************************
* This one displays the track karma graphically and provides buttons to karma
* vote via mouse click.
*
*******************************************************************************
* The Clock widget                                                            *
*******************************************************************************
* It's just a clock ;-)
*
*******************************************************************************
* The Ad widget                                                               *
*******************************************************************************
* Enables you to display advertisements in the HUD
*
*******************************************************************************
* The NextTrack widget                                                        *
*******************************************************************************
* Enables you to display some information about the next track, when the
* scoreboard is showing
*
*******************************************************************************
* The Scoreboard Lists widget                                                 *
*******************************************************************************
* Enables you to display some statistics about records and players, when the
* scoreboard is showing
*
*/

/**
 * The FufiWidgets Plugin
 * 
 * @author oorf | fuckfish
 * Updated by ManiacTwister
 */


if (!defined('IN_XASECO') || !defined('IN_MPASECO')){
  if (defined('XASECO2_VERSION')){
    define('IN_XASECO', true);
  } else if(defined('MPASECO_VERSION')){
    define('IN_XASECO', true);
    define('IN_MPASECO', true);
  } else {
    define('IN_XASECO', false);
  }
}

class CTUPlayer {
  var $login;
  var $nickname;
  function CTUPlayer($nickname, $login){
    $this->login = $login;
    $this->nickname = $nickname;
  }
}

if (IN_XASECO){
  //dummy plugin class for XAseco
  if (!class_exists('Plugin')){
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
    }
  }
}

class FufiWidgets extends Plugin {

  private $challengeWidgetID, $closeToYouWidgetID, $manialinksID, $karmaWidgetID, $rankingWidgetID, $dedimaniaWidgetID, $keyWidgetID;
  private $clockWidgetID, $adWidgetRaceID, $adWidgetAlwaysID, $adWidgetScoreID, $nextTrackWidgetID, $scoreBoardListsID;
  private $racing, $globalAdsSent, $keyWidgetSent;
  private $localRecordsHashs, $liveRankingHashs, $dedimaniaHashs;
  private $localRecordsStaticHash, $liveRankingStaticHash, $dedimaniaStaticHash;
  private $karmaHashs;
  private $lastShownKarma, $karmaStats;
  private $localRecordsCount, $liveRankingsCount, $dedimaniaCount;
  private $localTime;
  private $updateInterval, $lastKarmaUpdate, $lastRecordsUpdate;
  private $xasecoChallenge;
  private $firstChallengeLoaded;
  private $showRecordsWidgetsToLogin;
  private $actOnStatusChange, $firstStatusChange;
  private $liveRankings;
  private $oldRanking;
  private $hpmactive;
  public $smrankings = array();
  var $debugVars;
  var $settings;
  var $records_active;
  var $records_type;

  /**
   * Initializes the plugin, loads the XML settings
   */
  function init(){
    $this->loadSettings();
    $this->lastKarmaUpdate = -1;
    $this->lastRecordsUpdate = -1;

    // create some "unique" IDs for capturing clicks and updating the manialinks
    $this->manialinksID = "382";
    $this->challengeWidgetID = $this->manialinksID.'001';
    $this->nextTrackWidgetID = $this->manialinksID.'001'; //since they cannot be shown simultaneously but are very similar I use the same ID

    $this->closeToYouWidgetID = $this->manialinksID.'002';
    $this->scoreBoardListsID = $this->manialinksID.'002'; //since they cannot be shown simultaneously but are very similar I use the same ID

    $this->karmaWidgetID = $this->manialinksID.'003';
    $this->rankingWidgetID = $this->manialinksID.'004';
    $this->clockWidgetID = $this->manialinksID.'005';
    $this->adWidgetRaceID = $this->manialinksID.'006';
    $this->adWidgetAlwaysID = $this->manialinksID.'007';
    $this->adWidgetScoreID = $this->manialinksID.'008';
    $this->keyWidgetID = $this->manialinksID.'009';
    $this->dedimaniaWidgetID = $this->manialinksID.'010';

    // initialize some arrays and values
    $this->karmaHashs = array();
    $this->lastShownKarma = -1;
    $this->localRecordsHashs = array();
    $this->liveRankingHashs = array();
    $this->dedimaniaHashs = array();
    $this->localRecordsStaticHash = -1;
    $this->liveRankingStaticHash = -1;
    $this->dedimaniaStaticHash = -1;

    $this->showRecordsWidgetsToLogin = array();
    $this->localTime = "";
    $this->firstChallengeLoaded=false;
    $this->globalAdsSent = false;
    $this->keyWidgetSent = false;
    $this->debugVars = array();
    $this->actOnStatusChange = true;
    $this->firstStatusChange=true;
    $this->oldRanking = false;
    $this->hpmactive = false;
    $this->debug=false;   
    //$this->Aseco->debug=false;        
    $this->widgetsVersion = '0.2';
  }

  /**
   * Needed if in XASECO2 (inits the CustomUI)
   *
   */
  function xasecoStartup(){
    if ($this->settings['challengewidget']['enabled']){
      setCustomUIField('challenge_info', false);
    }
    $this->checkDependencies();
  }

  /**
   * Converts a string to a boolean          
   *
   * @param String $string
   * @return boolean
   */
  function stringToBool($string){
    if (strtoupper($string)=="FALSE" || $string=="0" || $string=="") return false;
    return true;
  }

  /**
   * Extracts the template blocks from the given xml file and returns an array
   *
   * @param String $xml
   * @return array
   */
  function getXMLTemplateBlocks($xml){
    $result = array();
    $xml_ = $xml;
    while (strstr($xml_, '<!--start_')){
      $xml_ = substr($xml_, strpos($xml_, '<!--start') + 10);
      $title = substr($xml_, 0, strpos($xml_, '-->'));
      $result[$title]= trim($this->getXMLBlock($xml, $title));
    }
    return $result;
  }

  /**
   * Replaces the styles in the templates
   */
  function replaceStyles($string){
    return str_replace(array('%style%', '%substyle%', '%topstyle%', '%topsubstyle%','%highlitestyle%', '%highlitesubstyle%'),
    array($this->settings['style'], $this->settings['substyle'], $this->settings['topstyle'], $this->settings['topsubstyle'], $this->settings['highlitestyle'], $this->settings['highlitesubstyle']), $string);
  }

  /**
   * Loads the settings from the XML file and stores them 
   * to the local variable $settings
   */
  function loadSettings(){
    $this->settings = array();
    $xml = simplexml_load_file('fufi_widgets_config.xml');


    $this->updateInterval = intval($xml->updateinterval);
    if ($this->updateInterval<1) $this->updateInterval = 1;
    $this->settings['style'] = strval($xml->style);
    $this->settings['substyle'] = strval($xml->substyle);
    $useAsecoStyle = $this->stringToBool(strval($xml->use_aseco_style));
    $asecoConfig = strval($xml->aseco_config_file);
    $this->settings['topstyle'] = strval($xml->topstyle);
    $this->settings['topsubstyle'] = strval($xml->topsubstyle);
    $this->settings['highlitestyle'] = strval($xml->highlitestyle);
    $this->settings['highlitesubstyle'] = strval($xml->highlitesubstyle);

    $this->settings['togglingdisabled'] = strval($xml->togglingdisabled);
    $this->settings['recordwidgetsdisabled'] = strval($xml->recordwidgetsdisabled);
    $this->settings['recordwidgetsenabled'] = strval($xml->recordwidgetsenabled);

    $this->settings['colortopcount'] = strval($xml->colortopcount);
    $this->settings['colorbetter'] = strval($xml->colorbetter);
    $this->settings['colorworse'] = strval($xml->colorworse);
    $this->settings['colorself'] = strval($xml->colorself);
        $this->settings['checkupdate'] = strval($xml->checkupdate);
    //highperformance mode settings

    $this->settings['hpm'] = array();
    $this->settings['hpm']['enabled'] = $this->stringToBool(strval($xml->highperformancemode->enabled));
    if ($this->settings['hpm']['enabled']){
      $this->settings['hpm']['ll'] = intval($xml->highperformancemode->lowerlimit);
      $this->settings['hpm']['ul'] = intval($xml->highperformancemode->upperlimit);
      $this->settings['hpm']['static'] = $this->stringToBool(strval($xml->highperformancemode->staticmode));
      $this->settings['hpm']['widgets'] = array();
      $this->settings['hpm']['widgets']['localrecords'] = $this->stringToBool(strval($xml->highperformancemode->displayedrecordwidgets->localrecordswidget));
      $this->settings['hpm']['widgets']['liverankings'] = $this->stringToBool(strval($xml->highperformancemode->displayedrecordwidgets->liverankingswidget));
      $this->settings['hpm']['widgets']['dedimania'] = $this->stringToBool(strval($xml->highperformancemode->displayedrecordwidgets->dedimaniawidget));
    }

    if ($useAsecoStyle){
      $asecoCfg = simplexml_load_file($asecoConfig);

      if (IN_XASECO){
        $stylefile = './styles/'.$asecoCfg->aseco->window_style.'.xml';
        if (file_exists($stylefile)){
          $styleXML = simplexml_load_file($stylefile);
          $this->settings['style'] = $styleXML->window->style;
          $this->settings['substyle'] = $styleXML->window->substyle;
        }
      } else {
        $stylefile = './styles/'.$asecoCfg->aseco->style.'.xml';
        if (file_exists($stylefile)){
          $styleXML = simplexml_load_file($stylefile);
          $this->settings['style'] = $styleXML->window->background->style;
          $this->settings['substyle'] = $styleXML->window->background->substyle;
        }
      }
    }

    if (IN_XASECO) $xml = simplexml_load_file('./plugins/fufi/fufi_widgets_xaseco.xml');
    else $xml = simplexml_load_file('./plugins/fufi/fufi_widgets_aseco.xml');

    //get karmawidget settings

    $karmaSettings = array();
    $karmaSettings["enabled"] = $this->stringToBool(strval($xml->karmawidget->enabled));
    $karmaSettings["title"] = strval($xml->karmawidget->title);
    $karmaSettings["states"] = array();
    foreach ($xml->karmawidget->states->state as $state){
      $enabled = $this->stringToBool(strval($state->enabled));

      $id = intval($state["id"]);
      $x = strval($state->x);
      $y = strval($state->y);
      if (isset ($state->titleoffsetx)){
        $titleoffsetx = strval($state->titleoffsetx);
      } else {
        $titleoffsetx = 0;
      }
      $pos = $x.' '.$y.' 0';
      $displaytitle = $this->stringToBool(strval($state->displaytitle));
      $displayvotecounts = $this->stringToBool(strval($state->displayvotecounts));
      $karmaSettings["states"][$id]=array();
      $karmaSettings["states"][$id]["pos"]=$pos;
      $karmaSettings["states"][$id]["enabled"]=$enabled;
      $karmaSettings["states"][$id]["displaytitle"]=$displaytitle;
      $karmaSettings["states"][$id]["displayvotecounts"]=$displayvotecounts;
      $karmaSettings["states"][$id]["titleoffsetx"]=$titleoffsetx;

    }
    $karmaSettings["xml"] = trim($this->replaceStyles(file_get_contents('./plugins/fufi/fufi_karmaWidget.xml')));
    $karmaSettings["blocks"] = $this->getXMLTemplateBlocks($karmaSettings["xml"]);
    $karmaSettings["needsUpdate"] = true;
    $this->settings["karmawidget"] = $karmaSettings;

    //get clockwidget settings

    $clockSettings = array();
    $clockSettings["enabled"] = $this->stringToBool(strval($xml->clockwidget->enabled));
    $clockSettings["timeformat"] = strval($xml->clockwidget->timeformat);
    $clockSettings["states"] = array();
    foreach ($xml->clockwidget->states->state as $state){
      $id = intval($state["id"]);
      $x = strval($state->x);
      $y = strval($state->y);
      $pos = $x.' '.$y.' 0';
      $clockSettings["states"][$id]=array();
      $clockSettings["states"][$id]["pos"]=$pos;
    }
    $clockSettings["xml" ] = $this->replaceStyles(file_get_contents('./plugins/fufi/fufi_clockWidget.xml'));
    $clockSettings["blocks"] = $this->getXMLTemplateBlocks($clockSettings["xml"]);
    $this->settings["clockwidget"] = $clockSettings;

    //get challengewidget settings

    $challengeSettings = array();
    $challengeSettings["enabled"] = $this->stringToBool(strval($xml->challengewidget->enabled));
    $challengeSettings["states"] = array();
    foreach ($xml->challengewidget->states->state as $state){
      $id = intval($state["id"]);
      $x = strval($state->x);
      $y = strval($state->y);
      $pos = $x.' '.$y.' 0';
      $challengeSettings["states"][$id]=array();
      $challengeSettings["states"][$id]["pos"]=$pos;
    }
    $challengeSettings["xml"] = $this->replaceStyles(file_get_contents('./plugins/fufi/fufi_challengeWidget.xml'));
    $challengeSettings["blocks"] = $this->getXMLTemplateBlocks($challengeSettings["xml"]);
    $this->settings["challengewidget"] = $challengeSettings;

    //get nexttrackwidget settings

    $nextTrackSettings = array();
    $nextTrackSettings["enabled"] = $this->stringToBool(strval($xml->nexttrackwidget->enabled));
    foreach ($xml->nexttrackwidget->states->state as $state){
      $x = strval($state->x);
      $y = strval($state->y);
      $pos = $x.' '.$y.' 0';
      $nextTrackSettings["pos"]=$pos;
    }
    $nextTrackSettings["xml"] = $this->replaceStyles(file_get_contents('./plugins/fufi/fufi_nextTrackWidget.xml'));
    $nextTrackSettings["blocks"] = $this->getXMLTemplateBlocks($nextTrackSettings["xml"]);
    $this->settings["nexttrackwidget"] = $nextTrackSettings;


    //get localrecordswidget settings

    $localRecordsSettings = array();
    $localRecordsSettings["enabled"] = $this->stringToBool(strval($xml->localrecordswidget->enabled));
    $localRecordsSettings["title"] = strval($xml->localrecordswidget->title);
    $localRecordsSettings["states"] = array();
    foreach ($xml->localrecordswidget->states->state as $state){
      $id = intval($state["id"]);
      $enabled = $this->stringToBool(strval($state->enabled));
      $entrycount = intval($state->entrycount);
      $x = strval($state->x);
      $y = strval($state->y);
      $pos = $x.' '.$y.' 0';
      $width = strval($state->width);
      $topCount = intval($state->topcount);
      if (isset ($state->titleoffsetx)){
        $titleoffsetx = strval($state->titleoffsetx);
      } else {
        $titleoffsetx = 0;
      }
      $displaytitle = $this->stringToBool(strval($state->displaytitle));

      $localRecordsSettings["states"][$id]=array();
      $localRecordsSettings["states"][$id]["displaytitle"]=$displaytitle;
      $localRecordsSettings["states"][$id]["width"]=$width;
      $localRecordsSettings["states"][$id]["enabled"]=$enabled;
      $localRecordsSettings["states"][$id]["entrycount"]=$entrycount;
      $localRecordsSettings["states"][$id]["topcount"]=$topCount;
      $localRecordsSettings["states"][$id]["pos"]=$pos;
      $localRecordsSettings["states"][$id]["titleoffsetx"]=$titleoffsetx;
    }
    $localRecordsSettings["xml"] = $this->replaceStyles(file_get_contents('./plugins/fufi/fufi_recordWidget.xml'));
    $localRecordsSettings["blocks"] = $this->getXMLTemplateBlocks($localRecordsSettings["xml"]);
    $localRecordsSettings["needsUpdate"] = true;
    $localRecordsSettings["forceUpdate"] = false;
    $this->settings["localrecordswidget"] = $localRecordsSettings;


    //get liverankingswidget settings

    $liveRankingsSettings = array();
    $liveRankingsSettings["enabled"] = $this->stringToBool(strval($xml->liverankingswidget->enabled));
    $liveRankingsSettings["title"] = strval($xml->liverankingswidget->title);
    $liveRankingsSettings["states"] = array();
    foreach ($xml->liverankingswidget->states->state as $state){
      $id = intval($state["id"]);
      $enabled = $this->stringToBool(strval($state->enabled));
      $entrycount = intval($state->entrycount);
      $x = strval($state->x);
      $y = strval($state->y);
      $pos = $x.' '.$y.' 0';
      $width = strval($state->width);
      $topCount = intval($state->topcount);
      if (isset ($state->titleoffsetx)){
        $titleoffsetx = strval($state->titleoffsetx);
      } else {
        $titleoffsetx = 0;
      }
      $displaytitle = $this->stringToBool(strval($state->displaytitle));

      $liveRankingsSettings["states"][$id]=array();
      $liveRankingsSettings["states"][$id]["displaytitle"]=$displaytitle;
      $liveRankingsSettings["states"][$id]["width"]=$width;
      $liveRankingsSettings["states"][$id]["enabled"]=$enabled;
      $liveRankingsSettings["states"][$id]["entrycount"]=$entrycount;
      $liveRankingsSettings["states"][$id]["pos"]=$pos;
      $liveRankingsSettings["states"][$id]["topcount"]=$topCount;
      $liveRankingsSettings["states"][$id]["titleoffsetx"]=$titleoffsetx;
    }
    $liveRankingsSettings["xml"] = $this->replaceStyles($localRecordsSettings["xml"]);
    $liveRankingsSettings["blocks"] = $localRecordsSettings["blocks"];

    $this->settings["liverankingswidget"] = $liveRankingsSettings;

    //get dedimaniawidget settings

    $dedimaniaSettings = array();
    $dedimaniaSettings["enabled"] = defined('IN_MPASECO') ? false : $this->stringToBool(strval($xml->dedimaniawidget->enabled));
    $dedimaniaSettings["title"] = strval($xml->dedimaniawidget->title);
    $dedimaniaSettings["states"] = array();
    foreach ($xml->dedimaniawidget->states->state as $state){
      $id = intval($state["id"]);
      $enabled = $this->stringToBool(strval($state->enabled));
      $entrycount = intval($state->entrycount);
      $x = strval($state->x);
      $y = strval($state->y);
      $pos = $x.' '.$y.' 0';
      $width = strval($state->width);
      $topCount = intval($state->topcount);
      if (isset ($state->titleoffsetx)){
        $titleoffsetx = strval($state->titleoffsetx);
      } else {
        $titleoffsetx = 0;
      }
      $displaytitle = $this->stringToBool(strval($state->displaytitle));

      $dedimaniaSettings["states"][$id]=array();
      $dedimaniaSettings["states"][$id]["displaytitle"]=$displaytitle;
      $dedimaniaSettings["states"][$id]["width"]=$width;
      $dedimaniaSettings["states"][$id]["enabled"]=$enabled;
      $dedimaniaSettings["states"][$id]["entrycount"]=$entrycount;
      $dedimaniaSettings["states"][$id]["pos"]=$pos;
      $dedimaniaSettings["states"][$id]["topcount"]=$topCount;
      $dedimaniaSettings["states"][$id]["titleoffsetx"]=$titleoffsetx;
    }
    $dedimaniaSettings["xml"] = $this->replaceStyles($localRecordsSettings["xml"]);
    $dedimaniaSettings["blocks"] = $localRecordsSettings["blocks"];

    $this->settings["dedimaniawidget"] = $dedimaniaSettings;

    //get adwidget settings

    $adsettings = array();
    $adsettings["enabled"] = $this->stringToBool(strval($xml->adwidget->enabled));
    $adsettings["ads"] = array();
    $adsettings["ads"]["always"] = array();
    $adsettings["ads"]["race"] = array();
    $adsettings["ads"]["score"] = array();

    foreach ($xml->adwidget->ads->ad as $ad){

      $x = strval($ad->x);
      $y = strval($ad->y);

      $halign = strtolower(strval($ad->halign));
      $valign = strtolower(strval($ad->valign));

      if (($halign != "left") && ($halign != "right")) $halign = "center";
      if (($valign != "top") && ($valign != "bottom")) $valign = "center";

      $width = strval($ad->width);
      $height = strval($ad->height);
      $pos = $x.' '.$y;
      $size = $width.' '.$height;


      $singlead=array();
      $singlead["pos"]=$pos;
      $singlead["size"]=$size;
      $singlead["halign"]=$halign;
      $singlead["valign"]=$valign;

      if (isset($ad->background)){
        $background = $this->stringToBool(strval($ad->background));
        $singlead["background"]=$background;
      }

      $singlead['text']='';
      $singlead['manialink']="";
      $singlead['url']="";

      if (isset($ad->text)){
        $text = strval($ad->text);
        if ($text!=""){
          $singlead['text']=$text;
        }
      }

      if (isset($ad->url)){
        $url = strval($ad->url);
        if ($url!=""){
          $singlead['manialink']="";
          $singlead['url']=$url;
        }
      }

      if (isset($ad->manialink)){
        $url = strval($ad->manialink);
        if ($url!=""){
          $singlead['url']="";
          $singlead['manialink']=$url;
        }
      }

      $image = strval($ad->image);
      $singlead['image']=$image;
      if (isset($ad->imagefocus)){
        $imagefocus = strval($ad->imagefocus);
        $singlead['imagefocus']=$imagefocus;
      }

      if (isset($ad->display)){
        $display = strtolower(strval($ad->display));
      } else {
        $display = 'always';
      }

      if (!isset($singlead['background'])) $singlead['background']=false;
      if (!isset($singlead['text'])) $singlead['text'] = false;
      if (!isset($singlead['url'])) $singlead['url'] = false;
      if (!isset($singlead['manialink'])) $singlead['manialink'] = false;
      if (!isset($singlead['image'])) $singlead['image'] = false;
      if (!isset($singlead['imagefocus'])) $singlead['imagefocus'] = false;

      $adsettings['ads'][$display][]=$singlead;

    }
    $adsettings["xml"] = $this->replaceStyles(file_get_contents('./plugins/fufi/fufi_adWidget.xml'));
    $adsettings["blocks"] = $this->getXMLTemplateBlocks($adsettings["xml"]);
    $this->settings["adwidget"] = $adsettings;

    //get scoreboardlists settings

    $sblsettings = array();
    $sblsettings["enabled"] = $this->stringToBool(strval($xml->scoreboardlists->enabled));
    $sblsettings["lists"] = array();
    $sblsettings["used"] = array();

    foreach ($xml->scoreboardlists->lists->list as $sbl){

      $x = strval($sbl->x);
      $y = strval($sbl->y);
      $title = strval($sbl->title);
      $content = strval($sbl->content);
      $sblsettings["used"][] = $content;
      $width = strval($sbl->width);
      $entrycount = intval($sbl->entrycount);
      $pos = $x.' '.$y;

      $singlelist=array();
      $singlelist["pos"]=$pos;
      $singlelist["width"]=$width;
      $singlelist["title"]=$title;
      $singlelist["content"]=$content;
      $singlelist["entrycount"] = $entrycount;

      if (isset($sbl->titleoffsetx)){
        $singlelist["titleoffsetx"] = strval($sbl->titleoffsetx);
      } else {
        $singlelist["titleoffsetx"] = 0;
      }

      $sblsettings['lists'][]=$singlelist;

    }
    $sblsettings["xml"] = $this->replaceStyles(file_get_contents('./plugins/fufi/fufi_recordWidget.xml'));
    $sblsettings["blocks"] = $this->getXMLTemplateBlocks($sblsettings["xml"]);
    $this->settings["sblwidget"] = $sblsettings;

  }


  /**
   * Extracts a specific marked block from the manialink XML templates
   * in the folder "./plugins/fufi"
   *
   * @param String $haystack
   * @param String $caption
   * @return The requested block in a String
   */
  function getXMLBlock($haystack, $caption){
    $startStr = '<!--start_'.$caption.'-->';
    $endStr = '<!--end_'.$caption.'-->';
    $haystack = substr($haystack, strpos($haystack, $startStr) + strlen($startStr));
    $haystack = substr($haystack, 0, strpos($haystack, $endStr));
    return ($haystack);
  }

  /**
   * Executes Karma votes
   *
   * @param String $login
   * @param String $action
   */
  function executeKarma($login, $action){
    $cmd=array();
    $cmd['author'] = $this->Aseco->server->players->getPlayer($login);
    if ($action==1){
      $vote = -1;
    } else if ($action==2){
      $vote = 1;
    } else return;


    if (IN_XASECO){
      KarmaVote($this->Aseco, $cmd, $vote);
    } else {
      $raspKarma = $this->Aseco->getPlugin('RaspKarma');
      $raspKarma->KarmaVote($cmd, $vote);
    }

  }

  /**
   * Displays the Karma widget to all or , if specified, 
   * only to the given login
   *
   * @param String $login
   */
  function showKarmaWidget($login=''){

    //the gamemode is needed to get the right configuration and display options

    $gamemode = $this->Aseco->server->gameinfo->mode;
    if (!$this->racing) $gamemode = 7;
    

    if (!($this->settings["karmawidget"]["enabled"] && $this->settings['karmawidget']['states'][$gamemode]['enabled'])) return;
    if (!$this->settings["karmawidget"]["needsUpdate"] && !$login) return;

    if (IN_XASECO){
      $challenge = $this->xasecoChallenge;
      $dbid = $challenge->id;
    } else {
      $challenge = $this->Aseco->server->challenge;
      //get some karma values from the database (large portions stolen from the RaspKarma plugin

      if (isset($challenge->data['DB_ID'])){
        $dbid = $challenge->data['DB_ID'];
      } else {
        $query = 'Select Id from maps where Uid="'.$challenge->uid.'"';
        $res=mysql_query($query);
        if (mysql_num_rows($res)!=1){
          $this->Aseco->console('[FufiWidgets] Unable to retrieve challenge ID for Karma widget');
          return;
        } else {
          $dbid = mysql_result($res, 0, 'Id');
        }
      }
    }

    if (IN_XASECO){
      $totkarma = $this->karmaStats["Total"];
      $goodkarma = $this->karmaStats["Good"];
    } else {
      $totkarma = $this->karmaStats["TotalVotes"];
      $goodkarma = $this->karmaStats["Good"];
    }


    $totalCups = 8;
    $glowCups = 0;
    if ($totkarma) $glowCups = round($goodkarma/$totkarma*$totalCups);


    //get the manialink XML template for the widget and separate it to blocks

    $xml = $this->settings["karmawidget"]["blocks"]["header"].$this->settings["karmawidget"]["blocks"]["window"];
    $title =  str_replace('%x%', 6+$this->settings['karmawidget']['states'][$gamemode]['titleoffsetx'], $this->settings["karmawidget"]["blocks"]["title"]);
    $glowAward =  $this->settings["karmawidget"]["blocks"]["glow_award"];
    $award =  $this->settings["karmawidget"]["blocks"]["award"];
    $highlightMinus =  $this->settings["karmawidget"]["blocks"]["highlight_minus"];
    $highlightPlus =  $this->settings["karmawidget"]["blocks"]["highlight_plus"];
    $neglabel = $this->settings["karmawidget"]["blocks"]["neglabel"];
    $poslabel = $this->settings["karmawidget"]["blocks"]["poslabel"];
    $minus =  $this->settings["karmawidget"]["blocks"]["minus"];
    $plus =  $this->settings["karmawidget"]["blocks"]["plus"];
    $footer=  $this->settings["karmawidget"]["blocks"]["footer"];

    //replace the placeholders in the xml blocks with content

    for ($i=0; $i< $totalCups; $i++){
      $x = 1.2*$i+1.3;
      $y = -2.5;
      $width = 1.6+($i/$totalCups)*0.6;
      if ($i<$glowCups){
        $aw = $glowAward;
      } else {
        $aw = $award;
      }
      $icon = str_replace(array('%width%', '%x%', '%y%'), array($width, $x, $y), $aw);
      $xml.=$icon;
    }

    $title = str_replace('%widgettitle%', $this->settings["karmawidget"]["title"], $title);
    $xml = str_replace(array('%manialinksid%', '%widgetid%', '%widgetpos%'), array($this->manialinksID, $this->karmaWidgetID.'000', $this->settings["karmawidget"]["states"][$gamemode]["pos"]), $xml);
    if ($this->settings["karmawidget"]["states"][$gamemode]["displaytitle"]) $xml.=$title;
    if ($this->settings["karmawidget"]["states"][$gamemode]["displayvotecounts"]){
      $xml.= str_replace('%text%', $totkarma-$goodkarma, $neglabel);
      $xml.= str_replace('%text%', $goodkarma, $poslabel);
      $hash = md5($totkarma - $goodkarma.' '.$goodkarma);
    } else {
      $hash = md5($totalCups.' ');
    }
    $xml.=$footer;





    //show the global part of the karma widget if necessary

    if ($this->lastShownKarma!=$hash||$login){
      if ($login){
        $this->sendMLtoLogin($login, $xml, 'Sending Karma Base', 'kw');
      } else {
        $this->lastShownKarma = $hash;
        $this->sendML($xml, 'Sending Karma Base', 'kw');
      }
    }


    //all below: create player specific manialink parts


    //replace the placeholders in the xml blocks with content

    $xml = $this->settings["karmawidget"]["blocks"]["header"];
    $xml = str_replace(array('%manialinksid%', '%widgetid%', '%widgetpos%'), array($this->manialinksID, $this->karmaWidgetID, $this->settings["karmawidget"]["states"][$gamemode]["pos"]), $xml);

    $votedPlus = $xml.str_replace(array('%action%'), array(''), $plus).str_replace(array('%action%'), array('action="'.$this->karmaWidgetID.'1"'), $minus).$highlightPlus.$footer;
    $votedMinus = $xml.str_replace(array('%action%'), array(''), $minus).str_replace(array('%action%'), array('action="'.$this->karmaWidgetID.'2"'), $plus).$highlightMinus.$footer;
    $notVoted = $xml.str_replace(array('%action%'), array('action="'.$this->karmaWidgetID.'1"'), $minus).str_replace(array('%action%'), array('action="'.$this->karmaWidgetID.'2"'), $plus).$footer;


    //get player specific karma votes from the database
    $query = 'select * from rs_karma where MapId='.$dbid;
    $res = mysql_query($query);
    $votes = array();

    for ($i=0; $i<mysql_num_rows($res); $i++){
      $playerID=mysql_result($res, $i, 'PlayerId');
      $votes["".$playerID] = mysql_result($res, $i, 'Score');
    }

    //show the player specific part of the karma widget to the players if necessary


    if ($this->Aseco->debug){
      $playercount = count($this->Aseco->server->players->player_list);
      $this->Aseco->console("[FufiWidgets] Attempting to update karma widget for ".$playercount." players.");
      $count=0;
    }

    foreach ($this->Aseco->server->players->player_list as $player){
      $query = "select Id from players where Login='".$player->login."'";
      $res = mysql_query($query);
      if (mysql_num_rows($res)){
        $playerID = mysql_result($res, 0, 'id');
      } else {
        $playerID = -1;
      }
      
      if (isset($votes["".$playerID])){
        $voted = intval($votes["".$playerID]);
      } else $voted = false;

      if ($voted==-1){
        $xml_ = $votedMinus;
      } else if ($voted==1){
        $xml_ = $votedPlus;
      } else {
        $xml_ = $notVoted;
      }

      if (!isset($this->karmaHashs[$player->login])) $this->karmaHashs[$player->login] = -2;

      //only sends manialink if it changed
      if ($this->karmaHashs[$player->login]!=$voted){
        $this->karmaHashs[$player->login]=$voted;
        $this->sendMLtoLogin($player->login, $xml_, '', 'kw', true);
        if ($this->Aseco->debug){
          $count++;
          echo'.';
        }
      }

    }
    if ($this->Aseco->debug){
      if ($count>0) echo CRLF;
      $this->Aseco->console("[FufiWidgets] ".$count." of ".$playercount." karma widgets needed update.");
    }

    $this->settings["karmawidget"]["needsUpdate"] = false;

  }

  /**
   * Wrapper for SendDisplayManialinkPageToLogin for debugging issues
   *
   * @param String $login
   * @param String $xml
   * @param String $debugText
   * @param String $debugVarsKey
   * @param boolean $silent
   */
  function sendMLtoLogin($login, $xml, $debugText, $debugVarsKey, $silent = false){
    //$xml = $this->unicodetoutf8($xml);
    if ($this->Aseco->debug) (simplexml_load_string($xml));
    if ($this->Aseco->debug && !$silent) $this->Aseco->console('[FufiWidgets] '.$debugText.' to ['.$login.']');
    $this->Aseco->client->addCall("SendDisplayManialinkPageToLogin", array($login, $xml, 0, false));
    if ($this->Aseco->debug) $this->debugVars[$debugVarsKey.'sdmptl']++;
  }

  /**
   * * Wrapper for SendDisplayManialinkPage for debugging issues
   *
   * @param String $xml
   * @param String $debugText
   * @param String $debugVarsKey
   * @param boolean $silent
   */
  function sendML($xml, $debugText, $debugVarsKey, $silent = false){
    //$xml = $this->unicodetoutf8($xml);
    if ($this->Aseco->debug && !$silent) $this->Aseco->console('[FufiWidgets] '.$debugText.' to all');
    $this->Aseco->client->addCall("SendDisplayManialinkPage", array($xml, 0, false));
    if ($this->Aseco->debug) $this->debugVars[$debugVarsKey.'sdmp']++;
  }

  /**
   * Creates a blank array with a specified length
   *
   * @param int $ctuCount The number of entries in the array
   * @return mixed
   */
  function blankCTUArray($ctuCount){
    $result = array();
    for ($i=0; $i<$ctuCount; $i++){
      $result[$i]=null;
    }
    return $result;
  }


  /**
   * Creates an array needed for the Local Records widget and the Live Rankings widget
   * in static high performance mode
   *
   * @param mixed $records
   * @param int $ctuCount
   * @return a specialized array
   */
  function getStaticRecordsArray($records, $ctuCount){
    $result = array();
    for ($i = 0; $i<min(count($records), ($ctuCount)); $i++){
      $entry = array();
      $entry["rank"]=$i+1;
      $entry["player"]=$records[$i]['player'];
      $entry["score"]=$records[$i]['score'];
      $entry["self"]=1;
      $result[] = $entry;
    }
    return $result;
  }

  /**
   * Creates an array needed for the Local Records widget and the Live Rankings widget
   * for a specific player
   *
   * @param mixed $records
   * @param string $login
   * @param int $ctuCount
   * @param int $topCount
   * @return a specialized array
   */
  function getCloseToYouArray($records, $login, $ctuCount, $topCount){

    $playerObj = $this->Aseco->server->players->getPlayer($login);
    if (IN_XASECO){
      $player = new CTUPlayer($playerObj->nickname, $playerObj->login);

    } else {
      $recplayer = new RecPlayer($playerObj);
      $player = new CTUPlayer($recplayer->nickname, $recplayer->login);
    }
    $result = $this->blankCTUArray($ctuCount);
    $better = array();
    $self = null;
    $worse = array();

    $isbetter=true;

    //constructs arrays with records of better and worse players than the specified player
    for ($i=0; $i<count($records); $i++){
      $entry = $records[$i];
      $entry["rank"]=$i+1;
      if ($isbetter){
        if ($records[$i]["player"]->login == $login){
          $self = $entry;
          $isbetter = false;
        } else {
          $better[]=$entry;
        }
      } else {
        $worse[] = $entry;
      }
    }

    //do the top x stuff
    $arrayTop = array();
    if (count($better)>$topCount){
      for ($i=0; $i<$topCount; $i++){
        $arrayTop[$i]=array();
        $arrayTop[$i]=array_shift($better);
        $arrayTop[$i]["self"]=-1;
      }
      $ctuCount -= $topCount;
    }

    //go through the possibile scenarios and choose the right one (wow, what an explanation^^)
    if (!$self){
      $lastIdx = $ctuCount - 1;
      $result[$lastIdx]=array();
      $result[$lastIdx]["rank"]=0;
      $result[$lastIdx]["player"]=$player;
      $result[$lastIdx]["score"]=0;
      $result[$lastIdx]["self"]=0;
      for($i=count($better)-1; $i>=0; $i--){
        if (--$lastIdx>=0){
          $result[$lastIdx]=$better[$i];
          $result[$lastIdx]["self"]=-1;
        }
      }
    } else {
      $hasbetter=true;
      $hasworse=true;
      $resultNew = array();

      $resultNew[0] = $self;
      $resultNew[0]["self"]=0;

      $idx=0;

      while (count($resultNew)<$ctuCount && ($hasbetter||$hasworse)){

        if ($hasbetter && (count($better)>= ($idx+1))){

          //push one record before
          $rec = $better[count($better)-1-$idx];
          $rec["self"]=-1;
          $help=array();
          $help[0]=$rec;
          for ($i=0; $i<count($resultNew); $i++){
            $help[$i+1]=$resultNew[$i];
          }
          $resultNew = $help;
        } else {
          $hasbetter = false;
        }
        if (count($resultNew)<($ctuCount)){
          if ($hasworse && (count($worse)>= ($idx+1))){

            //push one record behind
            $rec = $worse[$idx];
            $rec["self"]=1;
            $resultNew[]=$rec;
          } else {
            $hasworse = false;
          }
        }
        $idx++;
      }
      $result = $resultNew;
    }
    $result = array_merge($arrayTop, $result);

    $resultNew=array();
    $count=0;
    for ($i=0; $i<count($result); $i++){
      if ($result[$i]!=null){
        if (isset($result[$i]['self']) && $result[$i]['self']==0){
          if ($count>=$topCount) $result[$i]['highlitefull'] = true;
          else $result[$i]['highlitefull'] = false;
        }
        $resultNew[]= $result[$i];
        $count++;
      }
    }
    $result = $resultNew;

    return $result;
  }

  /**
   * Creates a hash value out of a Close2You entry
   *
   * @param array $arr
   *  @return a md5 hash string
   */   
  function ctuHash($arr){
    $result = "";
    for ($i=0; $i<count($arr); $i++){
      $result.= $arr[$i]["rank"];
      $result.= $arr[$i]["player"]->login;
      $result.= $arr[$i]["score"];
    }
    return md5($result);
  }

  /**
   * Creates a manialink string for an entry for the Close2You widgets
   *
   * @param array $rec
   * @param int $i
   * @param String $highlite
   * @param String $entry
   * @param boolean $showPoints
   * @return The manialink string
   */
  function getCTUEntry($rec, $i, $highlite, $entry, $showPoints, $width=0, $topCount){

    //$showpoints actually means that the widget calling that function wants to display points and not times

    if (!$showPoints){
      if (IN_XASECO){
        $score = formatTime($rec["score"]);
      } else {
        $score = substr(formatTime($rec["score"]),1);
      }
    } else {
      $score = $rec["score"].'   ';
    }

    //some gui formatting and replacement

    $nick = str_ireplace('$w', '', $rec["player"]->nickname);
    $nick = str_ireplace('$i', '', $nick);
    $nick = str_ireplace('$o', '', $nick);
    $rank = $rec["rank"];
    if ($rec["score"]==0){
      $rank = '--';
      if (!$showPoints) $score='--:--.--';
    }
    if ($rec["self"]==-1){
      if ($rec["rank"]<$topCount+1) $score = $this->settings['colortopcount'].$score;
      else $score = $this->settings['colorbetter'].$score;
    }
    else if ($rec["self"]==1){
      if ($rec["rank"]<$topCount+1) $score = $this->settings['colortopcount'].$score;
      else $score = $this->settings['colorworse'].$score;
    }
    else {
      $score = $this->settings['colorself'].$score;
      $y=-2*$i-0.9;
      if (isset($rec['highlitefull']) && $rec['highlitefull']){
        $highlite_ = str_replace(array('%y%'), array($y), $highlite);
      } else {
        //arrow only
        $highlite_ = str_replace(array('%y%'), array($y), $this->settings['localrecordswidget']['blocks']['highlight_entry_arrow_only']);
      }
      $highlite_ = str_replace('%x%', $width-0.5, $highlite_);

      $entry.=$highlite_;
    }
    $entry_ = str_replace(array("%x_rank%", "%y%", "%rank%", "%x_score%", "%score%", "%x_nick%", "%nick%"), array(1.8, -2*$i-1.2, $rank.'.', 6.8, $score, 7.2, htmlspecialchars($this->getValidUTF8String($nick))), $entry);
    return $entry_;

  }

  /**
   * Returns a valid UTF String and replaces faulty byte values with a given string
   * Thanks a lot to Slig for his original tm_substring function.
   *
   * @param String $str
   * @param String $replaceInvalidWith
   * @return String
   */
  function getValidUTF8String($str, $replaceInvalidWith = ''){
    $s = strlen($str); // byte string length
    $pos = 0; // current byte pos in string
    $newStr = '';

    while($pos < $s){
      $c = $str[$pos];
      $co = ord($c);

      if($co >= 240 && $co <248){ // 4 bytes utf8 => 11110xxx 10xxxxxx 10xxxxxx 10xxxxxx
        if(($pos+3 < $s ) &&
        (ord($str[$pos+1]) >= 128) && (ord($str[$pos+1]) < 192) &&
        (ord($str[$pos+2]) >= 128) && (ord($str[$pos+2]) < 192) &&
        (ord($str[$pos+3]) >= 128) && (ord($str[$pos+3]) < 192)){
          // ok, it was 1 character, increase counters
          $newStr.=substr($str, $pos, 4);
          $pos += 4;
        }else{
          // bad multibyte char
          $newStr.= $replaceInvalidWith;
          $pos++;
        }

      }elseif($co >= 224){ // 3 bytes utf8 => 1110xxxx 10xxxxxx 10xxxxxx
        if(($pos+2 < $s ) &&
        (ord($str[$pos+1]) >= 128) && (ord($str[$pos+1]) < 192) &&
        (ord($str[$pos+2]) >= 128) && (ord($str[$pos+2]) < 192)){
          // ok, it was 1 character, increase counters
          $newStr.=substr($str, $pos, 3);
          $pos += 3;
        }else{
          // bad multibyte char
          $newStr.= $replaceInvalidWith;
          $pos++;
        }

      }elseif($co >= 192){ // 2 bytes utf8 => 110xxxxx 10xxxxxx
        if(($pos+1 < $s ) &&
        (ord($str[$pos+1]) >= 128) && (ord($str[$pos+1]) < 192)){
          $newStr.=substr($str, $pos, 2);
          $pos += 2;
        }else{
          // bad multibyte char
          $newStr.=$replaceInvalidWith;
          $pos++;
        }

      }else{
        // ascii char or erroneus middle multibyte char
        if($co >=128)
        $newStr.=$replaceInvalidWith;
        else
        $newStr.=$str[$pos];

        $pos++;

      }
    }
    return $newStr;
  }

  /**
   * Shows the Local Record widget and the Live Rankings widget to all players.
   *
   * @param boolean $playerFinished Determines whether this function was called in result to the OnPlayerFinished event
   * @param String $login Only used onPlayerConnect in static high performance mode
   */
  function showRecordsWidgets($playerFinished=false, $login=''){

    //determine player count for highperformance mode
    $playercount = count($this->Aseco->server->players->player_list);

    $hpm = false;
    $static = false;
    if ($this->settings['hpm']['enabled']){
      if ($this->hpmactive){
        if ($playercount <= $this->settings['hpm']['ll']){
          $this->hpmactive = false;
          $this->settings["localrecordswidget"]["needsUpdate"] = true;
          $this->settings["localrecordswidget"]["forceUpdate"] = true;
        }
      } else {
        if ($playercount > $this->settings['hpm']['ul']){
          $this->hpmactive = true;
          $this->hideWidgetsForHPM();
          if ($static){
            $this->localRecordsStaticHash = -1;
            $this->liveRankingStaticHash = -1;
            $this->dedimaniaStaticHash = -1;
            $this->settings["localrecordswidget"]["needsUpdate"] = true;
            $this->settings["localrecordswidget"]["forceUpdate"] = true;
          }
        }
      }
      $hpm = $this->hpmactive;
      $static = $this->settings['hpm']['static'];
    }
    //$hpm = true;
    //$this->hpmactive = true;

    //the gamemode is needed to get the right configuration and display options
    $gamemode = $this->Aseco->server->gameinfo->mode;
    $roundsmode = $gamemode==1 || $gamemode==5 || ($gamemode==0 && !$this->records_active)
                  || ($gamemode==0 && $this->records_active && $this->records_type == "Points");
    
    $showLocalRecs = $this->settings["localrecordswidget"]["enabled"] && $this->settings["localrecordswidget"]["states"][$gamemode]["enabled"];
    if ($hpm) $showLocalRecs = $showLocalRecs && $this->settings['hpm']['widgets']['localrecords'];

    $showLiveRankings = $this->settings["liverankingswidget"]["enabled"] && $this->settings["liverankingswidget"]["states"][$gamemode]["enabled"];
    if ($hpm) $showLiveRankings = $showLiveRankings && $this->settings['hpm']['widgets']['liverankings'];

    $showDediRecs = $this->settings["dedimaniawidget"]["enabled"] && $this->settings["dedimaniawidget"]["states"][$gamemode]["enabled"];
    if ($hpm) $showDediRecs = $showDediRecs && $this->settings['hpm']['widgets']['dedimania'];

    //in roundsmode, there is no need to react onPlayerFinish
    if ($roundsmode && $playerFinished) $showLiveRankings = false;

    if (!($showLiveRankings || $showLocalRecs || $showDediRecs)) return;
    if (!$login){
      if (!$this->settings["localrecordswidget"]["forceUpdate"] && (!$this->settings["localrecordswidget"]["needsUpdate"] && $playerFinished)) return;
    }

    $showpointsLocalRecs = false;
    if ($gamemode==6) $showpointsLocalRecs = true;
    $showpointsLiveRankings = false;
  
    if ($gamemode==1 || $gamemode==6 || $gamemode==5 || ($gamemode==0 && !$this->records_active)
        || ($gamemode==0 && $this->records_active && $this->records_type == "Points"))
      $showpointsLiveRankings = true;

    //get the manialink XML template for the widget and separate it to blocks

    $header = $this->settings["localrecordswidget"]["blocks"]["header"];
    $header = str_replace(array("%manialinksid%"), array($this->manialinksID), $header);

    $footer = $this->settings["localrecordswidget"]["blocks"]["footer"];
    $footer_window = $this->settings["localrecordswidget"]["blocks"]["footer_window"];
    $entry = $this->settings["localrecordswidget"]["blocks"]["entry"];
    $highlite = $this->settings["localrecordswidget"]["blocks"]["highlight_entry"];

    $localRecordsCount = $this->settings["localrecordswidget"]["states"][$gamemode]["entrycount"];
    $liveRankingsCount = $this->settings["liverankingswidget"]["states"][$gamemode]["entrycount"];
    $dediRecsCount = $this->settings["dedimaniawidget"]["states"][$gamemode]["entrycount"];


    //local record stuff

    if ($showLocalRecs){

      //replace the placeholders in the xml blocks with content

      $headerLocalRecs = $this->settings["localrecordswidget"]["blocks"]["header_window"].$this->settings["localrecordswidget"]["blocks"]["window"];
      $headerLocalRecs = str_replace(array("%widgetid%", "%height%", "%width%", "%widgetpos%") , array($this->closeToYouWidgetID, 2*$localRecordsCount+2, $this->settings["localrecordswidget"]["states"][$gamemode]["width"], $this->settings["localrecordswidget"]["states"][$gamemode]["pos"]), $headerLocalRecs);
      if ($this->settings["localrecordswidget"]["states"][$gamemode]["displaytitle"]){
        $headerLocalRecs .= str_replace(array('%titlepos%', '%widgettitle%'), array($this->settings["localrecordswidget"]["states"][$gamemode]["width"]/2 + $this->settings["localrecordswidget"]["states"][$gamemode]["titleoffsetx"], $this->settings["localrecordswidget"]["title"]), $this->settings['localrecordswidget']['blocks']['title']);
      }
      $highliteLocalRecs = str_replace('%width%', $this->settings["localrecordswidget"]["states"][$gamemode]["width"]-1, $highlite);


      //get the records from Aseco

      $records = $this->getLocalRecsList();
    }


    //live rankings stuff

    if ($showLiveRankings){
  
      //replace the placeholders in the xml blocks with content

      $headerLiveRecs = $this->settings["localrecordswidget"]["blocks"]["header_window"].$this->settings["localrecordswidget"]["blocks"]["window"];
      $headerLiveRecs = str_replace(array("%widgetid%", "%height%", "%width%", "%widgetpos%") , array($this->rankingWidgetID, 2*$liveRankingsCount+2, $this->settings["liverankingswidget"]["states"][$gamemode]["width"], $this->settings["liverankingswidget"]["states"][$gamemode]["pos"]), $headerLiveRecs);
      if ($this->settings["liverankingswidget"]["states"][$gamemode]["displaytitle"]){
        $headerLiveRecs .= str_replace(array('%titlepos%',  '%widgettitle%'), array($this->settings["liverankingswidget"]["states"][$gamemode]["width"]/2 + $this->settings["liverankingswidget"]["states"][$gamemode]["titleoffsetx"], $this->settings["liverankingswidget"]["title"]), $this->settings['liverankingswidget']['blocks']['title']);
      }
      $highliteLiveRankings = str_replace('%width%', $this->settings["liverankingswidget"]["states"][$gamemode]["width"]-1, $highlite);

      $rankings = $this->getLiveRanksList();
    }

    //dedimania widget stuff

    if ($showDediRecs){

      //replace the placeholders in the xml blocks with content

      $headerDediRecs = $this->settings["localrecordswidget"]["blocks"]["header_window"].$this->settings["localrecordswidget"]["blocks"]["window"];
      $headerDediRecs = str_replace(array("%widgetid%", "%height%", "%width%", "%widgetpos%") , array($this->dedimaniaWidgetID, 2*$dediRecsCount+2, $this->settings["dedimaniawidget"]["states"][$gamemode]["width"], $this->settings["dedimaniawidget"]["states"][$gamemode]["pos"]), $headerDediRecs);
      if ($this->settings["dedimaniawidget"]["states"][$gamemode]["displaytitle"]){
        $headerDediRecs .= str_replace(array('%titlepos%',  '%widgettitle%'), array($this->settings["dedimaniawidget"]["states"][$gamemode]["width"]/2 + $this->settings["dedimaniawidget"]["states"][$gamemode]["titleoffsetx"], $this->settings["dedimaniawidget"]["title"]), $this->settings['localrecordswidget']['blocks']['title']);
      }
      $highliteDediRecs = str_replace('%width%', $this->settings["dedimaniawidget"]["states"][$gamemode]["width"]-1, $highlite);

      $dediRecs = $this->getDediRecsList();
      if (!$dediRecs){
        $dediRecs = array();
        $tryAgain = true;
      }

    }

    if ($hpm && $static){

      $localRecordsChanged = false;
      $liveRankingsChanged = false;
      $dediRecsChanged = false;

      $localRecContent = '';
      $liveRankingContent = '';
      $dediRecsContent = '';

      //local records stuff
      if ($showLocalRecs){
        $localRecordsStaticArray = $this->getStaticRecordsArray($records, $localRecordsCount);
        $localRecordsStaticHash = $this->ctuHash($localRecordsStaticArray);
        if ($this->localRecordsStaticHash != $localRecordsStaticHash){
          $localRecordsChanged = true;
          $this->localRecordsStaticHash = $localRecordsStaticHash;
        }
        for ($i=0;$i<count($localRecordsStaticArray); $i++){
          $localRecContent.= str_replace('%nickwidth%', $this->settings["localrecordswidget"]["states"][$gamemode]["width"]-8.5, $this->getCTUEntry($localRecordsStaticArray[$i], $i, $highliteLocalRecs, $entry, $showpointsLocalRecs, $this->settings['localrecordswidget']['states'][$gamemode]["width"],  $this->settings['localrecordswidget']['states'][$gamemode]['topcount']));
        }
        $highliteTop='';
        if ($this->settings['localrecordswidget']['states'][$gamemode]['topcount']){
          $highliteTop = str_replace(array('%width%', '%height%', '%x%', '%y%'), array($this->settings['localrecordswidget']['states'][$gamemode]['width']-1,0.7+2*$this->settings['localrecordswidget']['states'][$gamemode]['topcount'],0.5,-0.5), $this->settings['localrecordswidget']['blocks']['windowtop']);
        }
        $localRecContent = $headerLocalRecs.$highliteTop.$localRecContent.$footer_window;
      }

      //live rankings stuff
      if ($showLiveRankings){
        $liveRankingsStaticArray = $this->getStaticRecordsArray($rankings, $liveRankingsCount);
        $liveRankingsStaticHash = $this->ctuHash($liveRankingsStaticArray);
        if ($this->liveRankingStaticHash != $liveRankingsStaticHash){
          $liveRankingsChanged = true;
          $this->liveRankingStaticHash = $liveRankingsStaticHash;
        }
        for ($i=0;$i<count($liveRankingsStaticArray); $i++){
          $liveRankingContent.= str_replace('%nickwidth%', $this->settings["liverankingswidget"]["states"][$gamemode]["width"]-8.5, $this->getCTUEntry($liveRankingsStaticArray[$i], $i, $highliteLocalRecs, $entry, $showpointsLiveRankings, $this->settings['liverankingswidget']['states'][$gamemode]["width"],  $this->settings['liverankingswidget']['states'][$gamemode]['topcount']));
        }
        $highliteTop='';
        if ($this->settings['liverankingswidget']['states'][$gamemode]['topcount']){
          $highliteTop = str_replace(array('%width%', '%height%', '%x%', '%y%'), array($this->settings['liverankingswidget']['states'][$gamemode]['width']-1,0.7+2*$this->settings['liverankingswidget']['states'][$gamemode]['topcount'],0.5,-0.5), $this->settings['localrecordswidget']['blocks']['windowtop']);
        }
        $liveRankingContent = $headerLiveRecs.$highliteTop.$liveRankingContent.$footer_window;
      }

      //dedimania stuff
      if ($showDediRecs){
        $dediRecsStaticArray = $this->getStaticRecordsArray($dediRecs, $dediRecsCount);
        $dediRecsStaticHash = $this->ctuHash($dediRecsStaticArray);
        if ($this->dedimaniaStaticHash != $dediRecsStaticHash){
          $dediRecsChanged = true;
          $this->dedimaniaStaticHash = $dediRecsStaticHash;
        }
        for ($i=0;$i<count($dediRecsStaticArray); $i++){
          $dediRecsContent.= str_replace('%nickwidth%', $this->settings["dedimaniawidget"]["states"][$gamemode]["width"]-8.5, $this->getCTUEntry($dediRecsStaticArray[$i], $i, $highliteLocalRecs, $entry, $showpointsLocalRecs, $this->settings['dedimaniawidget']['states'][$gamemode]["width"],  $this->settings['dedimaniawidget']['states'][$gamemode]['topcount']));
        }
        $highliteTop='';
        if ($this->settings['dedimaniawidget']['states'][$gamemode]['topcount']){
          $highliteTop = str_replace(array('%width%', '%height%', '%x%', '%y%'), array($this->settings['dedimaniawidget']['states'][$gamemode]['width']-1,0.7+2*$this->settings['dedimaniawidget']['states'][$gamemode]['topcount'],0.5,-0.5), $this->settings['localrecordswidget']['blocks']['windowtop']);
        }
        $dediRecsContent = $headerDediRecs.$highliteTop.$dediRecsContent.$footer_window;
      }

      if ($login){
        $xml = $header.$localRecContent.$liveRankingContent.$dediRecsContent.$footer;
        $this->sendMLtoLogin($login, $xml, 'Sending static record widgets', 'rw');
      } else {
        if ($localRecordsChanged || $liveRankingsChanged || $dediRecsChanged){
          $content = '';
          if ($localRecordsChanged) $content.= $localRecContent;
          if ($liveRankingsChanged) $content.= $liveRankingContent;
          if ($dediRecsChanged) $content.= $dediRecsContent;
          $xml = $header.$content.$footer;
          $this->sendML($xml, 'Sending static record widgets', 'rw');
        }
      }

    } else {

      if ($this->Aseco->debug){
        $playercount = count($this->Aseco->server->players->player_list);
        $this->Aseco->console("[FufiWidgets] Attempting to update rec widgets for ".$playercount." players.");
        $count=0;
      }


      foreach ($this->Aseco->server->players->player_list as $player){


        //local records stuff (create the Close2You array and a hash value)

        if ($showLocalRecs){

          $localRecordsCtu = $this->getCloseToYouArray($records, $player->login, $localRecordsCount, $this->settings['localrecordswidget']['states'][$gamemode]['topcount']);
          $localRecordsCtuHash = $this->ctuHash($localRecordsCtu);

          if (!isset($this->localRecordsHashs[$player->login])){
            $this->localRecordsHashs[$player->login]="-1";
          }
        }


        //live rankings stuff (create the Close2You array and a hash value)

        if ($showLiveRankings){

          $liveRankingCtu = $this->getCloseToYouArray($rankings, $player->login, $liveRankingsCount,  $this->settings['liverankingswidget']['states'][$gamemode]['topcount']);
          $liveRankingCtuHash = $this->ctuHash($liveRankingCtu);

          if (!isset($this->liveRankingHashs[$player->login])){
            $this->liveRankingHashs[$player->login]="-1";
          }
        }

        //dedimania stuff (create the Close2You array and a hash value)

        if ($showDediRecs){

          $dediRecsCtu = $this->getCloseToYouArray($dediRecs, $player->login, $dediRecsCount,  $this->settings['dedimaniawidget']['states'][$gamemode]['topcount']);
          $dediRecsCtuHash = $this->ctuHash($dediRecsCtu);

          if (!isset($this->dedimaniaHashs[$player->login])){
            $this->dedimaniaHashs[$player->login]="-1";
          }
        }

        // only update the widget manialink if something changed

        if (($this->localRecordsHashs[$player->login] != $localRecordsCtuHash) || ($this->liveRankingHashs[$player->login] != $liveRankingCtuHash || $this->dedimaniaHashs[$player->login] != $dediRecsCtuHash)){
          $localRecContent = '';
          $liveRankingContent = '';
          $dediRecsContent = '';


          // local records stuff (create the new widget manialink, if necessary)

          $localRecordsChanged = false;
          if (($this->localRecordsHashs[$player->login] != $localRecordsCtuHash) && $showLocalRecs){
            $this->localRecordsHashs[$player->login] = $localRecordsCtuHash;

            for ($i=0;$i<count($localRecordsCtu); $i++){

              $localRecContent.= str_replace('%nickwidth%', $this->settings["localrecordswidget"]["states"][$gamemode]["width"]-8.5, $this->getCTUEntry($localRecordsCtu[$i], $i, $highliteLocalRecs, $entry, $showpointsLocalRecs, $this->settings['localrecordswidget']['states'][$gamemode]["width"],  $this->settings['localrecordswidget']['states'][$gamemode]['topcount']));

            }
            $highliteTop='';
            if ($this->settings['localrecordswidget']['states'][$gamemode]['topcount']){
              $highliteTop = str_replace(array('%width%', '%height%', '%x%', '%y%'), array($this->settings['localrecordswidget']['states'][$gamemode]['width']-1,0.7+2*$this->settings['localrecordswidget']['states'][$gamemode]['topcount'],0.5,-0.5), $this->settings['localrecordswidget']['blocks']['windowtop']);
            }
            $localRecContent = $headerLocalRecs.$highliteTop.$localRecContent.$footer_window;
            $localRecordsChanged = true;
          }

          // live rankings stuff (create the new widget manialink, if necessary)

          $liveRankingsChanged = false;
          if (($this->liveRankingHashs[$player->login] != $liveRankingCtuHash) && $showLiveRankings){
            $this->liveRankingHashs[$player->login] = $liveRankingCtuHash;

            for ($i=0;$i<count($liveRankingCtu); $i++){
              $liveRankingContent.=str_replace('%nickwidth%', $this->settings["liverankingswidget"]["states"][$gamemode]["width"]-8.5, $this->getCTUEntry($liveRankingCtu[$i], $i, $highliteLiveRankings, $entry, $showpointsLiveRankings, $this->settings['liverankingswidget']['states'][$gamemode]["width"],  $this->settings['liverankingswidget']['states'][$gamemode]['topcount']));

            }
            $highliteTop='';
            if ($this->settings['liverankingswidget']['states'][$gamemode]['topcount']){
              $highliteTop = str_replace(array('%width%', '%height%', '%x%', '%y%'), array($this->settings['liverankingswidget']['states'][$gamemode]['width']-1,0.7+2*$this->settings['liverankingswidget']['states'][$gamemode]['topcount'],0.5,-0.5), $this->settings['localrecordswidget']['blocks']['windowtop']);
            }
            $liveRankingContent = $headerLiveRecs.$highliteTop.$liveRankingContent.$footer_window;
            $liveRankingsChanged = true;
          }

          // dedimania widget stuff (create the new widget manialink, if necessary)

          $dediRecsChanged = false;
          if ((!isset($this->dedimaniaHashs[$player->login]) || $this->dedimaniaHashs[$player->login] != $dediRecsCtuHash) && $showDediRecs){
            $this->dedimaniaHashs[$player->login] = $dediRecsCtuHash;

            for ($i=0;$i<count($dediRecsCtu); $i++){
              $dediRecsContent.=str_replace('%nickwidth%', $this->settings["dedimaniawidget"]["states"][$gamemode]["width"]-8.5, $this->getCTUEntry($dediRecsCtu[$i], $i, $highliteDediRecs, $entry, $showpointsLocalRecs, $this->settings['dedimaniawidget']['states'][$gamemode]["width"],  $this->settings['dedimaniawidget']['states'][$gamemode]['topcount']));
            }
            $highliteTop='';
            if ($this->settings['dedimaniawidget']['states'][$gamemode]['topcount']){
              $highliteTop = str_replace(array('%width%', '%height%', '%x%', '%y%'), array($this->settings['dedimaniawidget']['states'][$gamemode]['width']-1,0.7+2*$this->settings['dedimaniawidget']['states'][$gamemode]['topcount'],0.5,-0.5), $this->settings['dedimaniawidget']['blocks']['windowtop']);
            }
            $dediRecsContent = $headerDediRecs.$highliteTop.$dediRecsContent.$footer_window;
            $dediRecsChanged = true;
          }

          // send it
          $xml = $header.$localRecContent.$liveRankingContent.$dediRecsContent.$footer;
          if ($this->showRecordsWidgetsToLogin[$player->login] && ($localRecordsChanged || $liveRankingsChanged || $dediRecsChanged)){
            $this->sendMLtoLogin($player->login, $xml, '', 'rw', true);
            if ($this->Aseco->debug){
              $count++;
              echo ".";
            }
          }

        }
      }

      if ($this->Aseco->debug){
        if ($count>0) echo CRLF;
        $this->Aseco->console("[FufiWidgets] ".$count." of ".$playercount." rec widgets needed update.");
      }
    }

    $this->settings["localrecordswidget"]["needsUpdate"] = false;
    $this->settings["localrecordswidget"]["forceUpdate"] = false;

    if (isset($tryAgain) && $tryAgain) $this->settings["localrecordswidget"]["needsUpdate"] = true;

  }

  function unicodetoutf8($string) {
    // Latin 1
    $string = str_replace("\xFF\xFE", '', $string); // remove unicode
    $string = str_replace("\x0D\x00\x0A\x00", '<br>', $string); // convert \r\n to <br>
    $string = str_replace("\x20\x00", ' ', $string);
    $string = str_replace("\x21\x00", '!', $string);
    $string = str_replace("\x22\x00", '"', $string);
    $string = str_replace("\x23\x00", '&#35;', $string);
    $string = str_replace("\x24\x00", '$', $string);
    $string = str_replace("\x25\x00", '%', $string);
    $string = str_replace("\x26\x00", '&#38;', $string);
    $string = str_replace("\x27\x00", '&#39;', $string);
    $string = str_replace("\x28\x00", '(', $string);
    // usw usf
  }

  /**
   * Shows the Clock widget
   *
   */
  function showClockWidget(){
    if (!$this->settings["clockwidget"]["enabled"]) return;

    $time = time();
    $localTime = date($this->settings["clockwidget"]["timeformat"], time());


    //sends the new time if necessary

    if ($localTime!=$this->localTime){
      $this->localTime = $localTime;
      $page = $this->settings["clockwidget"]["xml"];
      $page = str_replace(array("%manialinksid%", "%widgetid%", "%widgetpos%", "%time%"), array($this->manialinksID, $this->clockWidgetID, $this->settings["clockwidget"]["states"]["0"]["pos"], $this->localTime), $page);
      $this->sendML($page, 'Sending clock widget', 'cl');
    }
  }

  /**
   * Shows the NextTrackWidget
   */

  function showNextTrackWidget($login=''){
    if (!$this->settings['nexttrackwidget']['enabled']) return;

    $page = $this->settings["nexttrackwidget"]["blocks"]["header"].
    $this->settings["nexttrackwidget"]["blocks"]["defaultstate"].
    $this->settings["nexttrackwidget"]["blocks"]["footer"];

    //search for jukebox entries
    $jukebox = array();

    if (IN_XASECO){
      global $jukebox;
    } else {
      $jukeboxPlugin = $this->Aseco->getPlugin(('JukeBox'));
      if ($jukeboxPlugin){
        if (method_exists($jukeboxPlugin, 'getJukebox')) $jukebox = $jukeboxPlugin->getJukebox();
        else ($this->Aseco->console('[FufiWidgets] Unable to get Aseco\'s Jukebox. You\'ll have to upgrade Aseco to a newer version than 2.1.4 (if it\'s already out.^^'));
      }
    }

    foreach ($jukebox as $track){
      $next = $track;
      break;
    }

    if (isset($next)) $filename = $next["FileName"]; else $filename=false;

    if (!$filename){
      $this->Aseco->client->query("GetNextMapIndex");
      $next_index = $this->Aseco->client->getResponse();
      // do GetChallengeList and send it the next index, this way you avoid looping through data to find the right one later
      $this->Aseco->client->query("GetMapList", 1, $next_index);
      $nextchallenge = $this->Aseco->client->getResponse();
      $filename = $nextchallenge[0]['FileName'];
    }

    $this->Aseco->client->query("GetMapInfo", $filename);
    $nextchallenge = $this->Aseco->client->getResponse();
    $page = str_replace(array('%nextauthortimeline1%','%nextauthortimeline2%'), (!IN_MPASECO ? array('<quad sizen="2 2" halign="right" posn="2.75 5.1 0.1" style="BgRaceScore2" substyle="ScoreReplay"/>','<label sizen="6 2" posn="3.3 4.9 0.1" text="%nextauthortime%"/>') : ''), $page);

    $ncSearches = array ('%nextname%', '%nextauthortime%', '%nextauthor%', '%nextenv%', '%nextmood%');
    $ncReplaces = array (htmlspecialchars($this->getValidUTF8String($nextchallenge["Name"])), formatTime($nextchallenge['AuthorTime']), htmlspecialchars($this->getValidUTF8String($nextchallenge['Author'])), $nextchallenge["Environnement"], $nextchallenge["Mood"]);
    $page = str_replace($ncSearches, $ncReplaces, $page);

    $page = str_replace(array('%manialinksid%', '%widgetid%', '%widgetpos%'), array($this->manialinksID, $this->nextTrackWidgetID, $this->settings['nexttrackwidget']['pos']), $page);

    if ($login){
      $this->sendMLtoLogin($login, $page, 'Sending NextTrackWidget', 'nt');
    } else {
      $this->sendML($page, 'Sending NextTrackWidget', 'nt');
    }
  }

  /**
   * Shows the key widget (invisible, only for capturing keypresses)
   *
   */
  function showKeyWidget($login=''){
    if ($this->settings['challengewidget']['enabled']){
      if (IN_XASECO){
        $customui = getCustomUIBlock();
      } else {
        $customui = $this->settings['challengewidget']['blocks']['customui'];
      }
    }
    $xml = '<?xml version="1.0" encoding="UTF-8"?><manialinks id="'.$this->manialinksID.'"><manialink id="'.$this->keyWidgetID.'">'.
    '<quad action="'.$this->keyWidgetID.'003" actionkey="3" sizen="0 0"  posn="70 70 1"/>'.
    '</manialink>'.$customui.'</manialinks>';

    if ($login){
      $this->sendMLtoLogin($login, $xml, 'Sending key widget', 'ky');
    } else {
      if (!$this->keyWidgetSent){
        $this->keyWidgetSent = true;
        $this->sendML($xml, 'Sending key widget', 'ky');
      }

    }
  }

  /**
   * Shows the Ad widget
   *
   */
  function showAdWidget($login = ''){

    if (!$this->settings["adwidget"]["enabled"]) return;

    $headerRace = str_replace(array('%manialinksid', '%widgetid%'), array($this->manialinksID, $this->adWidgetRaceID), $this->settings["adwidget"]["blocks"]["header"]);
    $headerAlways = str_replace(array('%manialinksid', '%widgetid%'), array($this->manialinksID, $this->adWidgetAlwaysID), $this->settings["adwidget"]["blocks"]["header"]);
    $headerScore = str_replace(array('%manialinksid', '%widgetid%'), array($this->manialinksID, $this->adWidgetScoreID), $this->settings["adwidget"]["blocks"]["header"]);

    $ads = '';
    $strings = array();
    foreach ($this->settings["adwidget"]["ads"] as $display => $ads){
      $strings[$display] = "";
      foreach ($ads as $ad){
        $base = "";
        if ($ad["background"])
        $base = str_replace(array('%adsize%', '%adpos%'), array($ad["size"], $ad["pos"]), $this->settings["adwidget"]["blocks"]["window"]);

        if ($ad['image']){
          if ($ad["imagefocus"]){
            $base .= str_replace(array('%adsize%', '%adpos%', '%adurl%', '%admanialink%', '%image%', '%imagefocus%'),
            array($ad["size"], $ad["pos"], $ad["url"], $ad["manialink"], $ad["image"], $ad["imagefocus"]), $this->settings["adwidget"]["blocks"]["image_mouseover"]);
          } else {
            $base .= str_replace(array('%adsize%', '%adpos%', '%adurl%', '%admanialink%', '%image%'),
            array($ad["size"], $ad["pos"], $ad["url"], $ad["manialink"], $ad["image"]), $this->settings["adwidget"]["blocks"]["image"]);
          }
        }
        if ($ad['text']){
          $pos = explode(' ', $ad['pos']);
          $size = explode(' ',$ad['size']);
          $pos[0] = $pos[0]+$size[0]/2;
          $pos[1] = $pos[1]-$size[1]/2;
          $pos = implode (' ', $pos);
          $url='';
          $manialink;
          if (!$ad['image']){
            $url = $ad['url'];
            $manialink = $ad['manialink'];
          }
          $base .= str_replace(array('%adsize%', '%adpos%', '%adurl%', '%admanialink%', '%text%', '%halign%', '%valign%'),
          array($ad["size"], $pos, $url, $manialink, $ad['text'], $ad['halign'], $ad['valign']), $this->settings["adwidget"]["blocks"]["text"]);
        }

        $strings[$display].=$base;
      }

    }

    $page = '';
    if ($this->racing){
      if ($strings['race']){
        $page = $headerRace.$strings['race'].$this->settings["adwidget"]["blocks"]["footer"];
      }
      $debugText = 'Sending "race" ad widget';
    } else {
      if ($strings['score']){
        $page = $headerScore.$strings['score'].$this->settings["adwidget"]["blocks"]["footer"];
      }
      $debugText = 'Sending "score" ad widget';
    }

    $pageGlobal ='';
    if ($strings['always']){
      $pageGlobal = $headerAlways.$strings['always'].$this->settings["adwidget"]["blocks"]["footer"];
    }

    $debugVarsKey = 'aw';
    if ($login){
      if ($page) $this->sendMLtoLogin($login, $page, $debugText, $debugVarsKey);
      if ($pageGlobal) $this->sendMLtoLogin($login, $pageGlobal, 'Sending "global" ad widget', $debugVarsKey);
    } else {
      if ($page) $this->sendML($page, $debugText, $debugVarsKey);
      if (!$this->globalAdsSent){
        $this->globalAdsSent = true;
        if ($pageGlobal) $this->sendML($pageGlobal, 'Sending "global" ad widget', $debugVarsKey);
      }
    }
  }

  /**
   * Shows the Challenge widget to all or to a specific player
   *
   * @param String $login
   * @param int $state 0..default 1..expanded
   */
  function showChallengeInfo($login, $state = 0){
    if (!$this->settings["challengewidget"]["enabled"]) return;


    //little workaround that displays a mx button if the mxinfo plugin is available

    if (IN_XASECO){
            //TODO : Update this for Mania Exchange
      $mxInfo = true;
    } else {
      $mxInfo = $this->Aseco->getPlugin('MX_info');
    }

    if ($mxInfo && ($state == 2)){
      $command = array();
      $command['author']= $this->Aseco->server->players->getPlayer($login);
            $command['params']= "";
            
      if (IN_XASECO){
        chat_mxinfo($this->Aseco, $command);
      } else {
        $mxInfo->MX_info_manialink($command);
      }
      return;
    }
    if (!$mxInfo && $state==1){
      $state = 2;
    }


    if (IN_XASECO){
      $challenge = $this->xasecoChallenge;
    } else {
      $challenge = $this->Aseco->server->challenge;
    }

    //get the manialink XML template for the widget and separate it to blocks (depends on state id)

    if ($state == 0){
      $page = $this->settings["challengewidget"]["blocks"]["header"].
      $this->settings["challengewidget"]["blocks"]["defaultstate"].
      $this->settings["challengewidget"]["blocks"]["footer"];
    } else if ($state == 1){
      $page = $this->settings["challengewidget"]["blocks"]["header"].
      $this->settings["challengewidget"]["blocks"]["maxstate"].
      $this->settings["challengewidget"]["blocks"]["tmxbutton"].
      $this->settings["challengewidget"]["blocks"]["footer"];
    } else if ($state == 2){
      $page = $this->settings["challengewidget"]["blocks"]["header"].
      $this->settings["challengewidget"]["blocks"]["maxstate"].
      $this->settings["challengewidget"]["blocks"]["footer"];
    }

    // if in expanded state
    if ($state != 0){

      //search for jukebox entries

      $jukebox = array();

      if (IN_XASECO){
        global $jukebox;
      } else {
        $jukeboxPlugin = $this->Aseco->getPlugin(('JukeBox'));
        if ($jukeboxPlugin){
          if (method_exists($jukeboxPlugin, 'getJukebox')) $jukebox = $jukeboxPlugin->getJukebox();
          else ($this->Aseco->console('[FufiWidgets] Unable to get Aseco\'s Jukebox. You\'ll have to upgrade Aseco to a newer version than 2.1.4 (if it\'s already out.^^'));
        }
      }

      foreach ($jukebox as $track){
        $next = $track;
        break;
      }
      $filename = $next["FileName"];

      if (!$filename){
        $this->Aseco->client->query("GetNextMapIndex");
        $next_index = $this->Aseco->client->getResponse();
        // do GetChallengeList and send it the next index, this way you avoid looping through data to find the right one later
        $this->Aseco->client->query("GetMapList", 1, $next_index);
        $nextchallenge = $this->Aseco->client->getResponse();
        $filename = $nextchallenge[0]['FileName'];
      }

      $this->Aseco->client->query("GetMapInfo", $filename);
      $nextchallenge = $this->Aseco->client->getResponse();
      $page = str_replace(array('%nextauthortimeline1%','%nextauthortimeline2%'), (!IN_MPASECO ? array('<quad sizen="2.6 2.6" halign="right" posn="3 7.6 0.1" style="Icons64x64_1" substyle="Finish"/>','<label sizen="6 2" posn="3.3 4.9 0.1" text="%nextauthortime%"/>') : ''), $page);
      
      $ncSearches = array ('%nextname%', '%nextauthortime%', '%nextauthor%', '%nextenv%', '%nextmood%');
      $ncReplaces = array (htmlspecialchars($this->getValidUTF8String($nextchallenge["Name"])), formatTime($nextchallenge['AuthorTime']), htmlspecialchars($this->getValidUTF8String($nextchallenge['Author'])), $nextchallenge["Environnement"], $nextchallenge["Mood"]);
      $page = str_replace($ncSearches, $ncReplaces, $page);
    }

    //replace the placeholders in the xml blocks with content
    $attime_replace = !IN_MPASECO ? formatTime($challenge->authortime) : $challenge->environment;
    $page = str_replace(array("%manialinksid%", "%widgetid%", "%widgetpos%", "%trackname%", "%bronzetime%", "%silvertime%", "%goldtime%", "%authortime%", "%author%", "%linetwostyle%", "%linetwosubstyle%"),
    array($this->manialinksID,  $this->challengeWidgetID, $this->settings["challengewidget"]["states"][$state]["pos"], htmlspecialchars($this->getValidUTF8String($challenge->name)), formatTime($challenge->bronzetime), formatTime($challenge->silvertime), formatTime($challenge->goldtime), $attime_replace, htmlspecialchars($this->getValidUTF8String($challenge->author)), !IN_MPASECO ? 'BgRaceScore2' : 'Icons128x128_1', !IN_MPASECO ? 'ScoreReplay' : 'Advanced'), $page);
    if ($login){
      $this->sendMLtoLogin($login, $page, 'Sending map widget', 'cw');
    } else {
      $this->sendML($page, 'Sending map widget', 'cw');
    }
  }

  /**
   * Reset the Clock widget
   */
  function resetClockWidget(){
    $this->localTime="";
  }

  /**
   * Reset the Karma widget
   */
  function resetKarmaWidget(){
    $this->lastShownKarma=-1;
    $this->karmaHashs = array();
  }

  /**
   * Hide the Local Records widget and the Live Rankings widget
   */
  function hideRecordsWidgets($login=''){

    if (!$login){
      $this->localRecordsHashs = array();
      $this->liveRankingHashs = array();
      $this->dedimaniaHashs = array();
      $this->localRecordsStaticHash = -1;
      $this->liveRankingStaticHash = -1;
      $this->dedimaniaStaticHash = -1;
      if (!$this->settings['sblwidget']['enabled']){
        $this->hideWidget(array($this->closeToYouWidgetID, $this->rankingWidgetID, $this->dedimaniaWidgetID));
      }
    } else {
      $this->hideWidget(array($this->closeToYouWidgetID, $this->rankingWidgetID, $this->dedimaniaWidgetID), $login);
    }

  }

  /**
   * Hides the widgets that are disabled in High Performance Mode
   *
   */
  function hideWidgetsForHPM(){
    $ids = array();
    if (!$this->settings['hpm']['widgets']['localrecords']){
      $ids[] = $this->closeToYouWidgetID;
      $this->localRecordsHashs = array();
    }
    if (!$this->settings['hpm']['widgets']['liverankings']){
      $ids[] = $this->rankingWidgetID;
      $this->liveRankingHashs = array();
    }
    if (!$this->settings['hpm']['widgets']['dedimania']){
      $ids[] = $this->dedimaniaWidgetID;
      $this->dedimaniaHashs = array();
    }
    if (count($ids)!=0){
      if (count($ids)==1){
        $this->hideWidget($ids[0]);
      } else {
        $this->hideWidget($ids);
      }
    }
  }

  /**
   * Hide the Ad widget
   */
  function hideAdWidget(){
    if ($this->racing){
      //hide score ads
      $this->hideWidget($this->adWidgetScoreID);
    } else {
      //hide race ads
      $this->hideWidget($this->adWidgetRaceID);
    }
  }

  /**
   * Hide a widget by its ID (send an empty one)
   *
   * @param mixed $id(s)
   */
  function hideWidget($id, $login=''){

    $page = '';
    if (count($id)>1){
      foreach ($id as $mlid){
        $page.='<manialink id="'.$mlid.'"></manialink>';
      }
    } else {
      $page.='<manialink id="'.$id.'"></manialink>';
    }

    $page = '<?xml version="1.0" encoding="UTF-8"?>
          <manialinks id="'.$this->manialinksID.'">
              '.$page.'
            </manialinks>
         ';   
    if ($login){
      $this->sendMLtoLogin($login, $page, 'Sending hide widget', 'hw');
    } else {
      $this->sendML($page, 'Sending hide widget', 'hw');
    }
  }

  function showSBLWidget($login=''){
    if (!$this->settings['sblwidget']['enabled']) return;

    $xml='<?xml version="1.0" encoding="UTF-8"?>
          <manialinks id="'.$this->manialinksID.'">
              <manialink id="'.$this->rankingWidgetID.'"></manialink>
              <manialink id="'.$this->dedimaniaWidgetID.'"></manialink>
              <manialink id="'.$this->closeToYouWidgetID.'">';

    $xmlcontent='';
    $entry = $this->settings['sblwidget']['blocks']['entry'];
    $window = $this->settings['sblwidget']['blocks']['window'];
    $titlebar = $this->settings['sblwidget']['blocks']['title'];

    foreach ($this->settings['sblwidget']['lists'] as $list){
      $entryList = call_user_method('get'.$list['content'].'List', $this, $list['entrycount']);
      if ($entryList){
        $showPoints = $this->getShowPoints($list['content']);
        $xmlcontent.='<frame posn="'.$list['pos'].'">';
        $xmlcontent.=str_replace(array('%width%', '%height%'), array($list['width'], 2*$list['entrycount']+2), $window);
        $xmlcontent.=str_replace(array('%titlepos%', '%widgettitle%'), array($list['width']/2+$list['titleoffsetx'], $list['title']), $titlebar);

        $ctuArray = $this->getStaticRecordsArray($entryList, $list['entrycount']);

        for ($i=0;$i<count($ctuArray); $i++){
          $xmlcontent.= str_replace('%nickwidth%', $list["width"]-8.5, $this->getCTUEntry($ctuArray[$i], $i, '', $entry, $showPoints, $list['width'], 0));
        }
        $xmlcontent.='</frame>';
      }
    }
    $xml.=$xmlcontent;
    $xml.='</manialink></manialinks>';
    if ($login){
      $this->sendMLtoLogin($login, $xml, 'Sending Scorboardlists', 'sb');
    } else {
      $this->sendML($xml, 'Sending Scoreboardlists', 'sb');
    }

  }

  function getMostHitsList($limit=50){
    $query='SELECT Login, Nickname, Hits FROM players ORDER BY Hits DESC LIMIT '.$limit;
    $res = mysql_query($query);
    $rankings = array();
    while ($row = mysql_fetch_object($res)) {
      $player = new CTUPlayer($row->Nickname, $row->Login);
      $ranking['player'] = $player;
      $ranking['score'] = $row->Hits;
      $rankings[] = $ranking;
    }
    return $rankings;
  }
  
  function getMostCapturesList($limit=50){
    $query='SELECT Login, Nickname, Captures FROM players ORDER BY Captures DESC LIMIT '.$limit;
    $res = mysql_query($query);
    $rankings = array();
    while ($row = mysql_fetch_object($res)) {
      $player = new CTUPlayer($row->Nickname, $row->Login);
      $ranking['player'] = $player;
      $ranking['score'] = $row->Captures;
      $rankings[] = $ranking;
    }
    return $rankings;
  }
  
   function getMostNearMissesList($limit=50){
    $query='SELECT Login, Nickname, NearMisses FROM players ORDER BY NearMisses DESC LIMIT '.$limit;
    $res = mysql_query($query);
    $rankings = array();
    while ($row = mysql_fetch_object($res)) {
      $player = new CTUPlayer($row->Nickname, $row->Login);
      $ranking['player'] = $player;
      $ranking['score'] = $row->NearMisses;
      $rankings[] = $ranking;
    }
    return $rankings;
  }
  
  function getMostSurvivalsList($limit=50){
    $query='SELECT Login, Nickname, Survivals FROM players ORDER BY Survivals DESC LIMIT '.$limit;
    $res = mysql_query($query);
    $rankings = array();
    while ($row = mysql_fetch_object($res)) {
      $player = new CTUPlayer($row->Nickname, $row->Login);
      $ranking['player'] = $player;
      $ranking['score'] = $row->Survivals;
      $rankings[] = $ranking;
    }
    return $rankings;
  } 

    function getMostDeathsList($limit=50){
    $query='SELECT Login, Nickname, Deaths FROM players ORDER BY Deaths DESC LIMIT '.$limit;
    $res = mysql_query($query);
    $rankings = array();
    while ($row = mysql_fetch_object($res)) {
      $player = new CTUPlayer($row->Nickname, $row->Login);
      $ranking['player'] = $player;
      $ranking['score'] = $row->Deaths;
      $rankings[] = $ranking;
    }
    return $rankings;
  }
  
  function getWeeklyPointsList($limit=50){
    global $aseco;
    $year= date("Y");
    $week= date("W");  
           
    $query = 'SELECT PlayerId, WeeklyPoints FROM rank_weekly WHERE Year='.$year.' AND Week='.$week.' ORDER BY WeeklyPoints DESC LIMIT '.$limit;  
    $res = mysql_query($query);
    $rankings = array();
    while ($row = mysql_fetch_object($res)) {
      $querya = 'SELECT Login, NickName FROM players WHERE Id='.$row->PlayerId;
      $result = mysql_query($querya);
      if (mysql_num_rows($result) > 0) {
        $rowa = mysql_fetch_row($result);       
      } 
              
      $player = new CTUPlayer($rowa[1], $rowa[0]);
      $ranking['player'] = $player;
      $ranking['score'] = $row->WeeklyPoints;
      $rankings[] = $ranking;
    }
    return $rankings;
  }
 
  function getMonthlyPointsList($limit=50){
    global $aseco;
    $year= date("Y");
    $month= date("m"); 
           
    $query = 'SELECT PlayerId, MonthlyPoints FROM rank_monthly WHERE Year='.$year.' AND Month='.$month.' ORDER BY MonthlyPoints DESC LIMIT '.$limit;  
    $res = mysql_query($query);
    $rankings = array();
    while ($row = mysql_fetch_object($res)) {
      $querya = 'SELECT Login, NickName FROM players WHERE Id='.$row->PlayerId;
      $result = mysql_query($querya);
      if (mysql_num_rows($result) > 0) {
        $rowa = mysql_fetch_row($result);       
      } 
      
      $player = new CTUPlayer($rowa[1], $rowa[0]); //Nick/login
      $ranking['player'] = $player;
      $ranking['score'] = $row->MonthlyPoints;
      $rankings[] = $ranking;
    }                
    return $rankings;
  } 

  function getTopDonsList($limit=50){
    global $aseco;

    $query = 'SELECT p.NickName, p.Login, x.Donations FROM players p
              LEFT JOIN players_extra x ON (p.Id=x.PlayerId)
              WHERE x.Donations!=0 ORDER BY x.Donations DESC LIMIT ' . $limit;
    $res = mysql_query($query);
    
      
    $rankings = array();
    while ($row = mysql_fetch_object($res)) {
         
      $player = new CTUPlayer($row->NickName, $row->Login);
      $ranking['player'] = $player;
      $ranking['score'] = $row->Donations;
      $rankings[] = $ranking;   
    }
    return $rankings;
  } 
                   
  function getAllPointsList($limit=50){
    $query='SELECT Login, Nickname, AllPoints FROM players ORDER BY AllPoints DESC LIMIT '.$limit;
    $res = mysql_query($query);
    $rankings = array();
    while ($row = mysql_fetch_object($res)) {
      $player = new CTUPlayer($row->Nickname, $row->Login);
      $ranking['player'] = $player;
      if($row->AllPoints < 10000)
        $ranking['score'] = $row->AllPoints;
      elseif($row->AllPoints < 100000)
        $ranking['score'] = round($row->AllPoints / 1000,1).'k';
      elseif($row->AllPoints < 1000000)
        $ranking['score'] = round($row->AllPoints / 1000,0).'k';      
      else
        $ranking['score'] = round($row->AllPoints / 1000000,2).'M';
                
      $rankings[] = $ranking;
    }
    return $rankings;
  }
    
  function getMostRespawnsList($limit=50){
    $query='SELECT Login, Nickname, Respawns FROM players ORDER BY Respawns DESC LIMIT '.$limit;
    $res = mysql_query($query);
    $rankings = array();
    while ($row = mysql_fetch_object($res)) {
      $player = new CTUPlayer($row->Nickname, $row->Login);
      $ranking['player'] = $player;
      $ranking['score'] = $row->Respawns;
      $rankings[] = $ranking;
    }
    return $rankings;
  }
  
  function getMostRecsList($limit=50){
    $query='select p.Login, p.Nickname, count(p.Id) as Count from records r inner join players p on r.PlayerId = p.Id group by p.Id order by count desc limit '.$limit;
    $res = mysql_query($query);
    $rankings = array();
    while ($row = mysql_fetch_object($res)) {
      $player = new CTUPlayer($row->Nickname, $row->Login);
      $ranking['player'] = $player;
      $ranking['score'] = $row->Count;
      $rankings[] = $ranking;
    }
    return $rankings;
  }

  function getTopPlaytimeList($limit=50){
    $query = 'SELECT Login, NickName, TimePlayed FROM players ORDER BY TimePlayed DESC LIMIT '.$limit;
    $res = mysql_query($query);
    $rankings = array();
    while ($row = mysql_fetch_object($res)) {
      $player = new CTUPlayer($row->NickName, $row->Login);
      $ranking['player'] = $player;
      $ranking['score'] = round($row->TimePlayed / 3600).' h';
      $rankings[] = $ranking;
    }
    return $rankings;
  }

  function getTopWinnersList($limit=50){
    $query = 'SELECT Login, NickName, Wins FROM players ORDER BY Wins DESC LIMIT '.$limit;
    $res = mysql_query($query);
    $rankings = array();
    while ($row = mysql_fetch_object($res)) {
      $player = new CTUPlayer($row->NickName, $row->Login);
      $ranking['player'] = $player;
      $ranking['score'] = $row->Wins;
      $rankings[] = $ranking;
    }
    return $rankings;
  }

  function getTopRanksList($limit=50){
    $query = 'SELECT p.Login, p.NickName, r.avg FROM players p LEFT JOIN rs_rank r ON (p.Id=r.PlayerId) WHERE r.avg != 0 order by r.avg DESC LIMIT '.$limit;
    $res = mysql_query($query);
    $rankings = array();
    while ($row = mysql_fetch_object($res)) {
      $player = new CTUPlayer($row->NickName, $row->Login);
      $ranking['player'] = $player;
      $ranking['score'] = round($row->avg/1000) / 10;
      if ($ranking['score'] == round($ranking['score'])) $ranking['score'] = $ranking['score'].'.0';
      $rankings[] = $ranking;
    }
    return $rankings;
  }

  function getLiveRanksList(){
    if (!IN_MPASECO) {
      $gamemode = $this->Aseco->server->gameinfo->mode;
      $this->Aseco->client->resetError();
      
   /*   if($gamemode == 0) { // TODO
        return array('Login' => 'todo', 'NickName' => 'Todo', 'PlayerId' => 10, 'Rank' => 1, 'BestTime' => 1, 'Score' => 10, 'NbrLapsFinished' => 1 , 'LadderScore' => 10);
      } */
      
      $this->Aseco->client->query("GetCurrentRanking", 256,0);

      if ($this->Aseco->client->isError()){
        $this->Aseco->console("FufiWidgets: LiveRankingsError, reverting to last valid ranking ".$this->Aseco->client->getErrorMessage());
        if (!$this->oldRanking) $this->oldRanking = array();
        $rankings = $this->oldRanking;
      } else {
        $rankings = $this->Aseco->client->getResponse();
        $this->oldRanking = $rankings;
      }
      $rankings_= array();

      for ($i=0; $i<count($rankings); $i++){
        if ($rankings[$i]["BestTime"]==0) $rankings[$i]["BestTime"]= $rankings[$i]["Score"];
        if ($rankings[$i]["BestTime"]>0){

          $player = new CTUPlayer($rankings[$i]["NickName"], $rankings[$i]["Login"]);

          $ranking = array();
          $ranking["player"] = $player;
          $ranking["score"] = $rankings[$i]["BestTime"];
          if ($gamemode==1 || $gamemode==6 || $gamemode==5){
            $ranking["score"] = $rankings[$i]["Score"];
          }

          $rankings_[]= $ranking;
        }
      }

      $rankings = $rankings_;
    } else {
    if ($this->records_active && $this->records_type != "Points" ) {
      if (!isset($this->liveRankings)) $this->liveRankings = array();
      
      $keys = array();
      foreach ($this->liveRankings as $key => $row) {
         $keys[$key] = $row['score'];
      }
      array_multisort($keys, SORT_ASC, $this->liveRankings);

      return $this->liveRankings;
    } else {
      foreach($this->smrankings as $login => $score) {
        $nickname = isset($this->Aseco->server->players->player_list[$login]->nickname) ? $this->Aseco->server->players->player_list[$login]->nickname : $login;
        $rankings_[] = array('player' => new CTUPlayer($nickname, $login), 'score' => $score);
      }
      $rankings = $rankings_;
    }
    }
    return $rankings;
  }

  function getDediRecsList(){
    if (IN_XASECO){
      global $dedi_db;
    } else {
      $dedi_db = $this->Aseco->getPlugin('DediMania')->dedi_db;
    }

    if (isset($dedi_db['Challenge']['Records'])) $dedi_recs = $dedi_db['Challenge']['Records']; else return false;

    $dediRecs_ = array();
    if ($dedi_recs != NULL){
      for ($i=0; $i<count($dedi_recs); $i++){
        if ($dedi_recs[$i]["Best"]>0){

          $player = new CTUPlayer($dedi_recs[$i]["NickName"], $dedi_recs[$i]["Login"]);
          $ranking = array();
          $ranking["player"] = $player;
          $ranking["score"] = $dedi_recs[$i]["Best"];

          $dediRecs_[]= $ranking;
        }
      }
    } else {
      return false;
    }
    $dediRecs = $dediRecs_;
    return $dediRecs;
  }

  function getLocalRecsList(){
    $records = array();

    if (IN_XASECO){
      $recsAseco = $this->Aseco->server->records->record_list;
      foreach ($recsAseco as $recAseco){
        $rec = array();
        $recplayer = $recAseco->player;
        $rec["player"] = new CTUPlayer($recplayer->nickname, $recplayer->login);
        $rec["score"] = $recAseco->score;
        $records[]=$rec;
      }
    } else {
      for ($i=0; $i<$this->Aseco->records->count(); $i++){
        $records[$i] = array();
        $rec = $this->Aseco->records->getRecord($i);
        $recplayer =  $rec->player;
        $records[$i]["player"] = new CTUPlayer($recplayer->nickname, $recplayer->login);
        $records[$i]["score"] = $rec->score;
      }
    }
    return $records;
  }

  function getShowPoints($content){
    $gamemode = $this->Aseco->server->gameinfo->mode;
    $showPoints = false;
    switch ($content){
      case 'LocalRecs':
        if ($gamemode==6) $showPoints = true; break;
      case 'LiveRanks':
        if ($gamemode==1 || $gamemode==6 || $gamemode==5)
        $showPoints = true; break;
      case 'TopRanks':
        $showPoints = true; break;
      case 'TopWinners':
        $showPoints = true; break;
      case 'TopPlaytime':
        $showPoints = true; break;      
      case 'MostRecs':
        $showPoints = true; break;
      case 'MostHits':
      case 'MostCaptures':
      case 'MostSurvivals':
	  case 'MostNearMisses':      
      case 'MostDeaths':   
      case 'TopDons':               
      case 'WeeklyPoints':   
      case 'MonthlyPoints':            
      case 'MostRespawns':
      case 'AllPoints':       
        $showPoints = true; break;
      default: break;
    }
    return $showPoints;
  }

  function hideSBLWidget(){
    if ($this->settings['sblwidget']['enabled']){
      $this->hideWidget($this->scoreBoardListsID);
    }
  }

  /**
   * Show the widgets for the racing state to all or a specific player
   *
   * @param String $login
   */
  function showRaceWidgets($login=''){
    $this->settings["localrecordswidget"]["needsUpdate"] = true;
    $this->settings["karmawidget"]["needsUpdate"] = true;

    $this->showChallengeInfo($login);
    if ($this->hpmactive && $this->settings['hpm']['static']){
      $this->showRecordsWidgets(false, $login);
    } else {
      $this->showRecordsWidgets();
    }
    $this->showKarmaWidget($login);
    $this->showClockWidget();
    $this->showAdWidget($login);
    $this->showKeyWidget($login);
  }

  /**
   * hides all widgets for the racing state
   */
  function hideRaceWidgets(){

    //resetting hash values
    $this->resetKarmaWidget();
    $this->resetClockWidget();
    $this->settings["localrecordswidget"]["needsUpdate"] = false;
    $this->settings["karmawidget"]["needsUpdate"] = false;

    $this->hideRecordsWidgets();
    $this->hideAdWidget();
    $widgetsToHide = array();
    $widgetsToHide[] = $this->clockWidgetID;
    if (!$this->settings['karmawidget']['states'][7]['enabled']){
      $widgetsToHide[] = $this->karmaWidgetID;
      $widgetsToHide[] = $this->karmaWidgetID.'000';
    }
    if (!$this->settings['nexttrackwidget']['enabled']) $widgetsToHide[]=$this->challengeWidgetID;

    if (count($widgetsToHide) == 1) $widgetsToHide = $widgetsToHide[0];
    $this->hideWidget($widgetsToHide);

  }

  /**
   * hides the nexttrackwidget, if necessary
   */
  function hideNextTrackWidget(){
    if (!$this->settings['challengewidget']['enabled']){
      $this->hideWidget($this->nextTrackWidgetID);
    }
  }


  /**
   * Show the widgets for the scoreboard state to all or a specific player
   *
   * @param String $login
   */
  function showScoreWidgets($login=''){
    $this->showAdWidget($login);
    $this->showKeyWidget($login);
    $this->showNextTrackWidget($login);
    $this->settings['karmawidget']['needsUpdate']=true;
    $this->showKarmaWidget($login);
    $this->showSBLWidget($login);
  }

  /**
   * hides all widgets for the scoreboard state
   */
  function hideScoreWidgets(){
    $this->resetKarmaWidget();
    $this->hideAdWidget();
    $this->hideNextTrackWidget();
    $this->hideSBLWidget();

    $gamemode = $this->Aseco->server->gameinfo->mode;
    if (!($this->settings['karmawidget']['states'][$gamemode]['enabled'] && $this->settings['karmawidget']['enabled'])){
      $this->hideWidget(array($this->karmaWidgetID, $this->karmaWidgetID.'000'));
    }
  }

  /**
   * Is Called on StatusChangeTo3 (needed for some game modes to emulate the new challenge)
   */

  function doStatusChangeTo3(){
    $gamemode = $this->Aseco->server->gameinfo->mode;
    if ($this->Aseco->debug) $this->Aseco->Console("[FufiWidgets] StatusChangeTo3");
    if ($this->actOnStatusChange || $this->firstStatusChange){
      if ($gamemode == 1 || $gamemode==3 || $gamemode==5)
      $this->executeNewChallenge(false);
    }
    $this->actOnStatusChange =  true;
    $this->firstStatusChange = false;

  }

  /**
   * Is called on newChallenge event, initializes the racing state
   * I need this function to strip out the parameters that come from Aseco
   */
  function doNewChallenge(){
    if ($this->Aseco->debug) $this->Aseco->console('NewMap');
    $this->executeNewChallenge();
  }

  /**
   * Is called on newChallenge event and eventually on Status change to 3, initializes the racing state
   */
  function executeNewChallenge($resetUIHashs = true){

    if ($resetUIHashs){
      $this->karmaHashs = array();
      $this->localRecordsHashs = array();
      $this->liveRankingHashs = array();
      $this->dedimaniaHashs = array();
      $this->localRecordsStaticHash = -1;
      $this->liveRankingStaticHash = -1;
      $this->dedimaniaStaticHash = -1;
    }

    if ($this->Aseco->debug){
      $this->Aseco->console('[FufiWidgets] Last Round Stats:');
      $this->Aseco->console('[FufiWidgets] ChallengeW:  '.intval($this->debugVars['cwsdmp']).' SDMP & '.intval($this->debugVars['cwsdmptl']).' SDMPTL');
      $this->Aseco->console('[FufiWidgets] KarmaW:      '.intval($this->debugVars['kwsdmp']).' SDMP & '.intval($this->debugVars['kwsdmptl']).' SDMPTL');
      $this->Aseco->console('[FufiWidgets] RecordW:     '.intval($this->debugVars['rwsdmp']).' SDMP & '.intval($this->debugVars['rwsdmptl']).' SDMPTL');
      $this->Aseco->console('[FufiWidgets] AdW:         '.intval($this->debugVars['awsdmp']).' SDMP & '.intval($this->debugVars['awsdmptl']).' SDMPTL');
      $this->Aseco->console('[FufiWidgets] ClockW:      '.intval($this->debugVars['clsdmp']).' SDMP & '.intval($this->debugVars['clsdmptl']).' SDMPTL');
      $this->Aseco->console('[FufiWidgets] KeyW:        '.intval($this->debugVars['kysdmp']).' SDMP & '.intval($this->debugVars['kysdmptl']).' SDMPTL');
      $this->Aseco->console('[FufiWidgets] NextTrack:   '.intval($this->debugVars['ntsdmp']).' SDMP & '.intval($this->debugVars['ntsdmptl']).' SDMPTL');
      $this->Aseco->console('[FufiWidgets] Hidden:      '.intval($this->debugVars['hwsdmp']).' SDMP & '.intval($this->debugVars['hwsdmptl']).' SDMPTL');

      $sumsdmp = $this->debugVars['ntsdmp'] + $this->debugVars['cwsdmp']+$this->debugVars['kwsdmp']+$this->debugVars['rwsdmp'] + $this->debugVars['awsdmp']+ $this->debugVars['kysdmp']+$this->debugVars['clsdmp']+$this->debugVars['hwsdmp'];
      $sumsdmptl = $this->debugVars['ntsdmptl'] + $this->debugVars['cwsdmptl']+$this->debugVars['kwsdmptl']+$this->debugVars['rwsdmptl'] + $this->debugVars['awsdmptl']+ $this->debugVars['kysdmptl']+$this->debugVars['clsdmptl']+$this->debugVars['hwsdmptl'];;
      $this->Aseco->console('[FufiWidgets] Sum:         '.$sumsdmp.' SDMP & '.$sumsdmptl.' SDMPTL');

      $this->debugVars['totalsdmp'] += $sumsdmp;
      $this->debugVars['totalsdmptl'] += $sumsdmptl;

      //clear
      foreach($this->debugVars as $key => $value){
        if ($key!='totalsdmp' && $key!='totalsdmptl'){
          $this->debugVars[$key] = 0;
        }
      }
      $this->Aseco->console('[FufiWidgets] Since Start: '.intval($this->debugVars['totalsdmp']).' SDMP & '.intval($this->debugVars['totalsdmptl']).' SDMPTL');

    }

    if (!$this->firstChallengeLoaded){
      $this->firstChallengeLoaded = true;
    }
    $this->actOnStatusChange = false;

    if (IN_XASECO){
      $this->Aseco->client->query('GetCurrentMapInfo', array());
      $challenge = $this->Aseco->client->getResponse();
      $this->xasecoChallenge = new map($challenge);
      
      $query = 'select Id from maps where Uid ="'.$this->xasecoChallenge->uid.'"';
      $res = mysql_query($query);
      if (mysql_num_rows($res) == 1){
        $this->xasecoChallenge->id = mysql_result($res, 0, 'Id');
      }

    }

    if ($this->settings["karmawidget"]["enabled"]){
      if (IN_XASECO){
        if (function_exists('getKarmaValues')){
          $this->karmaStats = getKarmaValues($this->xasecoChallenge->id);
        }
      } else {
        $raspKarma = $this->Aseco->getPlugin('RaspKarma');
        $this->karmaStats = $raspKarma->getKarmaValues($this->Aseco->server->challenge);
      }
    }

    if (!$this->racing){
      $this->racing = true;
      $this->hideScoreWidgets();
      $this->showRaceWidgets();
    }

  }

  /**
   * Is called on endRace event, initializes the scoreboard widgets
   */
  function doEndRace(){
    if (isset($this->liveRankings)) unset($this->liveRankings);
    if ($this->Aseco->debug) $this->Aseco->Console("[FufiWidgets] onEndRace");
    $this->racing = false;
    $this->hideRaceWidgets();
    $this->showScoreWidgets();
  }
    
    /**
     * Searchs for updates
     */
   function search_update(){
        $current = trim(file_get_contents('http://xaseco.maniactwister.de/fufi/widgets/version.txt'));
        if (!empty($current) && $current != -1 && $current > $this->widgetsVersion) {
          return formatText('{#server}>> {#message}New Fufi Widgets version {#highlite}{1}{#message} available from {#highlite}{2}', $current, '$L[http://www.tm-forum.com/viewtopic.php?f=127&t=28763]TM-Forum');
        }
        return false;
    }
    
  /**
   * Is called, when a player connects and initializes his widgets
   *
   * @param mixed $command
   */
  function doPlayerConnect($command){
    $this->showRecordsWidgetsToLogin[$command->login] = true;
    if (IN_XASECO){

    } else {
      if ($this->settings["challengewidget"]["enabled"]) $command->framework->setUIChallengeInfo(false);
    }
    if ($this->firstChallengeLoaded){
      if ($this->racing){
        $this->showRaceWidgets($command->login);
      } else {
        $this->showScoreWidgets($command->login);
      }
    }
  }

  /**
   * Is called, when a player disconnects and deletes his hashs
   *
   * @param mixed $command
   */
  function doPlayerDisconnect($command){
    unset($this->showRecordsWidgetsToLogin[$command->login]);
    unset($this->localRecordsHashs[$command->login]);
    unset($this->liveRankingHashs[$command->login]);
    unset($this->dedimaniaHashs[$command->login]);
    unset($this->karmaHashs[$command->login]);
  }

  /**
   * This one handles keypresses
   *
   * @param unknown_type $login
   * @param unknown_type $key
   */
  function handleKeyPress($login, $key){
    $this->toggleRecordWidgets($login);
  }

  /**
   * this one toggles the display of the record widgets for a player
   *
   * @param unknown_type $login
   */
  function toggleRecordWidgets($login){

    if ($this->hpmactive && $this->settings['hpm']['static']){
      $this->Aseco->addCall('ChatSendServerMessageToLogin', array($this->settings['togglingdisabled'], $login));
    } else {
      if (isset($this->showRecordsWidgetsToLogin[$login])){
        $this->showRecordsWidgetsToLogin[$login] = !$this->showRecordsWidgetsToLogin[$login];
      } else {
        $this->showRecordsWidgetsToLogin[$login] = false;
      }

      if (!$this->showRecordsWidgetsToLogin[$login]){
        $this->hideRecordsWidgets($login);
        $this->Aseco->addCall('ChatSendServerMessageToLogin', array($this->settings['recordwidgetsdisabled'], $login));
      } else {
        unset($this->localRecordsHashs[$login]);
        unset($this->liveRankingHashs[$login]);
        unset($this->dedimaniaHashs[$login]);
        $this->settings['localrecordswidget']['needsUpdate'] = true;
        $this->settings['localrecordswidget']['forceUpdate'] = true;
        $this->Aseco->addCall('ChatSendServerMessageToLogin', array($this->settings['recordwidgetsenabled'], $login));
      }
    }
  }

  /**
   * Handles mouse clicks on the widgets
   *
   * @param mixed $command
   */
  function doHandleClick($command){

    $action = $command[2].'';
    if (substr($action, 0, strlen($this->manialinksID)) == $this->manialinksID){

      $action = substr($action, strlen($this->manialinksID));
      $recipient = substr($action, 0, 3);
      $action = substr($action, 3);

      if ($recipient=="001"){
        $this->showChallengeInfo($command[1], $action);
      } else if ($recipient=="003"){
        $this->executeKarma($command[1], $action);
      } else if ($recipient=='009'){
        $this->handleKeyPress($command[1], intval($action));
      }

    }
  }

  /**
   * Reacts onMainLoop (needed for the clock and the manialink updates)
   */
  function doMainLoop(){
    if ($this->racing){
      $this->showClockWidget();
      //update records if necessary
    }
    $time = time();
    if ($this->racing || ($this->settings['karmawidget']['enabled'] && $this->settings['karmawidget']['states'][6]['enabled'])){
      if ($time >= $this->lastKarmaUpdate + $this->updateInterval){
        $this->lastKarmaUpdate = $time;
        $this->showKarmaWidget();
      }
      if ($time >= $this->lastRecordsUpdate + $this->updateInterval + $this->updateInterval/2){
        $this->lastRecordsUpdate = $time;
        $this->showRecordsWidgets(true);
      }

    }
  }


  /**
   * reset all widgets
   *
   */
  function resetWidgets(){

    $this->localRecordsHashs = array();
    $this->liveRankingHashs = array();
    $this->dedimaniaHashs = array();

    $this->localRecordsStaticHash = -1;
    $this->liveRankingStaticHash = -1;
    $this->dedimaniaStaticHash = -1;

    $this->lastShownKarma=-1;
    $this->karmaHashs = array();

    $this->hideScoreWidgets();
    $this->hideRaceWidgets();

    foreach ($this->Aseco->server->players->player_list as $player){
      $this->doPlayerConnect($player);
    }

  }
  /**
   * Reacts onGameModeChange (needed for laying out the widgets)
   */
  function doGameModeChange(){
    $this->resetWidgets();
  }

  /**
   * Reacts onEndRound (used for the switching between the states)
   */
  function doEndRound(){
    if ($this->Aseco->server->gameinfo->mode == 1 || $this->Aseco->server->gameinfo->mode == 0){
      $this->showRecordsWidgets();
    }
  }
  
  function doBeginRound(){
    if ($this->Aseco->server->gameinfo->mode == 1 || $this->Aseco->server->gameinfo->mode == 0){
      $this->showRecordsWidgets();
    }
  }
  
  /**
   * Reacts onPlayerFinish (refreshes the Close2You widgets if needed)
   */
  function doPlayerFinish($command) {
    if ($this->records_active) {
      if (!isset($this->liveRankings)) $this->liveRankings = array();
          
      // delete old ranking and insert new one
      $firstRun = true;
      foreach ($this->liveRankings as $key => $ranking) {
        if ($ranking['player']->login == $command->player->login) {
          $firstRun = false;
          if ($ranking['score'] > $command->score) {
            unset($this->liveRankings[$key]);
            // add new record
            array_push($this->liveRankings, array('player' => $command->player, 'score' => $command->score));
          }
          break;
        }
      }
             //   var_dump($this->liveRankings);
      if ($firstRun) {
        // add new record
        array_push($this->liveRankings, array('player' => $command->player, 'score' => $command->score));
      }
    } else {
      if (IN_XASECO) if ($command->score==0) return;
    }
    $this->settings["localrecordswidget"]["needsUpdate"] = true;
    $this->showRecordsWidgets(true);
  }

  function doKarmaChange($karmaStats){
    if ($this->Aseco->debug) $this->Aseco->Console("[FufiWidgets] doKarmaChange");

    $this->karmaStats = $karmaStats;
    $this->settings["karmawidget"]["needsUpdate"] = true;
  }

  function doInitMenu($menu){
    $menu->addEntry('custommenu', '', true, 'Show Fufi Widgets', 'showfufiwidgets', '/togglewidgets', '', '', 'getHideWidgetsIndicator');
  }

  function getHideWidgetsIndicator($login){
    return $this->showRecordsWidgetsToLogin[$login];
  }

  function chatToggle($command){
    $this->toggleRecordWidgets($command['author']->login);
  }
}

// Initialization of the FufiWidgets


if (IN_XASECO){

  global $fufiWidgets;
  $fufiWidgets = new FufiWidgets();
  $fufiWidgets->init();
  $fufiWidgets->setAuthor('Alexander Peitz');
  $fufiWidgets->setVersion($this->widgetsVersion);
  $fufiWidgets->setDescription('Displays and manages graphical widgets.');

  if ($fufiWidgets->settings["karmawidget"]["enabled"]){
    $fufiWidgets->addDependence("Rasp Karma", 'feature_karma');
  }

  if (!defined('IN_MPASECO') && (in_array('DediRecs', $fufiWidgets->settings["sblwidget"]["used"]) || $fufiWidgets->settings["dedimaniawidget"]["enabled"])){
    $fufiWidgets->addDependence("Dedimania Plugin", 'dedi_timeout');
  }

  if ((in_array('LocalRecs', $fufiWidgets->settings["sblwidget"]["used"])
    || in_array('TopWinners', $fufiWidgets->settings["sblwidget"]["used"])
    || in_array('MostRecs', $fufiWidgets->settings["sblwidget"]["used"])
    || in_array('TopPlaytime', $fufiWidgets->settings["sblwidget"]["used"])
    || in_array('TopRanks', $fufiWidgets->settings["sblwidget"]["used"])) || $fufiWidgets->settings["localrecordswidget"]["enabled"] || $fufiWidgets->settings["karmawidget"]["enabled"]){
    global $ldb_settings;
    $fufiWidgets->addDependence("Local Database", 'ldb_settings');
  }

  Aseco::registerEvent('onStartup', 'fufiwidgets_xasecoStartup');
  function fufiwidgets_xasecoStartup($aseco){
    global $fufiWidgets;
    if (!$fufiWidgets->Aseco){
      $fufiWidgets->Aseco = $aseco;
    }
    if (!$fufiWidgets->records_active){
      $fufiWidgets->records_active = $aseco->settings['records_activated'];
      $fufiWidgets->records_type = $aseco->settings['records_type'];
    } 
    $fufiWidgets->xasecoStartup();
  }


  Aseco::registerEvent('onKarmaChange', 'fufiwidgets_karmaChange');

  function fufiwidgets_karmaChange($aseco, $command){
    global $fufiWidgets;
    $fufiWidgets->doKarmaChange($command);
  }

  Aseco::registerEvent('onEverySecond', 'fufiwidgets_mainLoop');

  function fufiwidgets_mainLoop($aseco, $command){
    global $fufiWidgets;
    $fufiWidgets->doMainLoop($command);
  }

  Aseco::registerEvent('onBeginMap', 'fufiwidgets_newChallenge');

  function fufiwidgets_newChallenge($aseco, $command){
    global $fufiWidgets;
    $fufiWidgets->doNewChallenge();
  }

  Aseco::registerEvent('onStatusChangeTo3', 'fufiwidgets_statusChangeTo3');

  function fufiwidgets_statusChangeTo3($aseco, $command){
    global $fufiWidgets;
    $fufiWidgets->doStatusChangeTo3();
  }

  Aseco::registerEvent('onEndMap', 'fufiwidgets_endRace');

  function fufiwidgets_endRace($aseco, $command){
    global $fufiWidgets;
    $fufiWidgets->doEndRace();
  }

  Aseco::registerEvent('onPlayerConnect', 'fufiwidgets_playerConnect');

  function fufiwidgets_playerConnect($aseco, $command){
    global $fufiWidgets;
   // var_dump($aseco->server->map);
    $fufiWidgets->doPlayerConnect($command);
       /* if($aseco->isMasterAdmin($command) && $fufiWidgets->settings['checkupdate'] && $message=$fufiWidgets->search_update()) $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command->login);   */
  }

  Aseco::registerEvent('onPlayerDisconnect', 'fufiwidgets_playerDisconnect');

  function fufiwidgets_playerDisconnect($aseco, $command){
    global $fufiWidgets;
    $fufiWidgets->doPlayerDisconnect($command);
  }

  Aseco::registerEvent('onPlayerFinish', 'fufiwidgets_playerFinish');

  function fufiwidgets_playerFinish($aseco, $command){
    global $fufiWidgets;
    $fufiWidgets->doPlayerFinish($command);
  }

  Aseco::registerEvent('onPlayerManialinkPageAnswer', 'fufiwidgets_handleClick');

  function fufiwidgets_handleClick($aseco, $command){
    global $fufiWidgets;
    $fufiWidgets->doHandleClick($command);
  }

  //not needed in XASECO
  //Aseco::registerEvent('onGameModeChange', 'fufiwidgets_gameModeChange');

  Aseco::registerEvent('onEndRound', 'fufiwidgets_endRound');

  function fufiwidgets_endRound($aseco, $command){
    global $fufiWidgets;
    if(IN_MPASECO) $fufiWidgets->smrankings = $command;
    $fufiWidgets->doEndRound();
    
  }
  
  if(IN_MPASECO) Aseco::registerEvent('onBeginRound', 'fufiwidgets_beginRound');

  function fufiwidgets_beginRound($aseco, $command){
    global $fufiWidgets;
    $fufiWidgets->smrankings = $command;
    $fufiWidgets->doBeginRound();
  }

  //chat command to toggle the widgets
  Aseco::addChatCommand('togglewidgets', 'Toggle the display of the record widgets');

  function chat_togglewidgets($aseco, $command){
    global $fufiWidgets;
    $fufiWidgets->chatToggle($command);
  }

  //use the fufi menu =)
  Aseco::registerEvent('onMenuLoaded', 'fufiwidgets_initMenu');

  function fufiwidgets_initMenu($aseco, $menu){
    global $fufiWidgets;
    if (!$fufiWidgets->Aseco){
      $fufiWidgets->Aseco = $aseco;
    }
    $fufiWidgets->doInitMenu($menu);
  }
    
    Aseco::registerEvent('onSync', 'fufiwidgets_onSync');
    
    function fufiwidgets_onSync($aseco){
        global $fufiWidgets;
        
        // Register this to the global version pool (for up-to-date checks)
       /*$aseco->plugin_versions[] = array(
          'plugin'   => 'plugin.fufi.widgets.php',
          'author'   => 'f*ckfish / ManiacTwister',
          'version'   => $fufiWidgets->widgetsVersion
       );*/
    }

  function getHideWidgetsIndicator($aseco, $login){
    global $fufiWidgets;
    return $fufiWidgets->getHideWidgetsIndicator($login);
  }


} else { //ASECO BRANCH

  $_PLUGIN = new FufiWidgets();
  $_PLUGIN->init();
  $_PLUGIN->setAuthor('Alexander Peitz');
  $_PLUGIN->setVersion('0.0');
  $_PLUGIN->setDescription('Displays and manages graphical widgets.');


  if ($_PLUGIN->settings["karmawidget"]["enabled"]){
    $_PLUGIN->addDependence("RaspKarma", 1);
    $_PLUGIN->addEvent('onKarmaChange', 'doKarmaChange');
  }

  if ((in_array('LocalRecs', $_PLUGIN->settings["sblwidget"]["used"])
    || in_array('TopWinners', $_PLUGIN->settings["sblwidget"]["used"])
    || in_array('MostRecs', $_PLUGIN->settings["sblwidget"]["used"])
    || in_array('TopPlaytime', $_PLUGIN->settings["sblwidget"]["used"])
    || in_array('TopRanks', $_PLUGIN->settings["sblwidget"]["used"])) ||
    $_PLUGIN->settings["localrecordswidget"]["enabled"] || $_PLUGIN->settings["karmawidget"]["enabled"]){
    $_PLUGIN->addDependence("LocalDb", 1);
  }

  if (in_array('DediRecs', $_PLUGIN->settings["sblwidget"]["used"]) || $_PLUGIN->settings["dedimaniawidget"]["enabled"]){
    $_PLUGIN->addDependence("DediMania", 0.1);
  }

  $_PLUGIN->addEvent('onMainLoop', 'doMainLoop');

  $_PLUGIN->addEvent('onNewChallenge', 'doNewChallenge');
  $_PLUGIN->addEvent('onEndMap', 'doEndRace');
  $_PLUGIN->addEvent('onPlayerConnect', 'doPlayerConnect');
  $_PLUGIN->addEvent('onPlayerDisconnect', 'doPlayerDisconnect');
  $_PLUGIN->addEvent('onPlayerFinish', 'doPlayerFinish');
  $_PLUGIN->addEvent('onPlayerManialinkPageAnswer', 'doHandleClick');
  $_PLUGIN->addEvent('onGameModeChange', 'doGameModeChange');
  $_PLUGIN->addEvent('onEndRound', 'doEndRound');
  $_PLUGIN->addEvent('onStatusChangeTo3', 'doStatusChangeTo3');

  $_PLUGIN->addChatCommand('togglewidgets', 'chatToggle', 'Toggles the display of the record widgets');

}

?>