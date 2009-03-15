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
 * A HTMLPage asset such as a script, stylesheet, etc
 *
 * @package UI
 */

abstract class HTMLPageAsset
{
    abstract public function __tostring();

    abstract public function compare($other);
}

class HTMLPageScript extends HTMLPageAsset
{
    public $href;
    public $type;

    public function __construct($href, $type='text/javascript')
    {
        $this->href = $href;
        $this->type = $type;
    }

    public function compare($other)
    {
        if (! isset($other) || ! $other instanceof self) {
            return false;
        }

        return
            $other->href == $this->href &&
            $other->type == $this->type;
    }

    public function __tostring()
    {
        return
            '<script type="'.
            htmlentities($this->type).
            '" src="'.
            htmlentities($this->href).
            '"></script>';
    }
}

class HTMLPageLinkedResource extends HTMLPageAsset
{
    public $href;
    public $type;
    public $rel;
    public $title;

    public function __construct($href, $type, $rel, $title=null)
    {
        $this->href = $href;
        $this->type = $type;
        $this->rel = $rel;
        $this->title = $title;
    }

    public function compare($other)
    {
        if (! isset($other) || ! $other instanceof self) {
            return false;
        }

        return
            $other->href == $this->href &&
            $other->type == $this->type &&
            $other->rel == $this->rel &&
            $other->title == $this->title;
    }

    public function __tostring()
    {
        $s = '<link rel="'.htmlentities($this->rel).'"';
        $s .= ' type="'.htmlentities($this->type).'"';
        $s .= ' href="'.htmlentities($this->href).'"';
        if (isset($this->title)) {
            $s .= ' title="'.htmlentities($this->title).'"';
        }
        $s .= ' />';
        return $s;
    }
}

class HTMLPageStylesheet extends HTMLPageLinkedResource
{
    public $media;

    public function __construct($href, $alt=false, $title=null, $media=null)
    {
        if ($alt) {
            $rel = 'alternate stylesheet';
        } else {
            $rel = 'stylesheet';
        }
        parent::__construct($href, 'text/css', $rel, $title);
        $this->media = $media;
    }

    public function __tostring()
    {
        $s = parent::__tostring();
        if (isset($this->media)) {
            $rel = "rel=\"$this->rel\"";
            $i = strpos($s, $rel) + strlen($rel);
            $s = substr($s, 0, $i)." media=\"$this->media\"". substr($s, $i);
        }
        return $s;
    }
}

class HTMLPageAlternateLink extends HTMLPageLinkedResource
{
    public function __construct($href, $type, $title=null)
    {
        parent::__construct($href, $type, 'alternate', $title);
    }
}

?>
