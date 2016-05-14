<?php
/**
 * This file contains a smaple configuration for the Cache Cleaner
 * It is *not* loaded by default
 *
 * @author		Francois Suter <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_cachecleaner
 *
 * $Id: configuration_sample.php 24444 2009-09-14 13:21:05Z francois $
 */
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['cachecleaner'] = array(
	'tables' => array(
		'cache_pages' => array(
			'expireField' => 'expires'
		),
		'cache_hash' => array(
			'dateField' => 'tstamp',
			'expirePeriod' => '7d'
		),
		'sys_log' => array(
			'dateField' => 'tstamp',
			'expirePeriod' => '1m'
		)
	)
);
?>
