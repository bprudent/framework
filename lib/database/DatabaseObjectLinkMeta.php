<?php

/**
 * DatabaseObjectLinkMeta class definition
 *
 * PHP version 5
 *
 * LICENSE: The contents of this file are subject to the Mozilla Public License Version 1.1
 * (the "License"); you may not use this file except in compliance with the
 * License. You may obtain a copy of the License at http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License for
 * the specific language governing rights and limitations under the License.
 *
 * The Original Code is Red Tree Systems Code.
 *
 * The Initial Developer of the Original Code is Red Tree Systems, LLC. All Rights Reserved.
 *
 * @author       Red Tree Systems, LLC <support@redtreesystems.com>
 * @copyright    2007 Red Tree Systems, LLC
 * @license      MPL 1.1
 * @version      1.0
 * @link         http://framework.redtreesystems.com
 */

require_once dirname(__FILE__).'/DatabaseObjectAbstractMeta.php';

class DatabaseObjectLinkMeta extends DatabaseObjectAbstractMeta
{
    /**
     * Static storage for meta objects
     */
    static private $ClassMeta = array();

    /**
     * Static singlton manager
     *
     * Returns the meta object for a given DatabaseObjcetLink subclass
     *
     * @param class string the class
     * @param string $db the database that this meta is valid in
     *
     * @return DatabaseObjectLinkMeta
     */
    static public function forClass($class, $db=null)
    {
        assert(class_exists($class));
        assert(is_subclass_of($class, 'DatabaseObjectLink'));

        if (! isset($db)) {
            $database = Site::getModule('Database');
            $db = $database->getSelected();
        }
        $key = $db.'/'.$class;
        if (! array_key_exists($key, self::$ClassMeta)) {
            $database = Site::getModule('Database');
            if ($database->getSelected() != $db) {
                $database->select($db);
            }
            self::$ClassMeta[$key] = new self($class);
        }
        return self::$ClassMeta[$key];
    }

    protected $queries = array(
        'link_insert' =>
            'INSERT INTO {table} SET {keyspec}{+fieldset}',
        'link_update' =>
            'UPDATE {table} SET {fieldset} WHERE {keyspec}',
        'link_delete' =>
            'DELETE FROM {table} WHERE {keyspec}',
        'link_delete_from' =>
            'DELETE FROM {table} WHERE {from_key}=?',
        'link_delete_to' =>
            'DELETE to {table} WHERE {to_key}=?',
        'link_load_from' =>
            'SELECT {to_key}{+addkey}{+colspec} FROM {table} WHERE {from_key}=? ORDER BY {orderfrom}',
        'link_load_to' =>
            'SELECT {from_key}{+addkey}{+colspec} FROM {table} WHERE {to_key}=? ORDER BY {orderto}'
    );

    protected $fromClass;
    protected $toClass;
    protected $additionalKey;
    private $keyColumns;

    public function __construct($class)
    {
        $refcls = new ReflectionClass($class);

        $members = array();
        foreach ($refcls->getProperties() as $prop) {
            $name = $prop->getName();
            switch ($name) {
            case 'table':
            case 'FromClass':
            case 'ToClass':
            case 'LinkOrderClause':
            case 'AdditionalKey':
                if (! $prop->isStatic() && Site::Site()->isDebugMode()) {
                    throw new RuntimeException(
                        "$class->$name should be $class::\$$name"
                    );
                }
                $m = strtolower($name[0]).substr($name, 1);
                $v = $prop->getValue();
                if ($name == 'FromClass' || $name == 'ToClass') {
                    if (
                        ! class_exists($v) ||
                        ! is_subclass_of($v, 'DatabaseObject')
                    ) {
                        throw new RuntimeException(
                            "$v is not a DatabaseObject class"
                        );
                    }
                }
                $this->$m = $v;
                break;
            default:
                if (! $prop->isStatic()) {
                    array_push($members, $name);
                }
                break;
            }
        }

        if (! isset($this->fromClass)) {
            throw new RuntimeException(
                "Cannot determine database fromClass for $class"
            );
        }
        if (! isset($this->toClass)) {
            throw new RuntimeException(
                "Cannot determine database toClass for $class"
            );
        }

        parent::__construct($class, $members);
        $this->inspectColumn($this->getFromKey());
        $this->inspectColumn($this->getToKey());

        if (isset($this->additionalKey)) {
            $man = isset($this->manualColumns)
                ? $this->manualColumns : array();
            $this->manualColumns = array_unique(array_merge(
                $man, $this->additionalKey
            ));
            foreach ($this->additionalKey as $col) {
                $this->inspectColumn($col);
            }
        }
    }

    public function getKeyColumns()
    {
        if (! isset($this->keyColumns)) {
            $l = array(
                $this->getFromKey(),
                $this->getToKey()
            );
            if (isset($this->additionalKey)) {
                $colmap = $this->getColumnMap();
                foreach ($this->additionalKey as $addKey) {
                    if (! array_key_exists($addKey, $colmap)) {
                        throw new RuntimeException("$addKey column doesn't exist");
                    }
                    array_push($l, $colmap[$addKey]);
                }
            }
            $this->keyColumns = $l;
        }
        return $this->keyColumns;
    }

    protected function expandSQL($sql)
    {
        $sql = parent::expandSQL($sql);

        $auto = $this->getAutomaticColumns();
        if (count($auto)) {
            $colspec = ', '.$this->getColumnsSQL($auto, null);
            $fieldset = ', '.$this->getFieldSetSQL($auto, false);
        } else {
            $colspec = '';
            $fieldset = '';
        }
        $sql = str_replace('{+colspec}', $colspec, $sql);
        $sql = str_replace('{+fieldset}', $fieldset, $sql);

        if (isset($this->additionalKey)) {
            $addkey = ', '.$this->getColumnsSQL($this->additionalKey, null);
        } else {
            $addkey = '';
        }
        $sql = str_replace('{+addkey}', $addkey, $sql);

        $sql = str_replace('{orderfrom}', $this->linkOrderClause[0], $sql);
        $sql = str_replace('{orderto}', $this->linkOrderClause[1], $sql);
        $sql = str_replace('{from_key}', $this->getFromKey(), $sql);
        $sql = str_replace('{to_key}', $this->getToKey(), $sql);

        $keyList = $this->getKeyColumns();
        $sql = str_replace('{keyspec}', $this->getFieldSetSQL($keyList), $sql);

        return $sql;
    }

    public function getFromClass()
    {
        return $this->fromClass;
    }

    public function getToClass()
    {
        return $this->toClass;
    }

    public function getFromMeta()
    {
        return DatabaseObjectMeta::forClass($this->fromClass);
    }

    public function getToMeta()
    {
        return DatabaseObjectMeta::forClass($this->toClass);
    }

    public function getFromKey()
    {
        return $this->getFromMeta()->getTable().'_id';
    }

    public function getToKey()
    {
        return $this->getToMeta()->getTable().'_id';
    }

    public function getAdditionalKey()
    {
        return isset($this->additionalKey)
            ? $this->additionalKey : array();
    }
}

?>
