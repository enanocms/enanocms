<?php
/**
 * debugConsole functions
 *
 * @author Andreas Demmer <info@debugconsole.de>
 * @see <http://www.debugconsole.de>
 * @version 1.0.0
 * @package debugConsole_1.2.1
 */

/**
 * show debug info of a variable in debugConsole,
 * add own text for documentation or hints
 * 
 * @param mixed $variable
 * @param string $text
 */
function dc_dump($variable, $text) {
  if(!defined('ENANO_DEBUG')) return false;
	$debugConsole = debugConsoleLoader();
	
	if (is_object($debugConsole)) {
		$debugConsole->dump($variable, $text);
	}
}

/**
 * watch value changes of a variable in debugConsole
 *
 * @param string $variableName
 */
function dc_watch($variableName) {
  if(!defined('ENANO_DEBUG')) return false;
	$debugConsole = debugConsoleLoader();
	
	if (is_object($debugConsole)) {
		$debugConsole->watchVariable($variableName);
	}
}

/**
 * show checkpoint info in debugConsole to make sure
 * that a certain program line has been passed
 *
 * @param string $message
 */
function dc_here($message = NULL) {
  if(!defined('ENANO_DEBUG')) return false;
	$debugConsole = debugConsoleLoader();
	
	if (is_object($debugConsole)) {
		(bool)$message ? $debugConsole->passedCheckpoint($message) : $debugConsole->passedCheckpoint();
	}
}

/**
 * starts a new timer clock and returns its handle
 *
 * @return mixed
 * @param string $comment
 */
function dc_start_timer($comment) {
  if(!defined('ENANO_DEBUG')) return false;
	$debugConsole = debugConsoleLoader();
	
	if (is_object($debugConsole)) {
		return $debugConsole->startTimer($comment);
	}
}

/**
 * stops and shows a certain timer clock in debugConsole
 *
 * @return bool
 * @param string $timerHandle
 */
function dc_stop_timer($timerHandle) {
  if(!defined('ENANO_DEBUG')) return false;
	$debugConsole = debugConsoleLoader();
	
	if (is_object($debugConsole)) {
		return $debugConsole->stopTimer($timerHandle);
	}
}

/**
 * singleton loader for debugConsole
 * DO NOT USE, private to debugConsole functions
 *
 * @return mixed
 */
function debugConsoleLoader() {
	static $debugConsole;
	static $access = 'unset';

	$config = $GLOBALS['_debugConsoleConfig'];
	
	/* obey access restrictions */
	if (gettype($access) != 'bool') {
		if ($config['active']) {
			if ($config['restrictions']['restrictAccess']) {
				if (in_array($_SERVER['REMOTE_ADDR'], $config['restrictions']['allowedClientAdresses'])) {
					$access = TRUE;
				} else {
					$access = FALSE;
				}
			} else {
				$access = TRUE;
			}
		} else {
			$access = FALSE;
		}
	}
	
	/* access granted */
	if ($access) {
		if (!is_object($debugConsole)) {
			$debugConsole = new debugConsole();
		}
	} else {
		$debugConsole = FALSE;
	}
	
	return $debugConsole;
}
?>