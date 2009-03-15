<?php
/* ***** BEGIN LICENSE BLOCK *****
 * Version: MPL 1.1/GPL 2.0/LGPL 2.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is RedTree Framework
 *
 * The Initial Developer of the Original Code is RedTree Systems LLC.
 * Portions created by the Initial Developer are Copyright (C) 2009
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Joshua T Corbin <jcorbin@redtreesystems.com>
 *
 * Alternatively, the contents of this file may be used under the terms of
 * either the GNU General Public License Version 2 or later (the "GPL"), or
 * the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
 * in which case the provisions of the GPL or the LGPL are applicable instead
 * of those above. If you wish to allow use of your version of this file only
 * under the terms of either the GPL or the LGPL, and not to allow others to
 * use your version of this file under the terms of the MPL, indicate your
 * decision by deleting the provisions above and replace them with the notice
 * and other provisions required by the LGPL or the GPL. If you do not delete
 * the provisions above, a recipient may use your version of this file under
 * the terms of any one of the MPL, the GPL or the LGPL.
 *
 * ***** END LICENSE BLOCK *****
 */

require_once 'Log.php';

class SiteLog
{
    /**
     * @var Site
     */
    protected $site;

    /**
     * The PEAR log interface
     *
     * @var Log
     */
    private $log;

    /**
     * Buffers log messages early on
     *
     * @var array
     */
    private $buffer;

    /**
     * Sets up the site logging interface, this is carried on the Site instance
     * as the $log public property
     *
     * This is called early on in site setup so that there is always a logging
     * interface avaliable. Initially we simply accumulate log messages until
     * either Site configuration is finalized, then we send any accumulated log
     * messages where they belong or
     *
     * @param site Site
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->buffer = array();
        $this->site->addCallback('onPostConfig', array($this, 'configure'));
    }

    /**
     * Sets up the log backend after configuration is done
     *
     * Marshalls 'provideLogBackend' on the Site object, if none is provided,
     * then a file backend is used that will log into a file like:
     *   $site->layout->logDir/y-m-d.log
     * or if test mode is enabled:
     *   $site->layout->logDir/test_y-m-d.log
     *
     * Once the backend is setup, any buffered log messages are logged
     */
    public function configure()
    {
        $this->log = $this->site->marshallSingleCallback('provideLogBackend');
        if (! isset($this->log)) {
            $logDir = $this->site->layout->setupLogDir();
            $logFile = strftime('%F.log');
            if ($this->site->isTestMode()) {
                $logFile = "test_$logFile";
            }
            $logFile = "$logDir/$logFile";

            if ($this->site->isDebugMode()) {
                $logLevel = PEAR_LOG_DEBUG;
            } else {
                $logLevel = PEAR_LOG_WARNING;
            }
            $this->log = Log::singleton('file', $logFile, null, $logLevel);
        }

        foreach ($this->buffer as $item) {
            list($level, $message) = $item;
            if ($level <= $this->log(getMask())) {
                $this->log->log($message, $level);
            }
        }
        $this->buffer = null;
    }

    /**
     * Logs a message
     *
     * @param string $message the message
     * @param int $level the log level
     * @param int $frame whose problem is this, defalut is 2 the caller of the
     * scope that called debug
     * @return void
     * @see callingFrame
     */
    public function log($message, $level, $frame=2)
    {
        $this->cleanMess($message);
        $message .= $this->callingFrame($frame);

        if (isset($this->log)) {
            if ($level <= $this->log->getMask()) {
                $this->log->log($message, $level);
            }
        } else {
            array_push($this->buffer, array($level, $message));
        }
    }

    /**
     * Returns a string description of the calling frame
     *
     * @param int $frame how far back to go in the stack trace from the calller
     * of callingFrame's point of view
     * @return string a string representing the callers frame
     */
    protected function callingFrame($delta)
    {
        $trace = debug_backtrace();
        assert($delta < count($trace));

        // Note, intuitively delta is "delta+1", since $trace is a zero-based
        // array, a $frame=2 is effectively saying "3 frames back from
        // callingFrame's point of view"

        $tc = count($trace);
        $s = ' in '.$this->frameWhat($trace[$delta]);
        do {
            if (array_key_exists('file', $trace[$delta])) {
                $s .= ' at '.
                    $trace[$delta]['file'].':'.
                    $trace[$delta]['line'];
                break;
            }
            $delta++;
            if ($delta < $tc) {
                $s .= ' via '.$this->frameWhat($trace[$delta]);
            } else {
                $s .= ' from ???';
            }
        } while ($delta < $tc);
        return $s;
    }

    /**
     * Formats the what portion for callingFrame, this is as string like:
     * - "Class->method"
     * - "Class::method"
     * - "function"
     *
     * @param array $frame a frame from debug_backtrace()
     * @return string
     */
    protected function frameWhat($frame)
    {
        if (array_key_exists('class', $frame)) {
            return $frame['class'].$frame['type'].$frame['function'];
        } else {
            return $frame['function'];
        }
    }

    /**
     * Convenience for log($message, PEAR_LOG_DEBUG)
     *
     * @param string $message
     * @param int $frame
     * @return void
     * @see log
     */
    public function debug($message, $frame=2)
    {
        $this->log($message, PEAR_LOG_DEBUG, $frame+1);
    }

    /**
     * Convenience for log($message, PEAR_LOG_INFO)
     *
     * @param string $message
     * @param int $frame
     * @return void
     * @see log
     */
    public function info($message, $frame=2)
    {
        $this->log($message, PEAR_LOG_INFO, $frame+1);
    }

    /**
     * Convenience for log($message, PEAR_LOG_NOTICE)
     *
     * @param string $message
     * @param int $frame
     * @return void
     * @see log
     */
    public function notice($message, $frame=2)
    {
        $this->log($message, PEAR_LOG_NOTICE, $frame+1);
    }

    /**
     * Convenience for log($message, PEAR_LOG_WARNING)
     *
     * @param string $message
     * @param int $frame
     * @return void
     * @see log
     */
    public function warning($message, $frame=2)
    {
        $this->log($message, PEAR_LOG_WARNING, $frame+1);
    }

    /**
     * Convenience for log($message, PEAR_LOG_ERR)
     *
     * @param string $message
     * @param int $frame
     * @return void
     * @see log
     */
    public function error($message, $frame=2)
    {
        $this->log($message, PEAR_LOG_ERR, $frame+1);
    }

    /**
     * Convenience for log($message, PEAR_LOG_ALERT)
     *
     * @param string $message
     * @param int $frame
     * @return void
     * @see log
     */
    public function alert($message, $frame=2)
    {
        $this->log($message, PEAR_LOG_ALERT, $frame+1);
    }

    /**
     * Convenience for log($message, PEAR_LOG_FATAL)
     *
     * @param string $message
     * @param int $frame
     * @return void
     * @see log
     */
    public function fatal($message, $frame=2)
    {
        $this->log($message, PEAR_LOG_FATAL, $frame+1);
    }

    /**
     * If debugging is on, files a complaint through php's error handling about
     * use of a deprecated interface with optional advice on what to do instead.
     *
     * @param old string describing the old interface
     * @param new string describing the new interface (optional)
     *
     * @return void
     */
    public function deprecatedComplain($old, $new=null, $from=null, $at=null)
    {
        if (! $this->site->isDebugMode()) {
            return;
        }

        if (! isset($from) || ! isset($at)) {
            $trace = debug_backtrace();
            if (! isset($from)) {
                $from = $trace[2]['class'].$trace[2]['type'].$trace[2]['function'];
            }
            if (! isset($at)) {
                $at = $trace[1]['file'].':'.$trace[1]['line'];
            }
        }

        $mess = "Call to deprecated $old from $from at $at";
        if (isset($new)) {
            $mess .= ", use $new instead";
        }

        trigger_error($mess);
    }

    private function cleanMess(&$message)
    {
        $len = strlen($message);
        if (! $this->site->isDebugMode() && $len > 1024) {
            $message = substr($message, 0, 1024);
        }
        $len = strlen($message);
        for ($i=0; $i<$len; $i++) {
            $chr = ord($message[$i]);
            if ($chr != 10 && $chr < 32 && $chr > 127) {
                $message[$i] = '?';
            }
        }
    }
}

?>
