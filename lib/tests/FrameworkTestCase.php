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
 * The Original Code is RedTree Framework Test Module
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

class FrameworkTestCase extends ASyncUnitTest
{
    /**
     * A utility method to truncate the given table(s)
     *
     * @param mixed $tables the table(s) to be truncated. accepts a string or array of strings
     * @return void
     */
    protected function truncate($tables)
    {
        $database = Site::getModule('Database');

        if (!is_array($tables)) {
            $tables = array($tables);
        }

        foreach ($tables as $table) {
            $database->query("TRUNCATE TABLE `$table`");
        }
    }

    /**
     * Populates the given DatabaseObject with dummy data
     *
     * @param DatabaseObject $obj
     * @return void
     */
    protected function populate(DatabaseObject &$obj)
    {
        $meta = $obj->meta();
        $key = $meta->getKey();
        $fields = $meta->getColumnMap();
        foreach ($fields as $property => $field) {
            if ($field == $key && isset($obj->$property)) {
                continue;
            }

            $def = $meta->getColumnDefinition($field);
            $type = strtolower(Params::generic($def, 'native_type'));
            switch ($type) {
                case 'int':
                case 'integer':
                case 'float':
                case 'currency':
                case 'decimal':
                case 'double':
                case 'real':
                case 'tinyint':
                case 'short':
                case 'long':
                    $obj->$property = mt_rand(0, 100);
                    break;
                case 'date':
                case 'datetime':
                case 'timestamp':
                    // Fuzz two weeks around now
                    $window = 1209600;  // 14 days * 24 hours * 60 minutes * 60 seconds
                    $time = time() - $window/2 + mt_rand(0, $window);
                    if ($type == 'date') {
                        $lt = localtime($time, true);
                        $time = mktime(0, 0, 0,
                            $lt['tm_mday'],
                            $lt['tm_mon']+1,
                            $lt['tm_year']+1900
                        );
                        $lt = null;
                    }
                    $obj->$property = $time;
                    break;
                case 'time':
                    // range is +- 838:59:59, but since this populate thing
                    // isn't really all it could be anyhow, let's just do
                    // positive values
                    $obj->$property = mt_rand(1, 839*60*60-1);
                    break;
                case 'var_string':
                    $obj->$property = 'dummy string content '.uniqid();
                    break;
                default:
                    $obj->$property = uniqid();
                    break;
            }
        }
    }
}

?>
