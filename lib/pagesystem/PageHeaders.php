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

/**
 * Describes outgoing HTTP headers
 */

class PageHeaders
{
    private $table = array();
    private $statusCode = null;

    /**
     * Sets the Status code and message
     *
     * @param code int
     * @param mess string optional
     */
    public function setStatus($code, $mess)
    {
        assert(is_int($code));
        $this->statusCode = $code;
        $this->set('Status', "$code $mess");
    }

    /**
     * Convenience for get('Status')
     */
    public function getStatus()
    {
        return $this->get('Status');
    }

    /**
     * Returns the status code
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * A convenience that returns the content type less any charset field
     *
     * @return string
     */
    public function getContentType()
    {
        $type = $this->get('Content-Type');
        if (! isset($type)) {
            return null;
        }
        $i = strpos($type, ';');
        return $i === false ? $type : substr($type, 0, $i);
    }

    /**
     * A convenience to set the Content-Type header
     *
     * @param type string
     * @param charset string optional
     * @return void
     */
    public function setContentType($type, $charset=null)
    {
        if (isset($charset)) {
            $this->set('Content-Type', "$type; charset=$charset");
        } else {
            $this->set('Content-Type', $type);
        }
    }

    /**
     * A convenience to set the charset=CHARSET field of the Content-Type header
     * preserving the existing Content-TYpe
     *
     * @param charset string
     * @return void
     */
    public function setContentTypeCharset($charset)
    {
        $type = $this->getContentType();
        if (! isset($type)) {
            return;
        }
        if (isset($charset)) {
            $type = "$type; charset=$charset";
        }
        $this->setContentType($type);
    }

    /**
     * A convenience to set the Content-Disposition header while preserving any
     * filename field set already
     *
     * @param value string defaultsl to 'attachement'
     * @return void
     */
    public function setContentDisposition($value='attachement')
    {
        $hdr = $this->get('Content-Disposition');
        if (isset($hdr)) {
            $i = strpos($hdr, ';');
            $hdr = $i === false ? $hdr : substr($hdr, $i);
        } else {
            $hdr = '';
        }
        $this->set('Content-Disposition', "$value$hdr");
    }

    /**
     * A convenience to easily set the filename="FILENAME" portion of the
     * Content-Disposition header, if the Content-Disposition header isn't set,
     * this will set it to attachment
     *
     * @param filename string
     * @return void
     */
    public function setContentFileName($filename)
    {
        $hdr = $this->get('Content-Disposition');
        if (isset($hdr)) {
            $i = strpos($hdr, ';');
            $hdr = $i === false ? $hdr : substr($hdr, 0, $i);
        } else {
            $hdr = 'attachment';
        }
        if (isset($filename)) {
            $hdr = "$hdr; filename=\"$filename\"";
        }
        $this->set('Content-Disposition', $hdr);
    }

    public function has($name)
    {
        return array_key_exists($name, $this->table);
    }

    public function names()
    {
        return array_keys($this->table);
    }

    /**
     * Gets the header value, this may be an array for multi-valued headers
     *
     * @param get string
     * @param asArray default false, if true will upgrade singletons to an
     * array, and return empty array instead of null, if false, only the first
     * value of a multi-valued header is returned
     * @return mixed
     * @see add
     */
    public function get($name, $asArray=false)
    {
        if (array_key_exists($name, $this->table)) {
            if ($asArray) {
                return is_array($this->table[$name])
                    ? $this->table[$name]
                    : array($this->table[$name]);
            } else {
                return is_array($this->table[$name])
                    ? $this->table[$name][0]
                    : $this->table[$name];
            }
        } else {
            return $asArray ? array() : null;
        }
    }

    /**
     * Adds a header value, if relpace is true the header will be forced to a
     * singleton, otherwise, multiple calls to add for the same value will
     * result in an array, however the first call will create only a singleton:
     *   $hdr = new PageHeaders();
     *   $hdr->add('Foo', 'a');
     *     $hdr->get('Foo') == 'a'
     *     $hdr->get('Foo', true) == array('a')
     *   $hdr->add('Foo', 'b');
     *     $hdr->get('Foo') == 'a'
     *     $hdr->get('Foo', true) == array('a', 'b')
     *
     * @param name string
     * @param value mixed
     * @param replace boolean default false
     * @return void
     */
    public function add($name, $value, $replace=false)
    {
        if (array_key_exists($name, $this->table)) {
            if ($replace) {
                if (isset($value)) {
                    $this->table[$name] = $value;
                } else {
                    unset($this->table[$name]);
                }
            } elseif (isset($value)) {
                if (! is_array($this->table[$name])) {
                    $this->table[$name] = array($this->table[$name]);
                }
                array_push($this->table[$name], $value);
            }
        } else {
            $this->table[$name] = $value;
        }
    }

    /**
     * Sets the named header, shortcut for add($name, $value, true)
     *
     * @param name string
     * @param value string
     * @return void
     */
    public function set($name, $value)
    {
        $this->add($name, $value, true);
    }

    /**
     * Clears the named header
     *
     * @param name string
     * @return void
     */
    public function clear($name)
    {
        if (array_key_exists($name, $this->table)) {
            unset($this->table[$name]);
        }
    }

    /**
     * Sends the header table
     *
     * @return void
     */
    public function send()
    {
        if (isset($this->statusCode) && array_key_exists('Status', $this->table)) {
            $status = $this->table['Status'];
            unset($this->table['Status']);
            header($_SERVER['SERVER_PROTOCOL'].' '.$status);
            header("Status: $status", true);
        }

        foreach ($this->table as $name => $val) {
            if (is_array($val)) {
                for ($i=0; $i<count($val); $i++) {
                    header("$name: $val[$i]", $i==0);
                }
            } else {
                header("$name: $val");
            }
        }
    }
}

?>
