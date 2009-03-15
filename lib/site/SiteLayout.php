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
 * Describes the layout of the sites assets on disk
 */
class SiteLayout
{
    /**
     * The site writable area
     *
     * @default Loader::$Base/writable
     * @var string
     */
    public $writableDir;

    /**
     * Where to cache things
     *
     * @default $writableDir/cache
     * @var string
     */
    public $cacheDir;

    /**
     * Where to store the logs
     *
     * @default $writableDir/logs
     * @var string
     */
    public $logDir;

    /**
     * Holds keyed cache directories, contains things like:
     *   'template' => "$writableDir/template"
     *
     * In other words, subclasses can plug this in their constructor to change
     * the sites sense of where things should be cached; note, if they do so,
     * they need to guarantee that the directory exists and is writable, no
     * other checking will be done as for normal cache areas.
     *
     * @var array
     */
    protected $cacheAreas;

    /**
     * @var Site
     */
    protected $site;

    /**
     * Constructor
     *
     * @param site Site
     */
    public function __construct(Site &$site)
    {
        $this->site = $site;

        $this->writableDir = Loader::$Base.'/writable';
        $this->cacheDir = $this->writableDir.'/cache';
        $this->logDir = $this->writableDir.'/logs';
        $this->cacheAreas = array();
    }

    protected function _mkdir($dir, $mode=0777, $recurse=true) {
        if (! is_dir($dir) && ! @mkdir($dir, $mode, $recurse)) {
            throw new RuntimeException("Couldn't create $dir");
        }
    }

    protected function _writable($dir)
    {
        if (! is_writable($dir)) {
            throw new RuntimeException("$dir isn't writable");
        }
    }

    protected function checkWritable()
    {
        $this->_mkdir($this->writableDir);
        $this->_writable($this->writableDir);
    }

    public function getCacheArea($area=null)
    {
        if (! isset($area)) {
            $this->checkWritable();
            $this->_mkdir($this->cacheDir);
            $this->_writable($this->cacheDir);
            return $this->cacheDir;
        }
        if (! array_key_exists($area, $this->cacheAreas)) {
            $this->checkWritable();

            $dir = "$this->cacheDir/$area";
            $this->_mkdir($dir);
            $this->_writable($dir);
            $this->cacheAreas[$area] = $dir;
        }
        return $this->cacheAreas[$area];
    }

    public function setupLogDir()
    {
        $this->checkWritable();
        $this->_mkdir($this->logDir);
        $this->_writable($this->logDir);
        return $this->logDir;
    }
}

?>
