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
 * An asynchronous unit test, basically the notion is that you're testing some
 * codepath that fires callbacks.
 */
class ASyncUnitTest extends UnitTestCase
{
    protected $expectationStack = array();

    /**
     * Pushes an expectation onto the stack
     *
     * @param expectation Expectation object to verify when data is available
     * @param multiplicity int each time verify is ran, it will decrement the
     *        expectation's multiplicity, once it reaches zero, it's poped from
     *        the stack.
     * @param message string optional string, if null assert's default will be
     *        used
     *
     * @return void
     */
    public function expect(&$expectation, $multiplicity=1, $message=null)
    {
        if (! is_int($multiplicity) || $multiplicity <= 0) {
            throw new InvalidArgumentException(
                "Invalid expectation multiplicity"
            );
        }

        array_push($this->expectationStack, array(
            $expectation, $multiplicity, $message
        ));
    }


    /**
     * Whether we're expecting anything
     *
     * @return boolean true if there are any expectations on the stack
     */
    public function expecting()
    {
        if (count($this->expectationStack)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks the given value against the expectation set by expect
     *
     * @param compare mixed value to compare
     * @param message string optional custom message to use
     *
     * @return boolean true on pass
     */
    public function verify($compare, $message=null)
    {
        if (! $this->expecting()) {
            throw new RuntimeException("No expectation to verify");
        }

        // reference to the head
        $ex =& $this->expectationStack[0];

        // if message not specified to verify(), use what was provided in expect()
        if (! isset($message)) {
            $message = $ex[2];
        }

        // test the expectation
        if (isset($message)) {
            $r = $this->assert($ex[0], $compare, $message);
        } else {
            $r = $this->assert($ex[0], $compare);
        }

        // decrement multiplicity
        if (--$ex[1] < 1) {
            array_shift($this->expectationStack);
        }

        return $r;
    }
}

?>
