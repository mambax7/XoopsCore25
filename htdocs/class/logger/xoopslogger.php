<?php
/**
 * Xoops Logger handlers - component main class file
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2025 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             kernel
 * @subpackage          logger
 * @since               2.3.0
 * @author              Kazumi Ono  <onokazu@xoops.org>
 * @author              Skalpa Keo <skalpa@xoops.org>
 * @author              Taiwen Jiang <phppp@users.sourceforge.net>
 *
 * @todo                Not well written, just keep as it is. Refactored in 3.0
 */
defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Collects information for a page request
 *
 * Records information about database queries, blocks, and execution time
 * and can display it as HTML. It also catches php runtime errors.
 *
 * @package kernel
 */
class XoopsLogger
{
    /**
     * *#@+
     *
     * @var array
     */
    public $queries    = [];
    public $blocks     = [];
    public $extra      = [];
    public $logstart   = [];
    public $logend     = [];
    public $errors     = [];
    public $deprecated = [];
    /**
     * *#@-
     */

    public $usePopup  = false;
    public $activated = true;

    /**
     * *@access protected
     */
    public $renderingEnabled = false;

    /**
     * XoopsLogger::__construct()
     */
    public function __construct() {}

    /**
     * Deprecated, use getInstance() instead
     */
    public function instance()
    {
        return XoopsLogger::getInstance();
    }

    /**
     * Get a reference to the only instance of this class
     *
     * @return object XoopsLogger  reference to the only instance
     */
    public static function getInstance()
    {
        static $instance;
        if (!isset($instance)) {
            $instance = new XoopsLogger();
            // Always catch errors, for security reasons
            set_error_handler('XoopsErrorHandler_HandleError');
            // grab any uncaught exception
            set_exception_handler([$instance, 'handleException']);
        }

        return $instance;
    }

    /**
     * Enable logger output rendering
     * When output rendering is enabled, the logger will insert its output within the page content.
     * If the string <!--{xo-logger-output}--> is found in the page content, the logger output will
     * replace it, otherwise it will be inserted after all the page output.
     */
    public function enableRendering()
    {
        if (!$this->renderingEnabled) {
            ob_start([&$this, 'render']);
            $this->renderingEnabled = true;
        }
    }

    /**
     * Returns the current microtime in seconds.
     *
     * @return float
     */
    public function microtime()
    {
        /** @var array $now */
        $now = explode(' ', microtime());

        return (float) $now[0] + (float) $now[1];
    }

    /**
     * Start a timer
     *
     * @param string $name name of the timer
     */
    public function startTime($name = 'XOOPS')
    {
        if ($this->activated) {
            $this->logstart[$name] = $this->microtime();
        }
    }

    /**
     * Stop a timer
     *
     * @param string $name name of the timer
     */
    public function stopTime($name = 'XOOPS')
    {
        if ($this->activated) {
            $this->logend[$name] = $this->microtime();
        }
    }

    /**
     * Log a database query
     *
     * @param string $sql   SQL string
     * @param string $error error message (if any)
     * @param int    $errno error number (if any)
     * @param null   $query_time
     */
    public function addQuery($sql, $error = null, $errno = null, $query_time = null)
    {
        if ($this->activated) {
            $this->queries[] = ['sql' => $sql, 'error' => $error, 'errno' => $errno, 'query_time' => $query_time];
        }
    }

    /**
     * Log display of a block
     *
     * @param string $name      name of the block
     * @param bool   $cached    was the block cached?
     * @param int    $cachetime cachetime of the block
     */
    public function addBlock($name, $cached = false, $cachetime = 0)
    {
        if ($this->activated) {
            $this->blocks[] = ['name' => $name, 'cached' => $cached, 'cachetime' => $cachetime];
        }
    }

    /**
     * Log extra information
     *
     * @param string $name name for the entry
     * @param int    $msg  text message for the entry
     */
    public function addExtra($name, $msg)
    {
        if ($this->activated) {
            $this->extra[] = ['name' => $name, 'msg' => $msg];
        }
    }

    /**
     * Log messages for deprecated functions
     *
     * this was deprecated, but is still in broad use?
     *
     * @param string $msg text message for the entry
     *
     */
    public function addDeprecated($msg)
    {
        if ($this->activated) {
            $backTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $miniTrace = "<br> trace: ";
            foreach ($backTrace as $i => $trace) {
                // Check if 'file' and 'line' exist in the current trace step
                $file = $trace['file'] ?? '(unknown file)';
                $line = $trace['line'] ?? '(unknown line)';
                $miniTrace .= $file . ':' . $line . "<br>";
            }
            // Replace paths to keep the output clean
            $miniTrace = str_replace([XOOPS_VAR_PATH, XOOPS_PATH, XOOPS_ROOT_PATH], '', $miniTrace);

            $this->deprecated[] = $msg . $miniTrace;
        }
    }

    /**
     * Error handling callback (called by the zend engine)
     *
     * @param integer $errno
     * @param string  $errstr
     * @param string  $errfile
     * @param string  $errline
     * @param array|null $trace
     */
    public function handleError($errno, $errstr, $errfile, $errline, $trace = null)
    {
        if ($this->activated && ($errno & error_reporting())) {
            // NOTE: we only store relative pathnames
            $this->errors[] = compact('errno', 'errstr', 'errfile', 'errline');
        }
        if ($errno == E_USER_ERROR) {
            $includeTrace = true;
            if (substr($errstr, 0, '8') === 'notrace:') {
                $includeTrace  = false;
                $errstr = substr($errstr, 8);
            }
            echo sprintf(_XOOPS_FATAL_MESSAGE, $errstr);
            if ($includeTrace) {
                echo "<div style='color:#f0f0f0;background-color:#f0f0f0;'>" . _XOOPS_FATAL_BACKTRACE . ':<br>';
                if ($trace === null && function_exists('debug_backtrace')) {
                    $trace = \debug_backtrace();
                    array_shift($trace);  // Remove the first element, which is this function itself
                }
                foreach ($trace as $step) {
                    if (isset($step['file'])) {
                        echo $this->sanitizePath($step['file']);
                        echo ' (' . $step['line'] . ")\n<br>";
                    }
                }
                echo '</div>';
            }
            exit();
        }
    }

    /**
     * Exception handling callback.
     *
     * @param \Exception|\Throwable $e uncaught Exception or Error
     *
     * @return void
     */
    public function handleException($e)
    {
        if ($this->isThrowable($e)) {
            $msg = get_class($e) . ': ' . $this->sanitizePath($this->sanitizeDbMessage($e->getMessage()));
            $this->handleError(E_USER_ERROR, $msg, $e->getFile(), $e->getLine(), $e->getTrace());
        }
    }

    /**
     * Determine if an object implements Throwable (or is an Exception that would under PHP 7.)
     *
     * @param mixed $e Expected to be an object related to Exception or Throwable
     *
     * @return bool true if related to Throwable or Exception, otherwise false
     */
    protected function isThrowable($e)
    {
        $type = interface_exists('\Throwable', false) ? '\Throwable' : '\Exception';
        return $e instanceof $type;
    }

    /**
     *
     * @access protected
     *
     * @param string $path
     *
     * @return mixed|string
     */
    public function sanitizePath($path)
    {
        $path = str_replace(['\\', XOOPS_ROOT_PATH, str_replace('\\', '/', realpath(XOOPS_ROOT_PATH))], ['/', '', ''], $path);

        return $path;
    }

    /**
     * sanitizeDbMessage
     * @access protected
     *
     * @param string $message
     *
     * @return string
     */
    protected function sanitizeDbMessage($message)
    {
        // XOOPS_DB_PREFIX  XOOPS_DB_NAME
        $message = str_replace(XOOPS_DB_PREFIX . '_', '', $message);
        $message = str_replace(XOOPS_DB_NAME . '.', '', $message);

        return $message;
    }

    /**
     * Output buffering callback inserting logger dump in page output
     * @param $output
     * @return string
     */
    public function render($output)
    {
        global $xoopsUser;
        if (!$this->activated) {
            return $output;
        }

        $log                    = $this->dump($this->usePopup ? 'popup' : '');
        $this->renderingEnabled = $this->activated = false;
        $pattern                = '<!--{xo-logger-output}-->';
        $pos                    = strpos($output, $pattern);
        if ($pos !== false) {
            return substr($output, 0, $pos) . $log . substr($output, $pos + strlen($pattern));
        } else {
            return $output . $log;
        }
    }

    /**
     * *#@+
     *
     * @protected
     * @param string $mode
     * @return
     */
    public function dump($mode = '')
    {
        include XOOPS_ROOT_PATH . '/class/logger/render.php';

        return $ret;
    }

    /**
     * get the current execution time of a timer
     *
     * @param  string $name  name of the counter
     * @param  bool   $unset removes counter from global log
     * @return float  current execution time of the counter
     */
    public function dumpTime($name = 'XOOPS', $unset = false)
    {
        if (!$this->activated) {
            return null;
        }

        if (!isset($this->logstart[$name])) {
            return 0;
        }
        $stop  = $this->logend[$name] ?? $this->microtime();
        $start = $this->logstart[$name];

        if ($unset) {
            unset($this->logstart[$name]);
        }

        return $stop - $start;
    }

    /**
     * XoopsLogger::triggerError()
     *
     * @deprecated
     * @param  int     $errkey
     * @param  string  $errStr
     * @param  string  $errFile
     * @param  string  $errLine
     * @param  int $errNo
     * @return void
     */
    public function triggerError($errkey = 0, $errStr = '', $errFile = '', $errLine = '', $errNo = 0)
    {
        $GLOBALS['xoopsLogger']->addDeprecated(__METHOD__ . '  is deprecated since XOOPS 2.5.4');

        if (!empty($errStr)) {
            $errStr = sprintf($errStr, $errkey);
        }
        $errFile = $this->sanitizePath($errFile);
        $this->handleError($errNo, $errStr, $errFile, $errLine);
    }

    /**
     * *#@+
     *
     * @deprecated
     */
    public function dumpAll()
    {
        $GLOBALS['xoopsLogger']->addDeprecated(__METHOD__ . '  is deprecated since XOOPS 2.5.4, please use \'$xoopsLogger->dump(\'\');\' instead.');

        return $this->dump('');
    }

    /**
     * dumpBlocks @deprecated
     *
     * @return dump
     */
    public function dumpBlocks()
    {
        $GLOBALS['xoopsLogger']->addDeprecated(__METHOD__ . '  is deprecated since XOOPS 2.5.4, please use \'$xoopsLogger->dump(\'blocks\');\' instead.');

        return $this->dump('blocks');
    }

    /**
     * dumpExtra @deprecated
     *
     * @return dump
     */
    public function dumpExtra()
    {
        $GLOBALS['xoopsLogger']->addDeprecated(__METHOD__ . '  is deprecated since XOOPS 2.5.4, please use \'$xoopsLogger->dump(\'extra\');\' instead.');

        return $this->dump('extra');
    }

    /**
     * dump Queries @deprecated
     *
     * @return mixed
     */
    public function dumpQueries()
    {
        $GLOBALS['xoopsLogger']->addDeprecated(__METHOD__ . '  is deprecated since XOOPS 2.5.4, please use \'$xoopsLogger->dump(\'queries\');\' instead.');

        return $this->dump('queries');
    }
    /**
     * *#@-
     */
}

/**
 * PHP Error handler
 *
 * NB: You're not supposed to call this function directly, if you don't understand why, then
 * you'd better spend some time reading your PHP manual before you hurt somebody
 *
 * @internal Using a function and not calling the handler method directly because of old PHP versions
 * set_error_handler() have problems with the array( obj,methodname ) syntax
 * @param       $errNo
 * @param       $errStr
 * @param       $errFile
 * @param       $errLine
 * @param  null $errContext
 * @return bool
 */
function XoopsErrorHandler_HandleError($errNo, $errStr, $errFile, $errLine, $errContext = null)
{
    /*
    // We don't want every error to come through this will help speed things up'
    if ($errNo == '2048') {
        return true;
    }
    // XOOPS should always be STRICT compliant thus the above lines makes no sense and will be removed! -- Added by Taiwen Jiang
    */
    $logger = XoopsLogger::getInstance();
    $logger->handleError($errNo, $errStr, $errFile, $errLine, $errContext);
    return null;
}
