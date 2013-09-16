<?php
/**
 * Jfreu's plugin 0.20
 * This file is included by jfreu.plugin.php or jfreu.lite.php, so don't
 * list it in plugins.xml!
 * XML file created and restructured php (2012 by Lukas Kremsmayr)
 * v23.12.2012 
 */

 /* Please don't make any changes in this file!!
   Please make all your changes in the following file:
   configs/plugins/jfreu/jfreu.messages.xml */
   
  global $feature_votes;
  
  //-> paths to config, vip/vip_team & bans files
  $conf_file     = 'configs/plugins/jfreu/jfreu.config.xml';
  $vips_file     = 'configs/plugins/jfreu/jfreu.vips.xml';
  $bans_file     = 'configs/plugins/jfreu/jfreu.bans.xml';
  $message_file  = 'configs/plugins/jfreu/jfreu.messages.xml'; 
   
  if (file_exists($message_file)) {
    $aseco->console('Load jfreu message file [' . $message_file . ']');
    if ($xml = $aseco->xml_parser->parseXml($message_file)) {
 
    //-> player join/leave messages	
    $player_join  = $xml['JFREU_MESSAGES']['PLAYER_JOIN'][0];
    $player_joins = $xml['JFREU_MESSAGES']['PLAYER_JOINS'][0];
    $player_left  = $xml['JFREU_MESSAGES']['PLAYER_LEFT'][0];
	
   	//-> prefix for info messages
    $message_start = $xml['JFREU_MESSAGES']['MESSAGE_START'][0];   	

    $i=1;
    foreach ($xml['JFREU_MESSAGES']['INFO_MESSAGES'][0]['MESSAGE'] as $message) {
      ${'message'.$i} = $message; 
      $i++;
    }

    if ($feature_votes) {
      foreach ($xml['JFREU_MESSAGES']['FEATURED_VOTE_MESSAGES'][0]['MESSAGE'] as $message) {
        ${'message'.$i} = $message; 
        $i++;  
      }
    }
    if (function_exists('send_window_message')) {
      ${'message'.$i} = $xml['JFREU_MESSAGES']['SEND_WINDOW_MESSAGE'][0];   	
    }
  
    $badwordslist = $xml['JFREU_MESSAGES']['BADWORDS_LIST'][0]['BADWORD'];
     
                	
    } else {
      trigger_error('Could not read/parse jfreu message file ' . $message_file . ' !', E_USER_WARNING);
    }
  } else {
    trigger_error('Could not find jfreu message file ' . $message_file . ' !', E_USER_WARNING);
  }   
?>
