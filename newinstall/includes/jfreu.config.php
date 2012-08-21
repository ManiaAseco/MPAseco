<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Jfreu's plugin 0.13d
 * Configuration settings.
 * This file is included by jfreu.plugin.php or jfreu.lite.php, so don't
 * list it in plugins.xml!
 * Updated by Xymph
 * updated by kremsy for mpaseco 
 */

	//-> paths to config, vip/vip_team & bans files
	$conf_file = 'plugins/jfreu/jfreu.config.xml';
	$vips_file = 'plugins/jfreu/jfreu.vips.xml';
	$bans_file = 'plugins/jfreu/jfreu.bans.xml';

	//-> Server's base name: (ex: '$000Jfreu')
	//   Max. length: 26 chars (incl. colors & tags, and optional "TopXXX")
	$servername = 'YOUR SERVER NAME';
	//-> Word between the servername and the limit (usually " Top")
	$top = ' $449TOP';
	//-> Change the servername when the limit changes: "Servername TopXXX" (0 = OFF, 1 = ON)
	$autochangename = 0;

	//-> ranklimit: ranklimiting default state (0 = OFF, 1 = ON)
	$ranklimit = 0;

	//-> limit: ranklimit default value (when autorank is OFF)
	$limit = 500000;

	//-> spec ranklimit
	$hardlimit = 1000000;

	//-> autorank: autorank default state (0 = OFF, 1 = ON)
	$autorank = 0;

	//-> offset (average + offset = Auto-RankLimit)
	$offset = 999;

	//-> autorankminplayers (autorank disabled when not enough players)
	$autorankminplayers = 10;
	//-> autorankvip: include VIP/unSpec in autorank calculation (0 = OFF, 1 = ON)
	$autorankvip = 0;

	//-> kick hirank when server is full and new player arrives (0 = OFF, 1 = ON)
	$kickhirank = 0;
	//-> maxplayers value for kickhirank (must be less than server's <max_players>)
	$maxplayers = 20;

	//-> allow user /unspec vote (0 = OFF, 1 = ON)
	$unspecvote = 1;

	//-> player join/leave messages
	$player_join  = '{#server}>> {1}: {#highlite}{2}$z$s{#message} Nation: {#highlite}{3}{#message} Ladder: {#highlite}{4}';
	$player_joins = '{#server}>> {1}: {#highlite}{2}$z$s{#message} Nation: {#highlite}{3}{#message} Ladder: {#highlite}{4}';
	$player_left  = '{#server}>> {#highlite}{1}$z$s{#message} has left the game. Played: {#highlite}{2}';

	//-> random info messages at the end of the race (0 = OFF, 1 = in chat, 2 = in message window)
	$infomessages = 1;
	//-> prefix for info messages
	$message_start = '$z$s$ff0>> [$f00INFO$ff0] $fff';

	//-> random information messages (if you add a message don't forget to change the number) (999 messages max :-P)
	// $message1 = 'Jfreu\'s plugin: "http://reload.servegame.com/plugin/"';
	$message1 = 'Information about and download of this MPAseco on ' . MPASECO;
	$message2 = 'Use "/list" -> "/jukebox ##" to add a map in the jukebox.';
	$message3 = 'Please don\'t sound your horn throughout the entire map.';
	$message4 = 'When going AFK, please set your character to Spectator mode.';
	$message5 = 'Don\'t use Enter to skip intros - instead use Space & Enter';
	$message6 = 'For player & server info use the "/stats" and "/server" commands.';
	$message7 = 'Looking for the name of this server?  Use the "/server" command.';
//	$message8 = 'Use "/list nofinish" to find maps you haven\'t completed yet, then /jukebox them!';
	$message8 = 'Use "/list norank" to find maps you aren\'t ranked on, then /jukebox them!';
//	$message10 = 'Can you beat the Gold time on all maps?  Use "/list nogold" to find out!';
//	$message11 = 'Can you beat the Author time on all maps?  Use "/list noauthor" to find out!';
	$message9 = 'Wondering which maps you haven\'t played recently?  Use "/list norecent" to find out!';
//	$message13 = 'Use the "/best" & "/worst" commands to find your best and worst records!';
//	$message14 = 'Use the "/clans" & "/topclans" commands to see clan members and ranks!';
	$message10 = 'Use the "/ranks" commands to see the server ranks of all online players!';
	$message11 = 'Who is the most victorious player?  Use "/topwins" to find out!';
	$message12 = 'Who has the most ranked records?  Use "/toprecs" to find out!';
	$message13 = 'Wondering what maps were played recently?  Use the "/history" command.';
	$message14 = 'Find the difference between your personal best and the map record with the "/diffrec" command!';
//	$message21 = 'Check how many records were driven on the current map with the "/newrecs" command!';
//	$message22 = 'Check how many records, and the 3 best ones, you have with the "/summary" command!';
//	$message16 = 'Who has the most top-3 ranked records?  Use "/topsums" to find out!';
	$message15 = 'Jukeboxed the wrong map?  Use "/jukebox drop" to remove it!';
	$message16 = 'Forgot what someone said?  Use "/chatlog" to check the chat history!';
	$message17 = 'Forgot what someone pm-ed you?  Use "/pmlog" to check your PM history!';
	$message18 = 'Looking for the next better ranked player to beat?  Use "/nextrank"!';
	$message19 = 'Use "/list newest <#>" to find the newest maps added to the server, then /jukebox them!';
	$message20 = 'Find the longest and shortest maps with the "/list longest / shortest" commands!';
	$message21 = 'Use "/mute" and "/unmute" to mute / unmute other players, and "/mutelist" to list them!';
	$message22 = 'Wondering when a player was last online?  Use "/laston <login>" to find out!';
//	$message32 = 'Use checkpoints tracking in Rounds/Team/Cup modes with the "/cps" command!';
	$message23 = 'Find the MX info for a map with the "/mxinfo" command!';
	$message24 = 'Looking for the name of the current map\'s song?  Use the "/song" command!';
	$message25 = 'Looking for the name of the current map\'s mod?  Use the "/mod" command!';
	$message26 = 'Use the "/style" command to select your personal window style!';
	$message27 = 'Use the "/recpanel" command to select your personal records panel!';
	$message28 = 'Use the "/votepanel" command to select your personal vote panel!';
//	$message31 = 'Find out all about the Dedimania world records system with "/helpdedi"!';
	$message29 =  'Check out the new MPAseco site at ' . MPASECO . ' !';
	global $feature_votes;
	if ($feature_votes) {
	$message30 = 'Find out all about the chat-based voting commands with "/helpvote"!';
	}
	if (function_exists('send_window_message')) {
	$message31 = 'Missed a system message?  Use "/msglog" to check the message history!';
	}

	//-> Badwords checking (0 = OFF, 1 = ON)
	$badwords = 0;
	//-> Badwords banning (0 = OFF, 1 = ON)
	$badwordsban = 0;
	//-> Number of badwords allowed
	$badwordsnum = 3;
	//-> Banning period (minutes)
	$badwordstime = 10;

	//-> Badwords to check for
	$badwordslist = array(
		'putain','ptain','klote','kIote','kanker','kenker',
		'arschl','wichs','fick','fikk','salop','siktirgit','gvd',
		'hitler','nutte','dick','cock','faitchier','bordel','shit',
		'encul','sucks','a.q','conerie','scheise','scheiße','scheis',
		'baskasole','cocugu','kodugumun','cazo','hoer','bitch',
		'penis','fotze','maul','frese','pizda','gay','fuck','tyfus',
		'sugi','cacat','pisat','labagiu','gaozar','muist','orospu',
		'pédé','cunt','godve','godfe','kut','kudt','lul','iui');

	//-> novote (auto-cancel votes) (0 = OFF, 1 = ON)
	$novote = 0;
?>
