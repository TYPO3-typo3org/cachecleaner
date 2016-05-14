<?php

########################################################################
# Extension Manager/Repository config file for ext: "cachecleaner"
#
# Auto generated 07-12-2009 10:18
#
# Manual updates:
# Only the data in the array - anything else is removed by next write.
# "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Cache Cleaner',
	'description' => 'Automation tool for clearing up old entries in cache tables. For use with lowlevel cleaner.',
	'category' => 'be',
	'author' => 'Francois Suter (Cobweb)',
	'author_email' => 'typo3@cobweb.ch',
	'shy' => '',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'module' => '',
	'doNotLoadInFE' => 1,
	'state' => 'stable',
	'internal' => '',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author_company' => '',
	'version' => '1.1.0',
	'constraints' => array(
		'depends' => array(
			'php' => '5.0.0-0.0.0',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:10:{s:9:"ChangeLog";s:4:"4bbd";s:10:"README.txt";s:4:"03b5";s:34:"class.tx_cachecleaner_lowlevel.php";s:4:"6f41";s:24:"configuration_sample.php";s:4:"b620";s:16:"ext_autoload.php";s:4:"e9e3";s:21:"ext_conf_template.txt";s:4:"ef02";s:12:"ext_icon.gif";s:4:"c17e";s:17:"ext_localconf.php";s:4:"a50b";s:13:"locallang.xml";s:4:"9349";s:14:"doc/manual.sxw";s:4:"35c8";}',
	'suggests' => array(
	),
);

?>