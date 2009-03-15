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

require_once dirname(__FILE__).'/DatabaseObjectAbstractMeta.php';

class DatabaseObjectLinkMeta extends DatabaseObjectAbstractMeta
{
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
        return self::metaForClass($class, __CLASS__, $db);
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
