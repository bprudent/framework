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

abstract class DatabaseObjectAbstractMeta
{
    static private $MetaCache = array();
    final static protected function metaForClass($objectClass, $metaClass, $db=null)
    {
        $database = Site::getModule('Database');

        if (! isset($db)) {
            $db = $database->getSelected();
        }

        $key = $db.'/'.$objectClass;
        if (! array_key_exists($key, self::$MetaCache)) {
            if ($database->getSelected() != $db) {
                $database->select($db);
            }
            self::$MetaCache[$key] = new $metaClass($objectClass);
        }
        return self::$MetaCache[$key];
    }

    protected $class;
    protected $table;

    protected $columnMap; // Holds member <-> column map
    protected $columnDef; // Holds database field definitions
    protected $sqlCache;  // Holds sql strings
    protected $manualColumns;

    /**
     * Contains custom sql quieres defined by the DatabaseObject subclass(es);
     * this array will be built by array_merge'ing each subclass's version of
     * this array if it exists in super to sub order.
     *
     * @var array
     */
    protected $customSQL;

    /**
     * meta implementations should define their bulitin queries here,
     * subclasses can supercede these using customSQL
     *
     * @var array
     */
    protected $queries = array();

    protected $_db;

    public function __construct($class, $members)
    {
        $database = Site::getModule('Database');
        $this->_db = $database->getSelected();

        $this->class = $class;
        $this->sqlCache = array();

        $refcls = new ReflectionClass($class);
        $refcls = new ReflectionClass($class);
        $this->customSQL = self::collectStaticArray($refcls, 'CustomSQL');
        $this->manualColumns = self::collectStaticArray($refcls, 'ManualColumns');

        if (! isset($this->table)) {
            throw new RuntimeException(
                "Cannot determine database table for $class"
            );
        }

        // columnMap, just a basic under_scored to camelCased translation
        $this->columnMap = array();

        // columnDef, information on database field details
        $this->columnDef = array();

        foreach ($members as $member) {
            if ($member[0] == '_') {
                continue;
            }
            $column = $this->columnName($member);
            if ($this->inspectColumn($column)) {
                $this->columnMap[$member] = $column;
            }
        }
        ksort($this->columnMap);

        if (isset($this->manualColumns)) {
            foreach ($this->manualColumns as $col) {
                $this->inspectColumn($col);
            }
        }
    }

    public function getDatabase()
    {
        $database = Site::getModule('Database');
        $database->select($this->_db);
        return $database;
    }

    protected function inspectColumn($column)
    {
        if (array_key_exists($column, $this->columnDef)) {
            return true;
        }
        $database = $this->getDatabase();
        $def = $database->getTableFieldDefinition($this->table, $column);
        if (! $def) {
            return false;
        } else {
            $this->columnDef[$column] = $def[0];
            ksort($this->columnDef);
            return true;
        }
    }

    public function isManualColumn($column)
    {
        return
            isset($this->manualColumns) &&
            in_array($column, $this->manualColumns);
    }

    protected function columnName($member)
    {
        // TODO it would be nice to not rely on this externally
        // since it's such a simple translation
        return Params::propertyToField($member);
    }

    /**
     * @return string the name of the class represented by this meta object.
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @return string the sql table name
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Returns an associative array mapping member names to database columns
     *
     * @return array
     */
    public function getColumnMap()
    {
        return $this->columnMap;
    }

    /**
     * Returns the database definition of the column
     *
     * @param column string
     *
     * @return mixed
     */
    public function getColumnDefinition($column)
    {
        if (! array_key_exists($column, $this->columnDef)) {
            throw new Exception("$this->class: No such column '$column' in '$this->table'");
        }
        return $this->columnDef[$column];
    }

    /**
     * Tests whether the given member corresponds to a database column
     *
     * @param member string
     *
     * @return boolean
     */
    public function isColumn($member)
    {
        if (array_key_exists($column, $this->columnDef)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns a sql query string
     *
     * @param op string the name of the operation
     * @return string
     * @see buildSQL
     */
    public function getSQL($op)
    {
        if (! array_key_exists($op, $this->sqlCache)) {
            $this->sqlCache[$op] = $this->buildSQL($op);
        }
        return $this->sqlCache[$op];
    }

    /**
     * Called by buildSQL to expand SQL statement clauses
     *
     * Clauses defined here:
     *   {table}     - the $table property, sql quoted
     *   {colspec}   - getColumnSQL for all auto columns
     *   {filedset}  - getFieldSetSQL for all auto columns
     *
     * @param sql string
     * @return string
     * @see $table, getColumnsSQL, getFieldSetSQL
     */
    protected function expandSQL($sql)
    {
        $sql = str_replace('{table}', $this->table, $sql);
        $sql = str_replace('{colspec}', $this->getColumnsSQL(null, null), $sql);
        $sql = str_replace('{fieldset}', $this->getFieldSetSQL(), $sql);
        return $sql;
    }

    /**
     * Builds a sql query string
     *
     * @param op string operation name, key into $queries or $customSQL
     * @return string
     * @see expandSQL
     */
    public function buildSQL($op)
    {
        if (array_key_exists($op, $this->customSQL)) {
            return $this->expandSQL($this->customSQL[$op]);
        } elseif (array_key_exists($op, $this->queries)) {
            return $this->expandSQL($this->queries[$op]);
        }
        throw new RuntimeException("Invalid sql operation $op for $this->class");
    }

    /**
     * Returns a list of all non-manual columns
     *
     * @return array
     */
    public function getAutomaticColumns() {
        $cols = array_values($this->columnMap);
        $man = array_filter($cols, array($this, 'isManualColumn'));
        return array_diff($cols, $man);
    }

    /**
     * Builds a list of colums for this class's fields, usable in a select statement.
     *
     * @param cols which columns to process, if not set all automatic members
     *   will be used
     * @param prefix string prefix to prepend to the column names, or null to return
     *   bare column names. Optional, defaults to the table name as returned by
     *   getTable())
     * @param glue mixed if set to null, than an array of columns is returned,
     *   otherwise glue is used to implode the array and a string is returned.
     *   The default is to implode on ', '.
     *
     * @return mixed see glue parameter
     */
    public function getColumnsSQL($cols=null, $prefix='', $glue=', ')
    {
        if ( $prefix === null) {
            $prefix = '';
        } else {
            if ($prefix == '') {
                $prefix = $this->table;
            } else {
                # Why is this here... why shouldn't a consumer be able
                # to specify "Database.Table" as a prefix?
                $prefix = str_replace('.', '', $prefix);
            }
            $prefix = "`$prefix`.";
        }

        if (! isset($cols)) {
            $cols = $this->getAutomaticColumns();
        }

        $sql = array();
        foreach ($cols as $column) {
            $def = $this->getColumnDefinition($column);
            switch (strtolower(Params::generic($def, 'native_type'))) {
                // FIXME how about a timezone?
                case 'time':
                    array_push($sql, "TIME_TO_SEC($prefix`$column`) AS `$column`");
                    break;
                case 'date':
                case 'datetime':
                case 'timestamp':
                    array_push($sql, "UNIX_TIMESTAMP($prefix`$column`) AS `$column`");
                    break;
                default:
                    array_push($sql, "$prefix`$column` AS `$column`");
            }
        }

        if ($glue) {
            return implode($glue, $sql);
        } else {
            return $sql;
        }
    }

    /**
     * Builds the needed SQL fragment to update or insert fields.
     *
     * @param bindByName boolean whether to generate named bind parameters, true by
     * default.
     * @param glue mixed if set to null, than an array of columns is returned,
     * otherwise glue is used to implode the array and a string is returned.
     * The default is to implode on ', '.
     *
     * @return string like "col1=:?, col2=:?" or "col1=:col1, col2=:col2"
     */
    public function getFieldSetSQL($cols=null, $bindByName=true, $glue=', ')
    {
        if (! isset($cols)) {
            $cols = $this->getAutomaticColumns();
        }

        $set = array();
        foreach ($cols as $column) {
            if ($this->isManualColumn($column)) {
                continue;
            }

            if ($bindByName) {
                $value = ":$column";
            } else {
                $value = '?';
            }

            $def = $this->getColumnDefinition($column);
            switch (strtolower(Params::generic($def, 'native_type'))) {
                case 'time':
                    $value = "SEC_TO_TIME($value)";
                    break;
                case 'date':
                case 'datetime':
                case 'timestamp':
                    $value = "FROM_UNIXTIME($value)";
                    break;
            }

            array_push($set, "`$column`=$value");
        }

        if ($glue) {
            return implode($glue, $set);
        } else {
            return $set;
        }
    }

    /**
     * utility for constructor
     */
    static protected function collectStaticArray(ReflectionClass $class, $name) {
        $a=array();
        while ($class) {
            if ($class->hasProperty($name)) {
                $prop = $class->getProperty($name);
                if (! $prop->isStatic()) {
                    throw new RuntimeException(
                        "$class->name::\$$name isn't static"
                    );
                }
                $v = $prop->getValue();
                if (! is_array($v)) {
                    throw new RuntimeException(
                        "$class->name::\$$name isn't an array"
                    );
                }
                $a = array_unique(array_merge($a, $v));
            }
            $class = $class->getParentClass();
        }
        return $a;
    }
}

?>
