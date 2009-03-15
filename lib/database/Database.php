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
 * The Original Code is RedTree Framework Database Module
 *
 * The Initial Developer of the Original Code is RedTree Systems LLC.
 * Portions created by the Initial Developer are Copyright (C) 2009
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Brandon Prudent <bprudent@redtreesystems.com>
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

/**
 * Database site module
 *
 * This provides a connection manager and an ORM
 */
class Database extends SiteModule
{
    /**
     * Utility to format a time string from a number of seconds
     */
    static public function formatTime($time)
    {
        $sec = $time % 60;
        $time = ($time-$sec)/60;
        $min = $time % 60;
        $time = ($time-$min)/60;
        return sprintf('%d:%02d:%02d', $time, $min, $sec);
    }

    const LOCK_READ = 0x01;
    const LOCK_WRITE = 0x02;

    /**
     * Holds the original PDO instance.
     *
     * @access public
     * @var PDO
     */
    public $pdo;

    /**
     * If set to true, then all SQL statements will be logged as [info] messages.
     *
     * @access public
     * @var boolean
     */
    protected $log = false;

    /**
     * If set to true, then all SQL statements will be timed as [info] messages.
     *
     * @access public
     * @var boolean
     */
    protected $time = false;

    /**
     * If timing is enabled, this tracks the total time queries
     * have taken to execute
     *
     * @var double
     * @access private
     */
    private $totalTime = 0;

    /**
     * If logging is enabled, this tracks the total queries executed
     *
     * @var int
     * @access private
     */
    private $totalQueries = 0;

    /**
     * The current transaction level (allows nestable transactions)
     */
    private $transactionLevel = 0;

    /**
     * The name of the currently selected database
     *
     * @var string
     */
    private $db;

    /**
     * Named database handle cache
     *
     * @var array
     */
    private $handles = array();
    private $dsnStrings = array();

    public function initialize()
    {
        parent::initialize();

        require_once "$this->dir/DatabaseException.php";
        require_once "$this->dir/DatabaseObject.php";
        require_once "$this->dir/DatabaseObjectLink.php";

        $this->site->addCallback('onPostConfig', array($this, 'onPostConfig'));
    }

    public function onPostConfig()
    {
        $this->log = $this->site->config->get(
            'database.logging', $this->site->isDebugMode()
        );
        $this->time = $this->site->config->get(
            'database.timing', $this->site->isDebugMode()
        );

        if ($this->time) {
            $this->site->addCallback('onCLeanup', array($this, 'timingReport'));
        }

        $db = $this->site->config->get('database.default');
        $def = $this->definitions();

        if (isset($db)) {
            $this->select($db);
        } else {
            if ($this->site->isTestMode() && $def->has('test')) {
                $this->select('test');
            } else {
                $this->select('default');
            }
        }
    }

    /**
     * Logs the timing report
     */
    public function timingReport()
    {
        $this->Site->log->info(sprintf(
            '==> Database: %d queries executed in %.4f seconds <==',
            $this->getTotalQueries(),
            $this->getTotalTime()
        ));
    }

    // just a shortcut to get the [database.def] config group
    protected function definitions()
    {
        return $this->site->config->getGroup('database.def');
    }

    /**
     * Selects the named database
     *
     * Expects that a config group exists that looks like this:
     *   [database.def.{dbname}]
     *   ; spcify this
     *   dsn=PDO DSN here
     *   ; or specify these three
     *   driver=mysql
     *   host=localhost
     *   db=database
     *   ; these two are optional
     *   user=redtreedev
     *   pass=redtreesystems
     *   ; defaults to true
     *   persistent=false
     */
    public function select($dbname)
    {
        if (! array_key_exists($dbname, $this->handles)) {
            $def = $this->definitions();
            $cfg = $def->get("$dbname");
            if (! isset($cfg)) {
                throw new InvalidArgumentException(
                    "No ".$def->getPath().".$dbname configuration group defined"
                );
            } elseif (! $cfg instanceof SiteConfigGroup) {
                $cfg = null;
                throw new RuntimeException(
                    "Config value ".$def->getPath().".$dbname should be a group"
                );
            }
        }
        $this->db = $dbname;
    }

    private $unlockRegistered = false;

    public function getSelected()
    {
        return $this->db;
    }

    /**
     * Connects to the database if not already connected.
     *
     * @return PDO
     */
    public function getPDO()
    {
        if (array_key_exists($this->db, $this->handles)) {
            return $this->handles[$this->db];
        }

        $def = $this->definitions();
        $cfg = $def->get($this->db);

        if ($cfg->has('dsn')) {
            $dsn = $cfg->get('dsn');
        } else {
            $dsn = sprintf('%s:host=%s;dbname=%s',
                $cfg->getRequired('driver'),
                $cfg->getRequired('host'),
                $cfg->getRequired('db')
            );
        }

        $opt = array();
        $opt[PDO::ATTR_PERSISTENT] = $cfg->get('persistent', true);
        $opt[PDO::ATTR_CASE] = PDO::CASE_NATURAL;
        $opt[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        $opt[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        $dbh = new PDO($dsn, $cfg->get('user'), $cfg->get('pass'), $opt);

        if (! $this->unlockRegistered) {
            register_shutdown_function(array($this, 'unlockAll'));
            $this->unlockRegistered = true;
        }

        $this->dsnStrings[$this->db] = $dsn;
        return $this->handles[$this->db] = $dbh;
    }

    /**
     * Returns a string that tryes to be a unique dsn identifier
     *
     * @return string
     */
    public function getDSN()
    {
        if (! array_key_exists($this->db, $this->dsnStrings)) {
            $this->getPDO();
        }
        return $this->dsnStrings[$this->db];
    }

    /**
     * Returns the total number of queries executed.
     * Only available if logging was enabled.
     *
     * @return int
     */
    public function getTotalQueries()
    {
        return $this->totalQueries;
    }

    /**
     * Returns the total time queries have taken to execute.
     * Only available if timing was enabled.
     *
     * @return int
     */
    public function getTotalTime()
    {
        return $this->totalTime;
    }

    /**
     * Get the structure of a field into an array
     *
     * @see http://pear.php.net/package/MDB2/docs/latest/MDB2/MDB2_Driver_Reverse_Common.html#methodgetTableFieldDefinition
     * @access public
     * @return data array on success, null on failure. The returned array contains an array
     * for each field definition, with all or some of these indices, depending on the field data
     * type: [notnull] [nativetype] [length] [fixed] [default] [type] [mdb2type]
     */
    public function getTableFieldDefinition($table, $field)
    {
        $this->getPDO();

        /*
         * @WARNING: this is mysql-specific
         */
        try {
            $sql = "SELECT `$field` FROM `$table` LIMIT 1";
            $sth = $this->query($sql);
            $desc = $sth->getColumnMeta(0);
            $out = array($desc);
            $out[0]['notnull'] = in_array('not_null', $desc);
            return $out;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Performs an arbitrary SQL statement.
     *
     * @access public
     * @param string $sql the SQL you want to perform
     * @param string $type the type of statement you're executing.
     * This is only relevant with logging on, as it's what shows up.
     * The default is 'perform'.
     * @return int the number of rows affected
     */
    public function perform($sql, $type='perform')
    {
        $rows = 0;

        $pdo = $this->getPDO();

        try {
            $this->startTiming();
            $rows = $pdo->exec($sql);
            $this->endTiming();
        } catch (PDOException $e) {
            $this->endTiming();
            throw new DatabaseException($this, "$type($sql)", $e->getMessage());
        } catch (Exception $e) {
            $this->endTiming();
            throw $e;
        }

        if ($this->log) {
            $what = $this->whatStatement($type, $sql);
            $this->infoLog($what, $rows);
        }

        return $rows;
    }

    /**
     * Prepares and executes a statement
     *
     * @param string $sqlf a SQL string, with positional(?) based preparation
     * @param arglist ... variable arguments representing the prepared args
     * @return PDOStatement
     */
    public function execute($sqlf)
    {
        $this->startTiming();

        try {
            $sth = $this->prepare($sqlf);

            // Temporarily disable logging
            $logging = $this->log;
            $this->log = false;

            // bind function args. if an array was passed, then flatten it
            $args = array_slice(func_get_args(), 1);
            $index = 1;
            foreach ($args as &$arg) {
                if (is_array($arg)) {
                    foreach ($arg as &$a) {
                        $sth->bindParam($index++, $a);
                    }
                    unset($a);

                    break;
                } # else, not array

                $sth->bindParam($index++, $arg);
            }
            unset($arg);

            $sth->execute();
            $this->endTiming();
            $this->log = $logging;
        } catch (Exception $e) {
            $this->endTiming();
            $this->log = $logging;
            throw $e;
        }

        if ($this->log) {
            $what = $this->whatStatement('executef', $args);
            $this->infoLog($what, $sth->rowCount());
        }

        return $sth;
    }

    /**
     * Prepares the given SQL for db'ing
     *
     * @param string $sql
     * @return PDOStatement
     */
    public function prepare($sql)
    {
        $pdo = $this->getPDO();

        $what = $this->whatStatement('prepare', $sql);
        try {
            $this->startTiming();
            $sth = $pdo->prepare($sql);
            $this->endTiming();
        } catch (PDOException $e) {
            $this->endTiming();
            throw new DatabaseException($this, $what, $e->getMessage());
        } catch (Exception $e) {
            $this->endTiming();
            throw $e;
        }

        if ($this->log) {
            $this->infoLog($this->whatStatement('prepare', $sql));
        }

        return $sth;
    }

    /**
     * Uses the given SQL to query the database.
     *
     * @access public
     * @param string $sql your SQL statement
     * @return boolean
     */
    public function query($sql)
    {
        $pdo = $this->getPDO();

        $what = $this->whatStatement('query', $sql);
        try {
            $this->startTiming();
            $sth = $pdo->query($sql);
            $this->endTiming();
        } catch (PDOException $e) {
            $this->endTiming();
            throw new DatabaseException($this, $what, $e->getMessage());
        } catch (Exception $e) {
            $this->endTiming();
            throw $e;
        }

        if ($this->log) {
            $what = $this->whatStatement('query', $sql);
            $this->infoLog($what, $sth->rowCount());
        }

        return $sth;
    }

    /**
     * Returns an array of arrays contaning the result set represented by the
     * given statement handle.
     *
     * @param PDOStatement $sth
     * @param boolean $collapse if true, the default, single column result sets
     * are collapsed to a scalar value instead of a single-element array
     * @return array
     */
    public function getResultValues(PDOStatement $sth, $collapse=true)
    {
        if ($collapse) {
            $collapse = (bool) $sth->columnCount() == 1;
        }

        $output = array();
        while (($row = $sth->fetch(PDO::FETCH_NUM)) !== false) {
            if ($collapse) {
                array_push($output, $row[0]);
            } else {
                array_push($output, $row);
            }
        }
        return $output;
    }

    /**
     * Returns an array of object rows based on the last prepare/execute
     *
     * @param PDOStatement $sth
     * @param string $type the type of objects to be returned
     * @return array an array of object rows
     */
    public function getResultObjects(PDOStatement $sth, $class='stdClass')
    {
        $output = array();
        while (($o = $sth->fetchObject($class)) !== false) {
            array_push($output, $o);
        }
        return $output;
    }

    /**
     * Returns the last auto_increment number used to insert a row.
     *
     * @access public
     * @return int the last auto_increment number
     */
    public function lastInsertId()
    {
        $pdo = $this->getPDO();

        $id = $pdo->lastInsertId();

        if ($this->log) {
            $this->infoLog("lastInsertId($id)");
        }

        return $id;
    }

    /**
     * Locks the specified tables.
     *
     * @access public
     * @param mixed $tables either an array of table names or a single table name to lock
     * @param int $type the type of lock to aquire. You may use the bit masks Database::LOCK_READ,
     * and Database::LOCK_WRITE
     * @return void
     */
    public function lock($tables, $type=Database::LOCK_READ)
    {
        foreach (array(
            'READ' => Database::LOCK_READ,
            'WRITE' => Database::LOCK_WRITE
        ) as $op => $mask) {
            if ($type & $mask) {
                $sql = 'LOCK TABLES '.implode(" $op, ", (array) $tables)." $op";
                if ($this->perform($sql, 'lock') < 0) {
                    throw new DatabaseException($this,
                        'lock', 'no rows affected'
                    );
                }
            }
        }
    }

    /**
     * Calls unlock for each database, called at shutdown
     */
    public function unlockAll()
    {
        foreach ($this->handles as $db => $dbh) {
            $dbh->exec('UNLOCK TABLES');
        }
    }

    /**
     * Unlocks any tables previousy locked. It's assumed to be safe to call this
     * even if you haven't locked any tables.
     *
     * @access public
     * @return void
     */
    public function unlock()
    {
        $this->perform("UNLOCK TABLES", 'unlock');
    }

    /**
     * Begins a transaction
     *
     * @see http://us.php.net/manual/en/pdo.begintransaction.php
     * @return boolean true on success
     */
    public function transaction()
    {
        if ($this->transactionLevel++) {
            $this->infoLog('transaction(start ignored)');
            return;
        }

        $pdo = $this->getPDO();
        $this->startTiming();
        $ret = $pdo->beginTransaction();
        $this->endTiming();

        if ($this->log) {
            $this->infoLog('transaction(start)');
        }

        return $ret;
    }

    /**
     * Rolls a transaction back
     *
     * @see http://us.php.net/manual/en/pdo.rollback.php
     * @return boolean true on success
     */
    public function rollback()
    {
        if (--$this->transactionLevel) {
            $this->infoLog('transaction(rollback ignored)');
            return;
        }

        $pdo = $this->getPDO();
        $this->startTiming();
        $ret = $pdo->rollBack();
        $this->endTiming();

        if ($this->log) {
            $this->infoLog('transaction(rollback)');
        }

        return $ret;
    }

    /**
     * Commits a transaction
     *
     * @see http://us.php.net/manual/en/pdo.commit.php
     * @return boolean true on success
     */
    public function commit()
    {
        if (--$this->transactionLevel) {
            $this->infoLog('transaction(commit ignored)');
            return;
        }

        $pdo = $this->getPDO();
        $this->startTiming();
        $ret = $pdo->commit();
        $this->endTiming();

        if ($this->log) {
            $this->infoLog('transaction(commit)');
        }

        return $ret;
    }

    /**
     * Timing utility
     */

    private $startTime = null;
    private $lastTimeDelta = null;

    public function isTiming()
    {
        return $this->time;
    }

    public function getLastTime()
    {
        return $this->lastTimeDelta;
    }

    /**
     * Starts timing if enabled and not already timing.
     *
     * @return void
     */
    private function startTiming()
    {
        if (! $this->time || isset($this->startTime)) {
            return;
        }

        $this->lastTimeDelta = null;
        $this->startTime = microtime(true);
    }

    /**
     * Ends timing if enabled and started.
     *
     * Sets lastTimeDelta to the number of microseconds since startTime, and
     * returns it for convenience.
     *
     * Increments totalTime by lastTimeDelta.
     *
     * @return float
     */
    private function endTiming()
    {
        if (! $this->time || ! isset($this->startTime)) {
            return;
        }

        $this->lastTimeDelta = microtime(true) - $this->startTime;
        $this->startTime = null;

        $this->totalTime += $this->lastTimeDelta;

        return $this->lastTimeDelta;
    }

    /**
     * Logging utility
     */

    private $observing = false;
    private $observers = array();

    const INFO  = 0;
    const ERROR = 1;

    /**
     * Registers an observer of database activity
     *
     * callable is passed 3 paramaters:
     *   $type - one of Database::INFO or Database::ERROR
     *   $what - string description
     *   $data - associative array containing extra information
     *           If type == Database::ERROR, this will contain at least one
     *           item named 'why' containing detail on why $what failed.
     * callable's return doesn't matter
     *
     * Example:
     *   class Mumble
     *   {
     *     public function foo()
     *     {
     *       $database = Site::getModule('Database');
     *       $obsh = $database->observe(array($this, 'onDatabaseEvent'))
     *       $database->spindleAndMutilate();
     *       $database->stopObserving($obsh);
     *     }
     *
     *     public function onDatabaseEvent($type, $what, $data)
     *     {
     *       switch ($type) {
     *         case Database::INFO:
     *           print "database->$what\n";
     *           break;
     *         case Database::ERROR:
     *           print "database->$what failed $data['why']\n";
     *           break;
     *       }
     *     }
     *   }
     *   $bla = new Mumble();
     *   $bla->foo();
     *   // prints:
     *   //   database->spindleAndMutilate...
     *   // or
     *   //   database->spindleAndMutilate failed: the paper tore
     *
     *
     * @param callable mixed a callable observer
     * @see stopObeserving
     * @return mixed returns reference to callable so that the caller can easily
     *         stash it for later removal
     */
    public function &observe($callable)
    {
        if (! is_callable($callable)) {
            throw new InvalidArgumentException("Argument isn't callable");
        }

        if (! in_array($callable, $this->observers)) {
            array_push($this->observers, $callable);
        }

        $this->observing = (bool) count($this->observers);

        return $callable;
    }

    /**
     * Unregisters an observer registered with observe
     *
     * @param observer mixed as in observeu
     * @see observe
     * @return void
     */
    public function stopObserving(&$callable)
    {
        if (! is_callable($callable)) {
            throw new InvalidArgumentException("Argument isn't callable");
        }

        $new = array();
        foreach ($this->observers as &$observer) {
            if ($observer !== $callable) {
                array_push($new, $observer);
            }
        }
        unset($observer);

        $this->observers = $new;
        $this->observing = (bool) count($this->observers);
    }

    /**
     * Notifies all observers of an event
     *
     * @param type int INFO or ERROR
     * @param what string what happened
     * @param data array named extra data
     *
     * @return void
     */
    private function notifyObservers($type, $what, $data)
    {
        foreach ($this->observers as $observer) {
            call_user_func($observer, $type, $what, $data);
        }
    }

    /**
     * Logs an info message through Config.
     *
     * This method does NOT check the $log flag first, the caller should do that
     * such that caller doesn't spend time building $what just to have it thrown
     * away.
     *
     * @param what string what the caller did, e.g. "query(DELETE FROM table)"
     * @param rows int number of rows affected by what happened (optional)
     *
     * @return void
     */
    private function infoLog($what, $rows=null)
    {
        $this->totalQueries++;

        $parts = array($what);

        if ($this->time && isset($this->lastTimeDelta)) {
            array_push($parts,
                sprintf('time: %.4f seconds', $this->lastTimeDelta)
            );
        }

        if (isset($rows)) {
            array_push($parts, sprintf('rows: %d', $rows));
        }

        if ($this->observing) {
            $data = array(
                'extra' => array_slice($parts, 1)
            );
            $this->notifyObservers(Database::INFO, $what, $data);
        }

        Site::getLog()->info(
            # Usually looks something like:
            #   Database::action(details), time: n.mmmm seconds, rows: n
            'Database::'.implode(', ', $parts),
            4 # try to pin it on Database's consumer
        );
    }

    /**
     * Returns a what clause for use with other logging functions, such as:
     *
     * @param action string single word like 'execute' or 'prepare'
     * @param detail mixed detail of what action did, if not a string will be
     * passed throuh json_encode
     *
     * @return string "action(detail)"
     */
    private function whatStatement($action, $detail)
    {
        if (! is_string($detail)) {
            if (function_exists('json_encode')) {
                $detail = json_encode($detail);
            } else {
                // TODO fake json since this should be shallow
                $detail = print_r($detail, true);
            }
        }

        return "$action($detail)";
    }
}

?>
