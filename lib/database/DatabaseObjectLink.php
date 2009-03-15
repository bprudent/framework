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

require_once dirname(__FILE__).'/DatabaseObjectAbstract.php';
require_once dirname(__FILE__).'/DatabaseObjectLinkMeta.php';

/**
 * Represents managed links between database objects
 *
 * Example:
 *   class DBOA extends DatabaseObject
 *   {
 *     public static $table = 'obj_a';
 *     public static $key = 'obj_a_id';
 *   }
 *
 *   class DBOB extends DatabaseObject
 *   {
 *     public static $table = 'obj_b';
 *     public static $key = 'obj_b_id';
 *   }
 *
 *   class DBOABLink extends DatabaseObjectLink
 *   {
 *     public static $FromClass = 'DBOA';
 *     public static $ToClass = 'DBOB';
 *   }
 *
 *   FIXME
 */
abstract class DatabaseObjectLink extends DatabaseObjectAbstract
{
    protected $from;
    protected $to;

    public static function deleteFor($linkClass, DatabaseObject $for)
    {
        assert(class_exists($linkClass));
        assert(is_subclass_of($linkClass, __CLASS__));

        if (! isset($for->id)) {
            throw new InvalidArgumentException('unsaved DatabaseObject');
        }

        $database = $for->getDatabase();
        $meta = DatabaseObjectLinkMeta::forClass($linkClass, $for->_db);
        $ok = false;
        if (is_a($for, $meta->getFromClass())) {
            $ok = true;
            $sql = $meta->getSQL('link_delete_from');
            $database->execute($sql, $for->id);
        }
        if (is_a($for, $meta->getToClass())) {
            $ok = true;
            $sql = $meta->getSQL('link_delete_to');
            $database->execute($sql, $for->id);
        }
        if (! $ok) {
            throw new InvalidArgumentException(
                'invalid DatabaseObject, expecting a '.$meta->getFromClass().
                'or a '.$meta->getToClass()
            );
        }
    }

    public static function loadFor($linkClass, DatabaseObject $for)
    {
        assert(class_exists($linkClass));
        assert(is_subclass_of($linkClass, __CLASS__));

        if (! isset($for->id)) {
            throw new InvalidArgumentException('unsaved DatabaseObject');
        }

        $database = $for->getDatabase();
        $meta = DatabaseObjectLinkMeta::forClass($linkClass, $for->_db);
        $data = array();
        if (is_a($for, $meta->getFromClass())) {
            $sql = $meta->getSQL('link_load_from');
            $data['from'] = $for;
            $other = 'to';
            $otherClass = $meta->getToClass();
        } elseif (is_a($for, $meta->getToClass())) {
            $sql = $meta->getSQL('link_load_to');
            $data['to'] = $for;
            $other = 'from';
            $otherClass = $meta->getFromClass();
        } else {
            throw new InvalidArgumentException(
                'invalid DatabaseObject, expecting a '.$meta->getFromClass().
                'or a '.$meta->getToClass()
            );
        }

        $otherId = null;
        $list = $database->execute($sql, $for->id);

        $list->bindColumn(1, $otherId);
        $i = 1;
        foreach (array_merge(
            $meta->getAdditionalKey(),
            $meta->getAutomaticColumns()
        ) as $column) {
            $data[$column] = null;
            $list->bindColumn(++$i, $data[$column]);
        }

        $r = array();
        $call = array($linkClass, 'factory');
        while ($list->fetch()) {
            $data[$other] = DatabaseObject::load($otherClass, $otherId);
            array_push($r, call_user_func($call, $data));
        }
        return $r;
    }

    public static $LinkOrderClause = array('{from_key}', '{to_key}');

    public function __construct($from, $to)
    {
        parent::__construct();
        $meta = $this->meta();
        if (is_array($from)) { // loading not creating
            assert(array_key_exists('from', $from));
            assert(array_key_exists('to', $from));
            $this->from = $from['from'];
            $this->to = $from['to'];
            foreach ($meta->getColumnMap() as $prop => $col) {
                if (array_key_exists($col, $from)) {
                    $this->$prop = $from[$col];
                }
            }
        } else {
            if (! is_a($from, $meta->getFromClass())) {
                throw new InvalidArgumentException(
                    'invalid from object expecting a '.$meta->getFromClass()
                );
            }
            if (! is_a($to, $meta->getToClass())) {
                throw new InvalidArgumentException(
                    'invalid to object expecting a '.$meta->getToClass()
                );
            }
            if (! isset($from->id)) {
                throw new InvalidArgumentException(
                    'Can\'t link unsaved '.get_class($from)
                );
            }
            if (! isset($to->id)) {
                throw new InvalidArgumentException(
                    'Can\'t link unsaved '.get_class($to)
                );
            }
            $this->from = $from;
            $this->to = $to;

            $this->doInsert();
        }
    }

    public function meta()
    {
        return DatabaseObjectLinkMeta::forClass(get_class($this), $this->_db);
    }

    protected function lockTables($database)
    {
        $database = $this->getDatabase();
        $meta = $this->meta();
        $database->lock(
            array(
                $meta->getFromMeta()->getTable(),
                $meta->getToMeta()->getTable(),
                $meta->getTable()
            ), Database::LOCK_WRITE
        );
    }

    protected function doInsert()
    {
        $database = $this->getDatabase();
        $this->lockTables();
        try {
            $sql = $this->meta()->getSQL('link_insert');
            $values = array_merge(
                $this->keyValue(),
                $this->autoValues()
            );
            $database->prepare($sql)->execute($values);
        } catch (Exception $e) {
            $database->unlock();
            throw $e;
        }
        $database->unlock();
    }

    public function update()
    {
        $meta = $this->meta();
        if (! count($meta->getAutomaticColumns())) {
            return;
        }

        $database = $this->getDatabase();
        $sql = $this->meta()->getSQL('link_update');
        $values = array_merge(
            $this->keyValue(),
            $this->autoValues()
        );
        $database->prepare($sql)->execute($values);
    }

    public function delete()
    {
        $database = $this->getDatabase();
        $sql = $this->meta()->getSQL('link_delete');
        $database->prepare($sql)->execute($this->keyValue());
    }

    protected function keyValue()
    {
        $r = array();
        $r[$this->getFromKey()] = $this->from->id;
        $r[$this->getToKey()] = $this->to->id;

        $meta = $this->meta();
        $addKey = $meta->getAdditionalKey();
        if (isset($addKey)) {
            $colmap = $meta->getColumnMap();
            foreach ($addKey as $prop) {
                $r[$colmap[$prop]] = $this->$prop;
            }
        }
        return $r;
    }

    protected function autoValues()
    {
        $r = array();
        $meta = $this->meta();
        $colmap = $meta->getColumnMap();
        foreach ($colmap as $prop => $col) {
            if (! $this->isManualColumn($col)) {
                $r[$col] = $this->$prop;
            }
        }
        return $r;
    }
}

?>
