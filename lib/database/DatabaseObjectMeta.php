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

/**
 * @see DatabaseObject::meta
 */

require_once dirname(__FILE__).'/DatabaseObjectAbstractMeta.php';

class DatabaseObjectMeta extends DatabaseObjectAbstractMeta
{
    /**
     * Static singlton manager
     *
     * Returns the meta object for a given DatabaseObjcet subclass
     *
     * @param class string the class
     * @param string $db the database that this meta is valid in
     *
     * @return DatabaseObjectMeta
     */
    static public function forClass($class, $db=null)
    {
        assert(class_exists($class));
        assert(is_subclass_of($class, 'DatabaseObject'));
        return self::metaForClass($class, __CLASS__, $db);
    }

    protected $key=null;

    protected $queries = array(
        'dbo_select' =>
            'SELECT {colspec} FROM {table} WHERE {key}=? LIMIT 1',
        'dbo_insert' =>
            'INSERT INTO {table} SET {fieldset}',
        'dbo_update' =>
            'UPDATE {table} SET {fieldset} WHERE {key}={keybind} LIMIT 1',
        'dbo_delete' =>
            'DELETE FROM {table} WHERE {key}=?'
    );

    /**
     * Constructor
     *
     * This shouldn't be called directly
     *
     * @see DatabaseObject::meta
     *
     * @param class string
     */
    function __construct($class)
    {
        $members = array();
        $refcls = new ReflectionClass($class);
        foreach ($refcls->getProperties() as $prop) {
            $name = $prop->getName();
            switch ($name) {
            case 'table':
            case 'key':
                if (! $prop->isStatic() && Site::Site()->isDebugMode()) {
                    trigger_error("$class->$name should be $class::\$$name");
                }
                $this->$name = $prop->getValue();
                break;
            default:
                if (! $prop->isStatic()) {
                    array_push($members, $name);
                }
                break;
            }
        }

        if (! isset($this->key)) {
            throw new RuntimeException(
                "Cannot determine database key for $class"
            );
        }

        parent::__construct($class, $members);
    }

    protected function columnName($member)
    {
        if ($member == 'id') {
            return $this->key;
        } else {
            return parent::columnName($member);
        }
    }

    /**
     * @return string the name of the primary key column in this table
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Expands a SQL string
     *
     * Clauses defined here:
     *   {key}      - the $key property, sql quoted
     *   {keybind}  - a string like ":table_id" for use as a named placeholder
     *
     * @param sql string
     * @return string
     * @see $key, DatabaseObjectAbstractMeta::expandSQL
     */
    protected function expandSQL($sql)
    {
        $sql = parent::expandSQL($sql);
        $sql = str_replace('{key}', "`$this->key`", $sql);
        $sql = str_replace('{keybind}', ":$this->key", $sql);
        return $sql;
    }

    public function isManualColumn($column)
    {
        if ($column == $this->key) {
            return true;
        }
        return parent::isManualColumn($column);
    }
}

?>
