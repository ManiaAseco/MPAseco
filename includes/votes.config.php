<?php
/**
 * Chat-based voting configuration options.
 * This file is included by plugin.rasp_votes.php.
 * XML file created and restructured php (2012 by Lukas Kremsmayr)
 */

$config_file = 'configs/plugins/rasp/votes_config.xml'; 
 
if (file_exists($config_file)) {
  $aseco->console('Load rasp votes Config [' . $config_file . ']');
	if ($xml = $aseco->xml_parser->parseXml($config_file)) {
	
  	$auto_vote_starter     = text2bool($xml['RASP_VOTES']['AUTO_VOTE_STARTER'][0]);
  	$allow_spec_startvote  = text2bool($xml['RASP_VOTES']['ALLOW_SPEC_STARTVOTE'][0]);
  	$allow_spec_voting     = text2bool($xml['RASP_VOTES']['ALLOW_SPEC_VOTING'][0]);

    // maximum number of rounds before a vote expires
  	$r_expire_limit = array(
  		0 => $xml['RASP_VOTES']['R_EXPIRE_LIMIT_ENDROUND'][0], 
  		1 => $xml['RASP_VOTES']['R_EXPIRE_LIMIT_LADDER'][0], 
  		2 => $xml['RASP_VOTES']['R_EXPIRE_LIMIT_REPLAY'][0], 
  		3 => $xml['RASP_VOTES']['R_EXPIRE_LIMIT_SKIP'][0], 
  		4 => $xml['RASP_VOTES']['R_EXPIRE_LIMIT_KICK'][0], 
  		5 => $xml['RASP_VOTES']['R_EXPIRE_LIMIT_ADD'][0], 
  		6 => $xml['RASP_VOTES']['R_EXPIRE_LIMIT_IGNORE'][0], 
  	); 	
    $r_show_reminder = text2bool($xml['RASP_VOTES']['R_SHOW_REMINDER'][0]);

    	// maximum number of seconds before a vote expires
  	$ta_expire_limit = array(
  		0 => $xml['RASP_VOTES']['TA_EXPIRE_LIMIT_ENDROUND'][0], 
  		1 => $xml['RASP_VOTES']['TA_EXPIRE_LIMIT_LADDER'][0], 
  		2 => $xml['RASP_VOTES']['TA_EXPIRE_LIMIT_REPLAY'][0], 
  		3 => $xml['RASP_VOTES']['TA_EXPIRE_LIMIT_SKIP'][0], 
  		4 => $xml['RASP_VOTES']['TA_EXPIRE_LIMIT_KICK'][0], 
  		5 => $xml['RASP_VOTES']['TA_EXPIRE_LIMIT_ADD'][0], 
  		6 => $xml['RASP_VOTES']['TA_EXPIRE_LIMIT_IGNORE'][0], 
  	); 	
    $ta_show_reminder = text2bool($xml['RASP_VOTES']['TA_SHOW_REMINDER'][0]);

	   // interval length at which to (approx.) repeat reminder [s]
  	$ta_show_interval = $xml['RASP_VOTES']['TA_SHOW_INTERVAL'][0];  
    
    if ($feature_votes){
  		// disable CallVotes
  		$aseco->client->query('SetCallVoteRatio', 1.0);
  
  		// really disable all CallVotes
  		$ratios = array(array('Command' => '*', 'Ratio' => -1.0));
  		$aseco->client->query('SetCallVoteRatios', $ratios);    
    
  	  $global_explain = $xml['RASP_VOTES']['GLOBAL_EXPLAIN'][0];   

  		// define the vote ratios for all types
  		$vote_ratios = array(
  			0 => $xml['RASP_VOTES']['VOTE_RATIO_ENDROUND'][0],  
  			1 => $xml['RASP_VOTES']['VOTE_RATIO_LADDER'][0],  
  			2 => $xml['RASP_VOTES']['VOTE_RATIO_REPLAY'][0],  
  			3 => $xml['RASP_VOTES']['VOTE_RATIO_SKIP'][0],  
  			4 => $xml['RASP_VOTES']['VOTE_RATIO_KICK'][0],  
  			5 => $xml['RASP_VOTES']['VOTE_RATIO_ADD'][0],  
  			6 => $xml['RASP_VOTES']['VOTE_RATIO_IGNORE'][0],  
  		); 
      
  		$vote_in_window     = text2bool($xml['RASP_VOTES']['VOTE_IN_WINDOW'][0]);
  		$disable_upon_admin = text2bool($xml['RASP_VOTES']['DISABLE_UPON_ADMIN'][0]);
  		$disable_while_sb   = text2bool($xml['RASP_VOTES']['DISABLE_WHILE_SB'][0]);     
 
   		// allow kicks & allow user to kick-vote any admin?
  		$allow_kickvotes  = text2bool($xml['RASP_VOTES']['ALLOW_KICKVOTES'][0]);
  		$allow_admin_kick = text2bool($xml['RASP_VOTES']['ALLOW_ADMIN_KICK'][0]);
  		
  		// allow ignores & allow user to ignore-vote any admin?
  		$allow_ignorevotes  = text2bool($xml['RASP_VOTES']['ALLOW_IGNOREVOTES'][0]);
  		$allow_admin_ignore = text2bool($xml['RASP_VOTES']['ALLOW_ADMIN_IGNORE'][0]);
   
  		$max_laddervotes = $xml['RASP_VOTES']['MAX_LADDERVOTES'][0];
  		$max_replayvotes = $xml['RASP_VOTES']['MAX_REPLAYVOTES'][0];
  		$max_skipvotes   = $xml['RASP_VOTES']['MAX_SKIPVOTES'][0];

  		$replays_limit   = $xml['RASP_VOTES']['REPLAYS_LIMIT'][0];
 
  		$ladder_fast_restart = text2bool($xml['RASP_VOTES']['LADDER_FAST_RESTART'][0]);
  
  		$r_points_limits     = text2bool($xml['RASP_VOTES']['R_POINTS_LIMITS'][0]);
  		$r_ladder_max        = $xml['RASP_VOTES']['R_LADDER_MAX'][0];
  		$r_replay_min        = $xml['RASP_VOTES']['R_REPLAY_MIN'][0];
  		$r_skip_max          = $xml['RASP_VOTES']['R_SKIP_MAX'][0];
  
  		$ta_time_limits      = text2bool($xml['RASP_VOTES']['TA_TIME_LIMITS'][0]);
  		$ta_ladder_max       = $xml['RASP_VOTES']['TA_LADDER_MAX'][0];
  		$ta_replay_min       = $xml['RASP_VOTES']['TA_REPLAY_MIN'][0];
  		$ta_skip_max         = $xml['RASP_VOTES']['TA_SKIP_MAX'][0]; 

  } else {
  trigger_error('Could not read/parse rasp votes config file ' . $config_file . ' !', E_USER_WARNING);
  }
} else {
  trigger_error('Could not find rasp votes config file ' . $config_file . ' !', E_USER_WARNING);
}   

?>
