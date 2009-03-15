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
 * The Original Code is RedTree Framework PageSystem Module
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

class HTMLPageMeta
{
    private $data = array();

    public function keys()
    {
        return array_keyS($this->data);
    }

    public function has($name)
    {
        return array_key_exists($name, $this->data);
    }

    public function get($name)
    {
        if (! array_key_exists($name, $this->data)) {
            return null;
        } else {
            return $this->data[$name];
        }
    }

    public function set($name, $value)
    {
        if (! isset($value)) {
            if (array_key_exists($name, $this->data)) {
                unset($this->data[$name]);
            }
        } else {
            $this->data[$name] = $value;
        }
    }

    public function setArray($data)
    {
        assert(is_array($data));
        foreach ($data as $n => $v) {
            $this->set($n, $v);
        }
    }

    public function add($name, $value)
    {
        if (! array_key_exists($name, $this->data)) {
            $this->data[$name] = array();
        } elseif(! is_array($this->data[$name])) {
            $this->data[$name] = array($this->data[$name]);
        }
        array_push($this->data[$name], $value);
    }

    public function clear($name)
    {
        if (array_key_exists($name, $this->data)) {
            unset($this->data[$name]);
        }
    }
}

?>
