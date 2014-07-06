<?php

if ( ! defined('MESSAGING_ADDON_NAME'))
{
	define('MESSAGING_ADDON_NAME',         'Messaging');
	define('MESSAGING_ADDON_VERSION',      '0.7.2');
}

$config['name']=MESSAGING_ADDON_NAME;
$config['version']=MESSAGING_ADDON_VERSION;

$config['nsm_addon_updater']['versions_xml']='http://www.intoeetive.com/index.php/update.rss/69';