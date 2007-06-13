<?php
/**
 * debugConsole class
 *
 * This class allows opening an external JavaScript
 * window for debugging purposes.
 *
 * @author Andreas Demmer <info@debugconsole.de>
 * @see <http://www.debugconsole.de>
 * @version 1.2.1
 * @package debugConsole_1.2.1
 */
class debugConsole {
	/**
	 * events which are shown in debug console
	 *
	 * @var array
	 */
	protected $filters;
	
	/**
	 * all watched variables with their current content
	 *
	 * @var array
	 */
	protected $watches;
	
	/**
	 * debugConsole configuration values
	 *
	 * @var array
	 */
	protected $config;
	
	/**
	 * URL where template can be found
	 *
	 * @var string
	 */
	protected $template;

	/**
	 * javascripts to control popup
	 *
	 * @var array
	 */
	protected $javascripts;

	/**
	 * html for popup
	 *
	 * @var array
	 */
	protected $html;

	/**
	 * time of debugrun start in milliseconds
	 *
	 * @var string
	 */
	protected $starttime;

	/**
	 * time of timer start in milliseconds
	 *
	 * @var array
	 */
	protected $timers;

	/**
	 * constructor, opens popup window
	 */
	public function __construct () {
		/* initialize class vars */
		$this->starttime = $this->getMicrotime();
		$this->watches = array ();
		$this->config = $GLOBALS['_debugConsoleConfig'];
		$this->html = $this->config['html'];
		$this->html['header'] = str_replace("\n\r", NULL, $this->html['header']);
		$this->html['header'] = str_replace("\n", NULL, $this->html['header']);
		$this->javascripts = $this->config['javascripts'];
		
		/* replace PHP's errorhandler */
		$errorhandler = array (
			$this,
			'errorHandlerCallback'
		);
		
		set_error_handler($errorhandler);
		
		/* open popup */
		$popupOptions = "', 'debugConsole', 'width=" . $this->config['dimensions']['width'] . ",height=" . $this->config['dimensions']['height'] . ',scrollbars=yes';
		
		$this->sendCommand('openPopup', $popupOptions);
		$this->sendCommand('write', $this->html['header']);
		
		$this->startDebugRun();
	}
	
	/**
	 * destructor, shows runtime and finishes html document in popup window
	 */
	public function __destruct () {
		$runtime = $this->getMicrotime() - $this->starttime;
		$runtime = number_format((float)$runtime, 4, '.', NULL);
		
		$info = '<p class="runtime">This debug-run took ' . $runtime . ' seconds to complete.</p>';

		$this->sendCommand('write', $info);
		$this->sendCommand('write', '</div>');
		$this->sendCommand('scroll', "0','100000");
		$this->sendCommand('write', $this->html['footer']);
		
		if ($this->config['focus']) {
			$this->sendCommand('focus');
		}
	}
	
	/**
	 * show new debug run header in console
	 */
	
	protected function startDebugRun () {
		$info = '<h1>new debug-run (' . date('H:i') . ' hours)</h1>';
		$this->sendCommand('write', '<div>');
		$this->sendCommand('write', $info);
	}

	/**
	 * adds a variable to the watchlist
	 * 
	 * Watched variables must be in a declare(ticks=n)
	 * block so that every n ticks the watched variables
	 * are checked for changes. If any changes were made,
	 * the new value of the variable is shown in the
	 * debugConsole with additional information where the
	 * changes happened.
	 *
	 * @param string $variableName
	 */
	public function watchVariable ($variableName) {
		if (count($this->watches) === 0) {
			$watchMethod = array (
				$this,
				'watchesCallback'
			);
			
			register_tick_function($watchMethod);
		}
		
		if (isset($GLOBALS[$variableName])) {
			$this->watches[$variableName] = $GLOBALS[$variableName];
		} else {
			$this->watches[$variableName] = NULL;
		}
	}
	
	/**
	 * tick callback: process watches and show changes
	 */
	public function watchesCallback () {
		if ($this->config['filters']['watches']) {
			foreach ($this->watches as $variableName => $variableValue) {
				if ($GLOBALS[$variableName] !== $this->watches[$variableName]) {
					$info = '<p class="watch"><strong>$' . $variableName;
					$info .= '</strong> changed from "';
					$info .= $this->watches[$variableName];
					$info .= '" (' . gettype($this->watches[$variableName]) . ')';
					$info .= ' to "' . $GLOBALS[$variableName] . '" (';
					$info .= gettype($GLOBALS[$variableName]) . ')';
					$info .= $this->getTraceback() . '</p>';
					
					$this->watches[$variableName] = $GLOBALS[$variableName];
					$this->sendCommand('write', $info);
				}
			}
		}
	}
	
	/**
	 * sends a javascript command to browser
	 *
	 * @param string $command
	 * @param string $value
	 */
	protected function sendCommand ($command, $value = FALSE) {
    if($command == 'write') $value = '\'+unescape(\''.rawurlencode($value).'\')+\'';
		$value = str_replace('\\', '\\\\', $value);
    $value = nl2br($value);
		
		if ((bool)$value) { 
			/* write optionally logfile */
			$this->writeLogfileEntry($command, $value);
			
			$command = $this->javascripts[$command] . "('" . $value . "');";
		} else {
			$command = $this->javascripts[$command] . ';';
		}
		
		$command = str_replace("\n\r", NULL, $command);
		$command = str_replace("\n", NULL, $command);
		
		if (!$this->config['logfile']['disablePopup']) {
			echo $this->javascripts['openTag'], "\n";
			echo $command, "\n";
			echo $this->javascripts['closeTag'], "\n";
		}
		
		flush();
	}
	
	/**
	 * writes html output as text entry into logfile
	 *
	 * @param string $command
	 * @param string $value
	 */
	protected function writeLogfileEntry ($command, $value) {
		if ($this->config['logfile']['enable']) {
			$logfile = $this->config['logfile']['path'] . $this->config['logfile']['filename'];
			/* log only useful entries, no html header and footer */
			if (
				$command === 'write'
				&& !strpos($value, '<html>')
				&&  !strpos($value, '</html>')
			) {
				/* convert html to text */
				$value = html_entity_decode($value);
				$value = str_replace('>', '> ', $value);
				$value = strip_tags($value);
				
				$fp = fopen($logfile, 'a+');
				fputs($fp, $value . "\n\n");
				fclose($fp);
			} elseif (strpos($value, '</html>')) {
				$fp = fopen($logfile, 'a+');
				fputs($fp, "-----------\n");
				fclose($fp);
			}
		}
	}

	/**
	 * shows in console that a checkpoint has been passed,
	 * additional info is the file and line which triggered
	 * the output
	 *
	 * @param string $message
	 */	
	public function passedCheckpoint ($message = NULL) {
		if ($this->config['filters']['checkpoints']) {
			$message = (bool)$message ? $message : 'Checkpoint passed!';
	
			$info = '<p class="checkpoint"><strong>' . $message . '</strong>';
			$info .= $this->getTraceback() . '</p>';
			
			$this->sendCommand('write', $info);
		}
	}
	
	/**
	 * returns microtime as float value
	 *
	 * @return float
	 */
	protected function getMicrotime () {
		list($usec, $sec) = explode(' ', microtime()); 
    	return ((float)$usec + (float)$sec);
	}
	
	/**
	 * returns all possible filter events for debugConsole::setFilter() method
	 *
	 * @return array
	 */
	public function getFilters () {
		$filters = array_keys($this->config['filters']);
		
		ksort($filters);
		reset($filters);
		
		return $filters; 
	}
	
	/**
	 * shows or hides an event-type in debugConsole,
	 * returns previous setting of the given event-type
	 *
	 * @param string $event
	 * @param bool $isShown
	 * @return bool
	 */
	public function setFilter ($event, $isShown) {
		if (array_key_exists($event, $this->config['filters'])) {
			$oldValue = $this->config['filters'][$event];
			$this->config['filters'][$event] = $isShown;
		} else {
			throw new Exception ('debugConsole: unknown event "' . $event . '" in debugConsole::filter()');
		}
		
		return $oldValue;
	}
	
	/**
	 * show debug info for variable in debugConsole,
	 * added by custom text for documentation and hints
	 *
	 * @param mixed $variable
	 * @param string $text
	 */
	public function dump ($variable, $text) {
		if ($this->config['filters']['debug']) {
			@ob_start();
			
			/* grab current ob content */
			$obContents = ob_get_contents();
			ob_clean();
			
			/* grap var dump from ob */
			var_dump($variable);
			$variableDebug = ob_get_contents();
			ob_end_clean();
			
			/* restore previous ob content */
			if ((bool)$obContents) echo $obContents;
			
			/* render debug */
			$variableDebug = htmlspecialchars($variableDebug);			
			$infos = '<p class="dump">' . $text . '<br />';
			
			if (is_array($variable)) {
				$variableDebug = str_replace(' ', '&nbsp;', $variableDebug);
				$infos .= '<span class="source">' . $variableDebug . '</span>';
			} else {
				$infos .= '<strong>' . $variableDebug . '</strong>';
			}
			
			$infos .= $this->getTraceback() . '</p>';
			$this->sendCommand('write', $infos);
		}
	}
	
	/**
	 * callback method for PHP errorhandling
	 * 
	 * @todo implement more errorlevels
	 */
	public function errorHandlerCallback () {
		$details = func_get_args();
		$details[1] = str_replace("'", '"', $details[1]);
		$details[1] = str_replace('href="function.', 'target="_blank" href="http://www.php.net/', $details[1]);
		
		
		/* determine error level */
		switch ($details[0]) {
			case 2:
				if (!$this->config['filters']['php_warnings']) return;
				$errorlevel = 'warning';
				break;
			case 8:
				if (!$this->config['filters']['php_notices']) return;
				$errorlevel = 'notice';
				break;
			case 2048:
				if (!$this->config['filters']['php_suggestions']) return;
				$errorlevel = 'suggestion';
				break;
		}

		$file = $this->cropScriptPath($details[2]);
		
		$infos = '<p class="' . $errorlevel . '"><strong>';
		$infos .= 'PHP ' . strtoupper($errorlevel) . '</strong>';
		$infos .= $details[1] . '<span class="backtrace">';
		$infos .= $file . ' on line ';
		$infos .= $details[3] . '</span></p>';		
		
		$this->sendCommand('write', $infos);
	}
	
	/**
	 * start timer clock, returns timer handle
	 * 
	 * @return mixed
	 * @param string $comment
	 */
	public function startTimer ($comment) {
		if ($this->config['filters']['timers']) {
			$timerHandle = md5(microtime());
			
			$this->timers[$timerHandle] = array (
				'starttime' => $this->getMicrotime(),
				'comment' => $comment
			);
		} else {		
			$timerHandle = FALSE;		
		}
		
		return $timerHandle;
	}
	
	/**
	 * stop timer clock
	 * 
	 * @return bool
	 * @param string $timerHandle
	 */
	public function stopTimer ($timerHandle) {
		if ($this->config['filters']['timers']) {
			if (array_key_exists($timerHandle, $this->timers)) {
				$timerExists = TRUE;
				$timespan = $this->getMicrotime() - $this->timers[$timerHandle]['starttime'];
			
				$info = '<p class="timer"><strong>' . $this->timers[$timerHandle]['comment'];
				$info .= '</strong><br />The timer ran ';
				$info .= '<strong>' . number_format ($timespan, 4, '.', NULL) . '</strong>';
				$info .= ' seconds.' . $this->getTraceback() . '</p>';
			
				$this->sendCommand('write', $info);
			} else {
				$timerExists = FALSE;
			}
		} else {
			$timerExists = FALSE;
		}
		
		return $timerExists;
	}
	
	/**
	 * returns a formatted traceback string
	 *
	 * @return string
	 */
	public function getTraceback () {
		$callStack = debug_backtrace();

		$debugConsoleFiles = array(
			'debugConsole.class.php',
			'debugConsole.functions.php'
		);
		
		$call = array (
			'file' => 'debugConsole.class.php'
		);
		
		while(in_array(basename($call['file']), $debugConsoleFiles)) {
			$call = array_shift($callStack);
		}

		$call['file'] = $this->cropScriptPath($call['file']);
		
		$traceback = '<span class="backtrace">';
		$traceback .= $call['file'] . ' on line ';
		$traceback .= $call['line'] . '</span>';
		
		return $traceback;
	}
	
	/**
	 * crops long script path, shows only the last $maxLength chars
	 *
	 * @param string $path
	 * @param int $maxLength
	 * @return string
	 */
	protected function cropScriptPath ($path, $maxLength = 30) {
		if (strlen($path) > $maxLength) {
			$startPos = strlen($path) - $maxLength - 2;
			
			if ($startPos > 0) {
				$path = '...' . substr($path, $startPos);
			}
		}

		return $path;
	}
}
?>