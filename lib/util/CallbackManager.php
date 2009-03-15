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

require_once 'lib/exceptions/StopException.php';

abstract class CallbackManager
{
    /**
     * Array of named callbacks
     *
     * @see addCallback, dispatchCallback, marshallCallback
     */
    private $callbacks = array();

    final public function hasCallback($name)
    {
        return
            array_key_exists($name, $this->callbacks) &&
            count($this->callbacks[$name]);
    }

    final public function addCallback($name, $callable)
    {
        if (! array_key_exists($name, $this->callbacks)) {
            $this->callbacks[$name] = array();
        }
        array_push($this->callbacks[$name], $callable);
    }

    /**
     * Calls each callback in the named callback list.
     *
     * If a callback throws a StopException, it halts the execution of the list
     *
     * @param name string
     * @return mixed if a StopException is raised, it is returned, null otherwise
     * @see addCallback
     */
    final public function dispatchCallback($name)
    {
        if (! array_key_exists($name, $this->callbacks)) {
            return;
        }
        $args = array_slice(func_get_args(), 1);
        try {
            foreach ($this->callbacks[$name] as $call) {
                call_user_func_array($call, $args);
            }
        } catch (StopException $s) {
            return $s;
        }
    }

    /**
     * Like dispatchCallback, but collects all non-null return values from the
     * callbacks and returns an array of them.
     *
     * @param name string
     * @return mixed StopException as in dispatchCallback, array otherwise
     * @see dispatchCallback
     */
    final public function marshallCallback($name)
    {
        if (! array_key_exists($name, $this->callbacks)) {
            return;
        }
        $args = array_slice(func_get_args(), 1);
        $ret = array();
        try {
            foreach ($this->callbacks[$name] as $call) {
                $r = call_user_func_array($call, $args);
                if (isset($r)) {
                    array_push($ret, $r);
                }
            }
        } catch (StopException $s) {
            return $s;
        }
        return $ret;
    }

    /**
     * Like marshallCallback, except stops on the first non-null return and
     * returns it rather than collecting an array
     *
     * @param name string
     * @return mixed StopException as in dispatchCallback, mixed otherwise
     * @see dispatchCallback
     */
    final public function marshallSingleCallback($name)
    {
        if (! array_key_exists($name, $this->callbacks)) {
            return;
        }
        $args = array_slice(func_get_args(), 1);
        try {
            foreach ($this->callbacks[$name] as $call) {
                $r = call_user_func_array($call, $args);
                if (isset($r)) {
                    return $r;
                }
            }
        } catch (StopException $s) {
            return $s;
        }
        return null;
    }
}

?>
