<?php
/**
 * rasp_settings
 * Parses the rasp_settings.xml and store the result in the well known rasp settings
 * XML file created and restructured php 2012 by Lukas Kremsmayr
 */
 
$config_file = 'configs/plugins/rasp/rasp_settings.xml'; //Settings XML File

require_once('includes/xmlparser.inc.php');    //XML-Parser

$xml_parser = new Examsly();

if (file_exists($config_file)) {
 // $aseco->console('Load rasp Config [' . $config_file . ']');
  if ($rasp_settings = $xml_parser->parseXml($config_file)){
 
    /***************************** FEATURES **************************************/
    $feature_ranks      = text2bool($rasp_settings['RASP_SETTINGS']['FEATURE_RANKS'][0]);
    $nextrank_show_rp   = text2bool($rasp_settings['RASP_SETTINGS']['NEXTRANK_SHOW_POINTS'][0]);
    $feature_karma      = text2bool($rasp_settings['RASP_SETTINGS']['FEATURE_KARMA'][0]);    
    $allow_public_karma = text2bool($rasp_settings['RASP_SETTINGS']['ALLOW_PUBLIC_KARMA'][0]);    
    $karma_show_start   = text2bool($rasp_settings['RASP_SETTINGS']['KARMA_SHOW_START'][0]);   
    $karma_show_details = text2bool($rasp_settings['RASP_SETTINGS']['KARMA_SHOW_DETAILS'][0]);   
    $karma_show_votes   = text2bool($rasp_settings['RASP_SETTINGS']['KARMA_SHOW_VOTES'][0]);   
    $karma_require_finish   =       $rasp_settings['RASP_SETTINGS']['KARMA_REQUIRE_FINISH'][0];
    $remind_karma           =       $rasp_settings['RASP_SETTINGS']['REMIND_KARMA'][0];
    $feature_jukebox    = text2bool($rasp_settings['RASP_SETTINGS']['FEATURE_JUKEBOX'][0]);   
    $feature_mxadd      = text2bool($rasp_settings['RASP_SETTINGS']['FEATURE_MXADD'][0]);   
    $jukebox_skipleft   = text2bool($rasp_settings['RASP_SETTINGS']['JUKEBOX_SKIPLEFT'][0]);   
    $jukebox_adminnoskip= text2bool($rasp_settings['RASP_SETTINGS']['JUKEBOX_ADMINNOSKIP'][0]);   
    $jukebox_permadd    = text2bool($rasp_settings['RASP_SETTINGS']['JUKEBOX_PERMADD'][0]);   
    $jukebox_adminadd   = text2bool($rasp_settings['RASP_SETTINGS']['JUKEBOX_ADMINADD'][0]);   
    $jukebox_in_window  = text2bool($rasp_settings['RASP_SETTINGS']['JUKEBOX_IN_WINDOW'][0]);   
    $admin_contact          =       $rasp_settings['RASP_SETTINGS']['ADMIN_CONTACT'][0];
    $autosave_matchsettings =       $rasp_settings['RASP_SETTINGS']['AUTOSAVE_MATCHSETTINGS'][0];
    $feature_votes      = text2bool($rasp_settings['RASP_SETTINGS']['FEATURE_VOTES'][0]);   
    $uptodate_check     = text2bool($rasp_settings['RASP_SETTINGS']['UPTODATE_CHECK'][0]);   
    $globalbl_merge     = text2bool($rasp_settings['RASP_SETTINGS']['GLOBALBL_MERGE'][0]);   
    $globalbl_url           =       $rasp_settings['RASP_SETTINGS']['GLOBALBL_URL'][0];
    /* unused Settings in MPAseco: */
    $feature_stats = true;   
    $always_show_pb = true;  
    $prune_records_times = false;    

    /***************************** PERFORMANCE VARIABLES ***************************/
    $minrank = $rasp_settings['RASP_SETTINGS']['MIN_POINTS'][0];    
    /* unused Settings in MPAseco: */
    $maxrecs = 50; 
    $maxavg = 10;
    
    /***************************** JUKEBOX VARIABLES *******************************/  
    $buffersize  = $rasp_settings['RASP_SETTINGS']['BUFFER_SIZE'][0];        
    $mxvoteratio = $rasp_settings['RASP_SETTINGS']['MX_VOTERATIO'][0]; 
    $mxdir       = $rasp_settings['RASP_SETTINGS']['MX_DIR'][0];                
    $mxtmpdir    = $rasp_settings['RASP_SETTINGS']['MX_TMPDIR'][0]; 

    /******************************* IRC VARIABLES *********************************/  
    $CONFIG = array();
    $CONFIG['server'] = $rasp_settings['RASP_SETTINGS']['IRC_SERVER'][0];
    $CONFIG['nick']   = $rasp_settings['RASP_SETTINGS']['IRC_BOTNICK'][0];
    $CONFIG['port']   = $rasp_settings['RASP_SETTINGS']['IRC_PORT'][0];
    $CONFIG['channel']= $rasp_settings['RASP_SETTINGS']['IRC_CHANNEL'][0];
    $CONFIG['name']   = $rasp_settings['RASP_SETTINGS']['IRC_BOTNAME'][0];
    $show_connect     = text2bool($rasp_settings['RASP_SETTINGS']['IRC_SHOW_CONNECT'][0]);  
                                                   
    $linesbuffer = array();
    $ircmsgs = array();
    $outbuffer = array();
    $con = array();
    $jukebox = array();
    $jb_buffer = array();
    $mxadd = array();
    $mxplaying = false;
    $mxplayed = false;    
    
  } else {
  trigger_error('Could not read/parse rasp config file ' . $config_file . ' !', E_USER_WARNING);
  }
} else {
  trigger_error('Could not find rasp config file ' . $config_file . ' !', E_USER_WARNING);
}   
?>
