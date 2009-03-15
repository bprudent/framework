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

/**
 * Implements a stupid path
 *
 * This is essentially an array that is string equivalent at all times:
 *
 *   $p = new StupidPath('/a/b/c');
 *   echo $p; // /a/b/c
 *   echo json_encode($p->components); // ['a','b','c']
 *   echo $p->up(); // /a/b
 *   echo $p->up(2); // /a
 *   echo $p->up()->down('d', 'file.css'); // /a/b/d/file.css
 */
class StupidPath
{
    public $components;

    /**
     * Constructs a new stupid path
     *
     * @param a string or array
     */
    public function __construct($a)
    {
        if (is_string($a)) {
            $a = explode('/', $a);
        }
        $this->components = $a;
    }

    /**
     * Returns string path
     */
    public function __tostring()
    {
        return implode('/', $this->components);
    }

    /**
     * Creates a new StupidPath based on the first count($componets)-$n
     * elemnets and returns it.
     *
     * @param n int optinonal defaults to 1
     * @return StupidPath
     */
    public function up($n=1)
    {
        return new StupidPath(array_slice(
            $this->components, 0, count($this->components)-$n
        ));
    }

    /**
     * Creates a new StupidPath with all arguments appended to $components and
     * returns it.
     *
     * @param <args> strings
     *   If any string contains a '/' it will be exploded and merged as if
     *   each elemnt had been given individually.  If a component is '..' it's
     *   equivalent to calling up(1), while '.' is a no-op.
     *
     * @return StupidPath
     */
    public function down()
    {
        $add = func_get_args();
        if (is_array($add[0])) {
            $add = $add[0];
        }

        $a = array();
        foreach ($add as $c) {
            $a = array_merge($a, explode('/', $c));
        }

        $path = $this->components;
        foreach ($a as $c) {
            if ($c == '.') {
                // pass
            } elseif ($c == '..' && count($path)) {
                array_pop($path);
            } else {
                array_push($path, $c);
            }
        }
        return new StupidPath($path);
    }

    public function rel2abs($path)
    {
        if (
            (is_string($path) && $path[0] == '/') ||
            (is_array($path) && $path[0] == '')
        ) {
            return new self($path);
        } else {
            return $this->down($path);
        }
    }
}

?>
