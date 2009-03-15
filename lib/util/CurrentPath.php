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

require_once 'lib/util/StupidPath.php';

/**
 * Represents the current path
 *
 * Instances are string equivalent so that conusmers of $current->path never
 * need not be any wiser.
 *
 * Example:
 *   $path = CurrentPath::set('bla/foo')
 * same as:
 *   $path = CurrentPath::set(Loader::$Base.'/bla/foo')
 *
 * echo $path; // prints /path/to/site/bla/foo
 * echo $path->up(); // prints /path/to/site/bla
 *
 * echo $path->url; // prints /bla/foo
 * echo $path->up(); // prints /bla
 */
class CurrentPath
{
    /**
     * The file path
     *
     * @var StupidPath
     */
    public $path=null;

    /**
     * The url corresponding to path
     *
     * @var StupidPath
     */
    public $url=null;

    private static $current=null;

    public static function get()
    {
        return self::$current;
    }

    /**
     * Sets the current->path. A relative or absolute path
     * may be used.
     *
     * @static
     * @access public
     * @param string $path the value current->path should be set to
     * @return string the old value of current->path
     */
    public static function set($path)
    {
        if (isset($path)) {
            if (is_string($path) || $path instanceof StupidPath) {
                $path = new self($path);
            } elseif (! $path instanceof CurrentPath) {
                throw new InvalidArgumentException('Invalid path');
            }
        }

        $oldPath = self::$current;
        self::$current = $path;
        return $oldPath;
    }

    /**
     * Constructs a new curent url
     *
     * @param path string
     */
    public function __construct($path, $url=null)
    {
        if ($path instanceof StupidPath) {
            $this->path = $path;
        } else {
            $path = realpath($path);

            // Strip trailing slash
            if ($path[strlen($path)-1] == '/') {
                $path = substr($path, 0, strlen($path)-1);
            }

            $this->path = new StupidPath(explode('/', $path));
        }

        if ($url instanceof StupidPath) {
            $this->url = $url;
        } else {
            if (! isset($url)) {
                $url = (string) $this->path;
            }

            $bl = strlen(Loader::$Base);
            if (substr($url, 0, $bl) == Loader::$Base) {
                $url = substr($url, $bl);
                $url = explode('/', $url);

                // TODO Intropsect Loader variables and the site's url base, and
                // determine the framework path thusly
                if (count($url) >= 1 && $url[0] == 'framework') {
                    $url = array_slice($url, 1);
                } elseif (count($url) >= 2 && $url[0] == '' && $url[1] == 'framework') {
                    $url = array_slice($url, 2);
                }

                if ($url[0] == '') {
                    array_shift($url);
                }
                $this->url = new StupidPath(array_merge(
                    explode('/', Site::Site()->url),
                    $url
                ));
            }
        }
    }

    /**
     * Returns string representation of $path property
     */
    public function __tostring()
    {
        return (string) $this->path;
    }

    /**
     * Rolls both path and url up by n and returns a new self
     *
     * @see StupidPath::up
     */
    public function up($n=1)
    {
        return new self($this->path->up($n), $this->url->up($n));
    }

    /**
     * Descends into bothp and url and returns a new self
     *
     * @see StupidPath::down
     */
    public function down()
    {
        $args = func_get_args();
        return new self($this->path->down($args), $this->url->down($args));
    }
}

?>
