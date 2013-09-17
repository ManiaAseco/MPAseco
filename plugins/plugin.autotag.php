<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * AutoServerTag plugin.
 * Set's ServerTags for plugins
 *
 * Plugin author: W1lla
 *
 * Dependencies: none
 */
 
Aseco::registerEvent('onSync', 'autotag_plugins');
Aseco::addChatCommand('autotag', 'Displays autotag'); //debug mode

function autotag_plugins($aseco) {
    // create list of plugins
    $list = array();
    foreach ($aseco->plugins as $plugin) {
        $list[] = array($plugin);
        //$list = array();
	    //var_dump($plugin);
    }

    $aseco->client->query('SetServerTag','nl.pluginlist', json_encode(array($list)), true);
    $aseco->client->query('SetServerTag', 'nl.controller', 'MPAseco');
    $aseco->client->query('SetServerTag', 'nl.controller.version', MPASECO_VERSION);
}  // autotag_plugins

function chat_autotag($aseco, $command){
	$aseco->client->query('GetServerTags');
	$servertags = $aseco->client->getResponse();
	//var_dump($servertags);
}
?>