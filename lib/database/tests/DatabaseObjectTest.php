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

// If called directly from command line
if (realpath($_SERVER['SCRIPT_FILENAME']) == __FILE__) {
    $APP = 'test';
    require_once dirname(__FILE__).'/../../../../index.php';
}

/**
 * This class is meant for testing DatabaseObject, every feature of
 * DatabaseObject should be demonstrated here in some way.
 */

class DatabaseObjectTest extends FrameworkTestCase
{
    private $dbObserverHandle;
    private $db = null;
    public $verbose = false;

    public function setUp()
    {
        $this->db = Site::getModule('Database');

        // hook database events
        $this->dbObserverHandle =& $this->db->observe(
            array($this, 'onDatabaseEvent')
        );
    }

    public function tearDown()
    {
        // reverse converse of setUp
        $this->db->stopObserving($this->dbObserverHandle);
        $this->db = null;
    }

    // handler for database events, hooked by setUp
    public function onDatabaseEvent($type, $what, $data)
    {
        switch ($type) {
            case Database::INFO:
                if ($this->verbose) {
                    print "Database activity $what ";
                    if (array_key_exists('extra', $data)) {
                        print implode(' ', $data['extra']);
                    }
                    print "\n";
                }
                if ($this->expecting()) {
                    $this->verify($what);
                } else {
                    $this->fail(
                        "Unexpected database activity: $what"
                    );
                }
                break;
            case Database::ERROR:
                $why = $data['why'];
                $this->fail("Database::$what failed: $why");
                break;
            default:
                $this->fail(
                    "Database notified us of a bogus event type($type)"
                );
                break;
        }
    }

    // Expects to see a string == action(detail)
    public function expectExact($action, $detail, $multiplicity=1, $message='%s')
    {
        $this->expect(
            new EqualExpectation("$action($detail)"),
            $multiplicity, $message
        );
    }

    // Expects to match a string action(pattern)
    public function expectMatch($action, $pattern, $multiplicity=1, $message='%s')
    {
        $this->expect(
            new PatternExpectation("$action\($pattern\)"),
            $multiplicity, $message
        );
    }

    public function testDummy()
    {
        { // Test meta object
            $meta = DatabaseObjectMeta::forClass('DBODummy');
            $table = $meta->getTable();
            $key = $meta->getKey();

            $this->assertEqual('DBODummy', $meta->getClass());
            $this->assertEqual('dbodummy', $table);
            $this->assertEqual('dbodummy_id', $key);

            $colMap = $meta->getColumnMap();
            $this->assertEqual($colMap["aDate"],     "a_date");
            $this->assertEqual($colMap["aDateTime"], "a_date_time");
            $this->assertEqual($colMap["aTime"],     "a_time");
            $this->assertEqual($colMap["mess"],      "mess");
            $this->assertEqual($colMap["id"],        "dbodummy_id");

            $fieldSet = $meta->getFieldSetSQL();
            $this->assertEqual($fieldSet, implode(', ', array(
                '`a_date`=FROM_UNIXTIME(:a_date)',
                '`a_date_time`=FROM_UNIXTIME(:a_date_time)',
                '`a_time`=SEC_TO_TIME(:a_time)',
                '`mess`=:mess'
            )));

            $pfx = "`$table`.";
            $colSQL = $meta->getColumnsSQL();
            $this->assertEqual($colSQL, implode(', ', array(
                "UNIX_TIMESTAMP($pfx`a_date`) AS `a_date`",
                "UNIX_TIMESTAMP($pfx`a_date_time`) AS `a_date_time`",
                "TIME_TO_SEC($pfx`a_time`) AS `a_time`",
                "$pfx`mess` AS `mess`"
            )));

            $colSQL = $meta->getColumnsSQL(null, null);
            $this->assertEqual($colSQL, implode(', ', array(
                "UNIX_TIMESTAMP(`a_date`) AS `a_date`",
                "UNIX_TIMESTAMP(`a_date_time`) AS `a_date_time`",
                "TIME_TO_SEC(`a_time`) AS `a_time`",
                "`mess` AS `mess`"
            )));
        }

        $drop = "DROP TABLE IF EXISTS $table";

        $create =
            "CREATE TABLE $table (\n  ".
            implode(",\n  ", DBODummy::$CreateSpec).
            "\n)";

        $this->expectExact('perform', $drop);
        $this->expectExact('perform', $create);

        $this->db->perform($drop);
        $this->db->perform($create);

        $dummy = new DBODummy();

        { // Create a dummy
            $this->populate($dummy);
            // TODO populate really should be a method on the object or its meta

            $this->expectExact('lock', "LOCK TABLES $table WRITE");
            $this->expectExact('prepare',
                "INSERT INTO `$table` SET $fieldSet"
            );
            $this->expectExact('execute', json_encode(array(
                ":a_date"      => (int) $dummy->aDate,
                ":a_date_time" => (int) $dummy->aDateTime,
                ":a_time"      => (int) $dummy->aTime,
                ":mess"        => $dummy->mess
            )));
            $this->expectExact('lastInsertId', 1); // The table is virgin
            $this->expectExact('unlock', 'UNLOCK TABLES');

            $dummy->create();
        }

        { // Change the dummy and save
            $this->populate($dummy);
            $this->expectExact('prepare',
                "UPDATE `$table` SET $fieldSet WHERE `$key` = :$key LIMIT 1"
            );
            $this->expectExact('execute', json_encode(array(
                ":a_date"      => (int) $dummy->aDate,
                ":a_date_time" => (int) $dummy->aDateTime,
                ":a_time"      => (int) $dummy->aTime,
                ":mess"        => $dummy->mess,
                ":dbodummy_id" => $dummy->id
            )));

            $dummy->update();
        }

        { // Fetch a dummy
            $dummyId = $dummy->id;
            $dummyData = array(
                $dummy->aDate,
                $dummy->aDateTime,
                $dummy->aTime,
                $dummy->mess
            );
            $dummy = null;

            $this->expectExact('prepare',
                "SELECT $colSQL FROM `$table` WHERE `$key` = ? LIMIT 1"
            );
            $this->expectExact('executef', json_encode(array($dummyId)));

            $dummy = DatabaseObject::load('DBODummy', $dummyId);

            $this->assertEqual($dummyId, $dummy->id);
            $this->assertEqual($dummyData[0], $dummy->aDate);
            $this->assertEqual($dummyData[1], $dummy->aDateTime);
            $this->assertEqual($dummyData[2], $dummy->aTime);
            $this->assertEqual($dummyData[3], $dummy->mess);
        }

        { // Kill a dummy
            $this->expectExact('prepare',
                "DELETE FROM `$table` WHERE `$key` = ?"
            );
            $this->expectExact('executef', json_encode(array($dummyId)));

            $dummy->delete();

            $this->assertEqual(-1, $dummy->id);

            // Delete only clears id
            $this->assertEqual($dummyData[0], $dummy->aDate);
            $this->assertEqual($dummyData[1], $dummy->aDateTime);
            $this->assertEqual($dummyData[2], $dummy->aTime);
            $this->assertEqual($dummyData[3], $dummy->mess);

            // TODO once wipe added, values should be nullifed
        }

        // TODO update a non-existent id should fail
        // TODO fetch non-existent id should fail
    }
}

class DBODummy extends DatabaseObject
{
    /**
     * Definitions
     */
    static public $table = 'dbodummy';
    static public $key = 'dbodummy_id';
    static public $CreateSpec = array(
        'dbodummy_id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT',
        'mess VARCHAR(255)',
        'a_date DATE',
        'a_time TIME',
        'a_date_time DATETIME'
    );


    /**
     * Fields
     */
    public $mess;
    public $aDate;
    public $aTime;
    public $aDateTime;
}

?>
