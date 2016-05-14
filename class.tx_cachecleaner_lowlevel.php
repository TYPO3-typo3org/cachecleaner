<?php
/***************************************************************
*  Copyright notice
*  
*  (c) 2009 Francois Suter (typo3@cobweb.ch)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is 
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
* 
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
* 
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/** 
 * This class provides the functionality for the tx_cachecleaner_cache module of the lowlevel_cleaner
 *
 * @author		Francois Suter <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_cachecleaner
 *
 *  $Id: class.tx_cachecleaner_lowlevel.php 27411 2009-12-07 09:12:12Z francois $
 */
class tx_cachecleaner_lowlevel extends tx_lowlevel_cleaner_core {
	protected $extKey = 'cachecleaner';	// The extension key
	protected $extConf = array(); // Extension configuration
	protected $cleanerConfiguration = array(); // The configuration of tables to clean up
	protected $analysisResults = array(); // The results of the analysis run
	protected $selectedTables = array(); // List of tables to clean up

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::tx_lowlevel_cleaner_core();

			// Load the extension configuration
		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);

			// Load the cleaning configuration
		if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['tables']) && is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['tables'])) {
			$this->cleanerConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['tables'];
		}

			// Load the language file and set base messages for the lowlevel interface
		$GLOBALS['LANG']->includeLLFile('EXT:' . $this->extKey . '/locallang.xml');
		$this->cli_help['name'] = $GLOBALS['LANG']->getLL('name');
		$this->cli_help['description'] = trim($GLOBALS['LANG']->getLL('description'));
		$this->cli_help['author'] = 'Francois Suter, (c) 2009';
		$this->cli_options[] = array('--optimize', $GLOBALS['LANG']->getLL('options.optimize'));
		$this->cli_options[] = array('--tables table_names', $GLOBALS['LANG']->getLL('options.tables'));

		if (!empty($this->cli_args['--tables'])) {
			$this->selectedTables = t3lib_div::trimExplode(',', $this->cli_args['--tables'][0], TRUE);
		}
			// Add entry to the sys_log to keep track of executions
		$GLOBALS['BE_USER']->writelog(
			4,
			0,
			0,
			'cachecleaner',
			'[cachecleaner]: Finished initializing',
			array()
		);
	}

	/**
	 * This method is called by the lowlevel_cleaner script when running without the AUTOFIX option
	 * It just returns a preview of could happen if the script was run for real
	 *
	 * @return	array	Result structure, as expected by the lowlevel_cleaner
	 * @see tx_lowlevel_cleaner_core::cli_main()
	 */
	public function main() {
			// Initialize result array
		$resultArray = array(
			'message' => $this->cli_help['name'] . chr(10) . chr(10) . $this->cli_help['description'],
			'headers' => array(
				'RECORDS_TO_CLEAN' => array(
					$GLOBALS['LANG']->getLL('cleantest.header'), $GLOBALS['LANG']->getLL('cleantest.description'), 1
				)
			),
			'RECORDS_TO_CLEAN' => array()
		);

			// No tables are configured
			// Issue information message to that effect
		$logMessage = 'No tables configured';
		if (count($this->cleanerConfiguration) == 0) {
			$message = $GLOBALS['LANG']->getLL('noConfiguration');
			$resultArray['RECORDS_TO_CLEAN'][] = $message;
			if ($this->extConf['debug'] || TYPO3_DLOG) {
				t3lib_div::devLog($message, $this->extKey, 2);
			}

			// Loop on all configured tables
		} else {
			foreach ($this->cleanerConfiguration as $table => $tableConfiguration) {
					// Perform operations only if table is selected or all tables are allowed
				if (count($this->selectedTables) == 0 || in_array($table, $this->selectedTables)) {
					$configurationOK = true;
					$field = '';
					$dateLimit = '';
					$where = '';
						// Handle tables that have an explicit expiry field
					if (isset($tableConfiguration['expireField'])) {
						$field = $tableConfiguration['expireField'];
						$dateLimit = $GLOBALS['EXEC_TIME'];
							// If expire field value is 0, do not delete
							// Expire field = 0 means no expiration
						$where = $field . " <= '" . $dateLimit . "' AND " . $field . " > '0'";

						// Handle tables with a date field and a lifetime
					} elseif (isset($tableConfiguration['dateField'])) {
						$field = $tableConfiguration['dateField'];
						$dateLimit = $this->calculateDateLimit($tableConfiguration['expirePeriod']);
						$where = $field . " <= '" . $dateLimit . "'";

						// No proper configuration field was found, skip this table
					} else {
						$configurationOK = false;
					}

						// If the configuration is ok, perform the actual query and write down the results
						// Also store the results for each table, so that no pointless DELETE is performed
						// when the script is actually run
					$message = '';
					if ($configurationOK) {
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('COUNT(*) AS total', $table, $where);
						$row = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);
						$message = sprintf($GLOBALS['LANG']->getLL('recordsToDelete'), $table, $row[0]);
						$this->analysisResults[$table] = $row[0];
					} else {
						$message = '!!! ' . sprintf($GLOBALS['LANG']->getLL('invalidConfigurationForTable'), $table);
						$this->analysisResults[$table] = 0;
					}
					$resultArray['RECORDS_TO_CLEAN'][] = $message;
					$GLOBALS['TYPO3_DB']->sql_free_result($res);
				}
			}
			$logMessage = 'Finished analyzing';
		}

			// Add entry to the sys_log to keep track of executions
		$GLOBALS['BE_USER']->writelog(
			4,
			0,
			0,
			'cachecleaner',
			'[cachecleaner]: ' . $logMessage,
			array()
		);

		return $resultArray;
	}

	/**
	 * This method is called by the lowlevel_cleaner script when running *with* the AUTOFIX option
	 * 
	 * @return	void
	 * @see tx_lowlevel_cleaner_core::cli_main()
	 */
	public function main_autofix() {
			// Perform clean up only if at least one table is configured
			// NOTE: the AUTOFIX question will still be asked, as there no way
			// to interrupt the process from here
		if (count($this->cleanerConfiguration) > 0) {
				// Loop on all configured tables
			foreach ($this->cleanerConfiguration as $table => $tableConfiguration) {
					// Perform operations only if table is selected or all tables are allowed
				if (count($this->selectedTables) == 0 || in_array($table, $this->selectedTables)) {
						// If the analysis run returned 0 records to clean up,
						// avoid clean up entirely for this table
					if ($this->analysisResults[$table] == 0) {
						$message =  sprintf($GLOBALS['LANG']->getLL('noRecordsToDelete'), $table);
						if ($this->extConf['debug'] || TYPO3_DLOG) {
							t3lib_div::devLog('(' . $table. ') ' . $GLOBALS['LANG']->getLL('noDeletedRecords'), $this->extKey, 0);
						}
						echo $message . chr(10);

						// There are records to clean up, proceed
					} else {
						echo sprintf($GLOBALS['LANG']->getLL('cleaningRecords'), $table) . ':' . chr(10);
						if (($bypass = $this->cli_noExecutionCheck($table))) {
							echo $bypass;
						} else {
							$configurationOK = true;
							$field = '';
							$dateLimit = '';
							$where = '';
								// Handle tables that have an explicit expiry field
							if (isset($tableConfiguration['expireField'])) {
								$field = $tableConfiguration['expireField'];
								$dateLimit = $GLOBALS['EXEC_TIME'];
									// If expire field value is 0, do not delete
									// Expire field = 0 means no expiration
								$where = $field . " <= '" . $dateLimit . "' AND " . $field . " > '0'";

								// Handle tables with a date field and a lifetime
							} elseif (isset($tableConfiguration['dateField'])) {
								$field = $tableConfiguration['dateField'];
								$dateLimit = $this->calculateDateLimit($tableConfiguration['expirePeriod']);
								$where = $field . " <= '" . $dateLimit . "'";

								// No proper configuration field was found, skip this table
							} else {
								$configurationOK = false;
							}

								// If the configuration is ok, perform the actual query and write down the results
							$message = '';
							$severity = 0;
							if ($configurationOK) {
								$res = $GLOBALS['TYPO3_DB']->exec_DELETEquery($table, $where);
								$numDeletedRecords = $GLOBALS['TYPO3_DB']->sql_affected_rows($res);
								$message =  sprintf($GLOBALS['LANG']->getLL('deletedRecords'), $numDeletedRecords);
									// Optimize the table, if the optimize flag was set
									// NOTE: this is MySQL specific and will not work with another database type
								if (isset($this->cli_args['--optimize'])) {
									$GLOBALS['TYPO3_DB']->sql_query('OPTIMIZE TABLE ' . $table);
									$message .=  ' ' . $GLOBALS['LANG']->getLL('tableOptimized');
								}

								// If the configuration is not ok, write out an error message
							} else {
								$message = $GLOBALS['LANG']->getLL('invalidConfiguration') . ' ' . $GLOBALS['LANG']->getLL('cleanupSkipped');
								$severity = 2;
							}
							if ($this->extConf['debug'] || TYPO3_DLOG) {
								t3lib_div::devLog('(' . $table. ') ' . $message, $this->extKey, $severity);
							}
							echo $message . chr(10);
						}
					}
					echo chr(10);
				}
			}

				// Add entry to the sys_log to keep track of executions
			$GLOBALS['BE_USER']->writelog(
				4,
				0,
				0,
				'cachecleaner',
				'[cachecleaner]: Finished cleaning up',
				array()
			);
		}
	}

	/**
	 * This method calculates the date limit given a duration from the cleaning configuration
	 * The duration can be a single number, in which case it is considered to be a number of days
	 * It can also take a character after the number to definie another period. The available periods are:
	 *
	 *	"h" = hour
	 *	"d" = day
	 *	"w" = week
	 *	"m" = month (= 30 days)
	 *
	 * Examples
	 *
	 *	7d = 7 days
	 *	4h = 4 hours
	 *
	 * @param	string		$duration: duration from the cache cleaning configuration
	 * @return	integer		Date limit as a Unix timestamp
	 */
	protected function calculateDateLimit($duration) {
			// Extract last character of duration string
		$periodType = substr($duration, -1);

			// If this character is a number, it is not a period type
			// The whole duration is taken as the period's length and the default type is set to "d" (days)
		$periodLength = '';
		if (is_numeric($periodType)) {
			$periodType = 'd';
			$periodLength = intval($duration);

			// Get the period's length by removing the last character from the duration string
		} else {
			$periodLength = intval(substr($duration, 0, -1));
		}

			// Calculate the size of the expire period in seconds
			// The default is 1 day
		$interval = 86400;
		switch ($periodType) {
			case 'h':
				$interval = 3600;
				break;
			case 'w':
				$interval = 7 * 86400;
				break;
			case 'm':
				$interval = 30 * 86400;
				break;
		}
		$limit = $GLOBALS['EXEC_TIME'] - ($periodLength * $interval);
		return $limit;
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cachecleaner/class.tx_cachecleaner_lowlevel.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cachecleaner/class.tx_cachecleaner_lowlevel.php']);
}
?>