<?php
Aseco::registerEvent('onStartup', 'timed_onstartup');
Aseco::registerEvent('onEndMap1', 'timed_endmap');

Aseco::addChatCommand('topweekly', 'Displays top weekly ranked players');
Aseco::addChatCommand('topmonthly', 'Displays top monthly ranked players');
Aseco::addChatCommand('monthlypoints', 'Displays the amount of Monthly Points');
Aseco::addChatCommand('weeklypoints', 'Displays the amount of Weekly Points');

function chat_monthlypoints($aseco, $command) {

	$player = $command['author'];
  $year= date("Y");
  $month= date("m"); 
   
	$query = 'SELECT p.NickName, r.MonthlyPoints FROM players p
	          LEFT JOIN rank_monthly r ON (p.Id=r.PlayerId)
	          WHERE Year='.$year.' AND Month='.$month.' AND Login="'.$player->login.'"';
	$res = mysql_query($query);

	if (mysql_num_rows($res) == 0) {
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}No Monthly Points!'), $player->login);
		mysql_free_result($res);
		return;
	}

	$row = mysql_fetch_object($res);
  $message = $aseco->formatColors('{#server}> {#record}You have{#highlite} '.$row->MonthlyPoints.' {#record}Monthly Points.');
	$aseco->console($message);			
	$aseco->client->query('ChatSendServerMessageToLogin', $message, $player->login);
        
  mysql_free_result($res);  

}  //chat_monthlypoints

function chat_weeklypoints($aseco, $command) {

	$player = $command['author'];
  $year= date("Y");
  $week= date("W"); 
   
	$query = 'SELECT p.NickName, r.WeeklyPoints FROM players p
	          LEFT JOIN rank_weekly r ON (p.Id=r.PlayerId)
	          WHERE Year='.$year.' AND Week='.$week.' AND Login="'.$player->login.'"';
	$res = mysql_query($query);

	if (mysql_num_rows($res) == 0) {
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}No Weekly Points!'), $player->login);
		mysql_free_result($res);
		return;
	}

	$row = mysql_fetch_object($res);
  $message = $aseco->formatColors('{#server}> {#record}You have{#highlite} '.$row->WeeklyPoints.' {#record}Weekly Points.');
	$aseco->console($message);			
	$aseco->client->query('ChatSendServerMessageToLogin', $message, $player->login);
        
  mysql_free_result($res);  

}  //chat_weeklypoints
	
function chat_topmonthly($aseco, $command) {   
	$player = $command['author'];
  $year= date("Y");
  $month= date("m"); 
  
	$head = 'Current TOP Monthly Players:';
	$top = 100;
	$bgn = '{#black}';  // nickname begin

	$query = 'SELECT p.NickName, r.MonthlyPoints FROM players p
	          LEFT JOIN rank_monthly r ON (p.Id=r.PlayerId)
	          WHERE Year='.$year.' AND Month='.$month.' ORDER BY r.MonthlyPoints DESC LIMIT ' . $top;
	$res = mysql_query($query);

	if (mysql_num_rows($res) == 0) {
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}No ranked players found!'), $player->login);
		mysql_free_result($res);
		return;
	}

	$recs = array();
	$lines = 0;
	$player->msgs = array();
	// reserve extra width for $w tags
	$extra = ($aseco->settings['lists_colornicks'] ? 0.2 : 0);
	$player->msgs[0] = array(1, $head, array(0.7+$extra, 0.1, 0.45+$extra, 0.15), array('BgRaceScore2', 'LadderRank'));
	$i = 1;
	while ($row = mysql_fetch_object($res)) {
		$nick = $row->NickName;
		if (!$aseco->settings['lists_colornicks'])
			$nick = stripColors($nick);
		$recs[] = array(str_pad($i, 2, '0', STR_PAD_LEFT) . '.',
		                $bgn . $nick, $row->MonthlyPoints);
		             /*   sprintf("%4.1F", $row->Avg / 10000));        */
		$i++;
		if (++$lines > 14) {
			$player->msgs[] = $recs;
			$lines = 0;
			$recs = array();
		}
	}
	// add if last batch exists
	if (!empty($recs))
		$player->msgs[] = $recs;

	// display ManiaLink message
	display_manialink_multi($player);

	mysql_free_result($res);
}  // topmonthly

function chat_topweekly($aseco, $command) {   
	$player = $command['author'];
  $year= date("Y");
  $week= date("W");   
  
	$head = 'Current TOP Weekly Players:';
	$top = 100;
	$bgn = '{#black}';  // nickname begin

  
	$query = 'SELECT p.NickName, r.WeeklyPoints FROM players p
	          LEFT JOIN rank_weekly r ON (p.Id=r.PlayerId)
	          WHERE Year='.$year.' AND Week='.$week.' ORDER BY r.WeeklyPoints DESC LIMIT ' . $top;
	$res = mysql_query($query);

	if (mysql_num_rows($res) == 0) {
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors('{#server}> {#error}No ranked players found!'), $player->login);
		mysql_free_result($res);
		return;
	}

	$recs = array();
	$lines = 0;
	$player->msgs = array();
	// reserve extra width for $w tags
	$extra = ($aseco->settings['lists_colornicks'] ? 0.2 : 0);
	$player->msgs[0] = array(1, $head, array(0.7+$extra, 0.1, 0.45+$extra, 0.15), array('BgRaceScore2', 'LadderRank'));
	$i = 1;
	while ($row = mysql_fetch_object($res)) {
		$nick = $row->NickName;
		if (!$aseco->settings['lists_colornicks'])
			$nick = stripColors($nick);
		$recs[] = array(str_pad($i, 2, '0', STR_PAD_LEFT) . '.',
		                $bgn . $nick, $row->WeeklyPoints);
		             /*   sprintf("%4.1F", $row->Avg / 10000));        */
		$i++;
		if (++$lines > 14) {
			$player->msgs[] = $recs;
			$lines = 0;
			$recs = array();
		}
	}
	// add if last batch exists
	if (!empty($recs))
		$player->msgs[] = $recs;

	// display ManiaLink message
	display_manialink_multi($player);

	mysql_free_result($res);
}  // topweekly


  
// called @ onEndMap
function timed_endmap($aseco) {

  $year= date("Y");
  $month= date("m");
  $week= date("W");  
  $row=array();
   foreach($aseco->smrankings as $login => $pts)
   {
      $pid=$aseco->getPlayerId($login);
      $monthly=$pts;
      $weekly=$pts;
      if($pts > 0)
      {
        $query = 'SELECT MonthlyPoints FROM rank_monthly WHERE PlayerID='.$pid.' AND Year='.$year.' AND Month='.$month;
        $res = mysql_query($query);
    		if (mysql_num_rows($res) > 0) {	
    		  $row = mysql_fetch_array($res);
    		  $monthly=$row['MonthlyPoints']+$pts;
    	  }
    		mysql_free_result($res);    

        

        $query = 'INSERT INTO rank_monthly 
                  (PlayerId,Year,Month,MonthlyPoints)
                   VALUES
                  ('.$pid.','.$year.','.$month.','.$monthly.')';
        $result = mysql_query($query);

      	if (mysql_affected_rows() == -1) {
      		$error = mysql_error();
      		if (!preg_match('/Duplicate entry.*for key/', $error))
      			trigger_error('Could not insert MonthlyPoints! (' . $error . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
      	}
   
      	// could not be inserted?
      	if (mysql_affected_rows() != 1) {
      		// update existing record
      		$query = 'UPDATE rank_monthly
      		          SET MonthlyPoints=' . $monthly . '
      		          WHERE PlayerId=' . $pid . ' AND Year='.$year.' AND Month='.$month;
      		$result = mysql_query($query);
      
      		// could not be updated?
      		if (mysql_affected_rows() != 1) {
      			trigger_error('Could not update MonthlyPoints! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
      		}
      	}

        /* START WEEKLY POINTS */
        $query = 'SELECT WeeklyPoints FROM rank_weekly WHERE PlayerID='.$pid.' AND Year='.$year.' AND Week='.$week;
        $res = mysql_query($query);
    		if (mysql_num_rows($res) > 0) {	
    		  $row = mysql_fetch_array($res); 
          $weekly=$row['WeeklyPoints']+$pts;            
    	  }
    		mysql_free_result($res);    



        $query = 'INSERT INTO rank_weekly 
                  (PlayerId,Year,Week,WeeklyPoints)
                   VALUES
                  ('.$pid.','.$year.','.$week.','.$weekly.')';
        $result = mysql_query($query);

      	if (mysql_affected_rows() == -1) {
      		$error = mysql_error();
      		if (!preg_match('/Duplicate entry.*for key/', $error))
      			trigger_error('Could not insert WeeklyPoints! (' . $error . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
      	}
   
      	// could not be inserted?
      	if (mysql_affected_rows() != 1) {
      		// update existing record
      		$query = 'UPDATE rank_weekly
      		          SET WeeklyPoints=' . $weekly . '
      		          WHERE PlayerId=' . $pid . ' AND Year='.$year.' AND Week='.$week;
      		$result = mysql_query($query);
      
      		// could not be updated?
      		if (mysql_affected_rows() != 1) {
      			trigger_error('Could not update WeeklyPoints! (' . mysql_error() . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
      		}
      	}
      }     
   }                  
    
}  // timed_endmap

	
			
// called @ onStartup
function timed_onstartup($aseco) {
		if (!checkTables()) {
				trigger_error('{RASP_ERROR} Table structure incorrect!  Use localdb/rasp.sql to correct this', E_USER_ERROR);
			}  
}  // timed_onstartup


function checkTables() {
		$query = "CREATE TABLE IF NOT EXISTS `rank_monthly` (
		          `PlayerId` mediumint(9) NOT NULL default 0,
              `Year` year(4) NOT NULL default '0000',
              `Month` tinyint(2) unsigned NOT NULL default '0',
              `MonthlyPoints` int(10) unsigned NOT NULL default '0',          
		           KEY `PlayerId` (`PlayerId`),
	             UNIQUE KEY `Player` (`PlayerId`,`Year`,`Month`)   		           
		         ) ENGINE=MyISAM";
		mysql_query($query);

		$query = "CREATE TABLE IF NOT EXISTS `rank_weekly` (
		          `PlayerId` mediumint(9) NOT NULL default 0,
              `Year` year(4) NOT NULL default '0000',
              `Week` tinyint(2) unsigned NOT NULL default '0',
              `WeeklyPoints` int(10) unsigned NOT NULL default '0',
		           KEY `PlayerId` (`PlayerId`),
	             UNIQUE KEY `Player` (`PlayerId`,`Year`,`Week`) 		           
		         ) ENGINE=MyISAM";
		mysql_query($query);   

		$tables = array();
		$res = mysql_query('SHOW TABLES');
		while ($row = mysql_fetch_row($res))
			$tables[] = $row[0];
		mysql_free_result($res);
		$check = array();
		$check[1] = in_array('rank_monthly', $tables);
		$check[2] = in_array('rank_weekly', $tables);

		return ($check[1] && $check[2]);	
}
?>
