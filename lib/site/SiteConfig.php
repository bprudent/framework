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
 * Parses and caches ini file settings, the file format is:
 *   [groupa.groupb.groupc]
 *   foo=simple string value
 *   bar=json:["a","complex","value"]
 *
 * The Site instantiates a SiteConfig object, then dispatches the "onConfig"
 * event to allow other areas to add any config files needed, then calles
 * SiteConfig::compile() and dispatches "onPostConfig".
 */
class SiteConfig
{
    protected $site;
    protected $data;

    // Used by SiteConfigParseException
    public $curFile;
    public $curLine;

    protected $files;
    protected $mtime;
    protected $loadedMtime;

    /**
     * Constructs a new SiteConfig, the config starts off with:
     *
     * @param Site $site
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->files = array();
        $this->mtime = 0;
    }

    /**
     * @param string $path
     * @param boolean $create default true, if false null will be returned
     * instead of creating non-existant groups
     * @return SiteConfigGroup
     */
    public function getGroup($path, $create=true)
    {
        if (! isset($this->data)) {
            throw new RuntimeException('configuration not compiled yet');
        }

        if (is_string($path)) {
            $path = explode('.', $path);
        }
        assert(is_array($path));

        $group = $this->data;
        foreach ($path as $comp) {
            if (! $group->has($comp)) {
                if ($create) {
                    return $group->addGroup($comp);
                } else {
                    return null;
                }
            }
            $group = $group->get($comp);
        }
        return $group;
    }

    /**
     * @param string $path
     * @param mixed $default
     * @return mixed
     */
    public function get($path, $default=null)
    {
        if (! isset($this->data)) {
            throw new RuntimeException('configuration not compiled yet');
        }

        if (is_string($path)) {
            $path = explode('.', $path);
        }
        assert(is_array($path));

        $name = array_pop($path);
        $group = $this->getGroup($path);
        return $group->get($name, $default);
    }

    /**
     * Like get, except there is no default, a RuntimeException will be thrown
     * if the value isn't set
     * @param string $path
     * @return mixed
     */
    public function getRequired($path)
    {
        $val = $this->get($path);
        if (! isset($val)) {
            throw new RuntimeException("unset configuration value $path");
        }
        return $val;
    }

    /**
     * Adds a configuration file to the site configuration
     *
     * If the configuration has already been complide, compile is called to
     * integrate the newly added file.
     *
     * @param string $file the file path
     * @return void
     * @see compile
     */
    public function addFile($file)
    {
        $file = realpath($file);
        if ($file === false) {
            return;
        }
        if (in_array($file, $this->files)) {
            return;
        }

        $this->mtime = max($this->mtime, filemtime($file));
        array_push($this->files, $file);

        if (isset($this->data)) {
            $this->complie();
        }
    }

    /**
     * Complise the configuration
     *
     * If any of the added files are newer than the cached version, then each
     * file is loaded and its contents merged; the result is then cached so we
     * don't have to do that until a file is changed again.
     *
     * @return void
     * @see load
     */
    public function compile($loadcache=true)
    {
        if (isset($this->data) && $this->loadedMtime >= $this->mtime) {
            return;
        }

        // Try to load cached configuration
        $cache = $this->site->layout->getCacheArea('config').'/siteconfig';
        if ($loadcache) {
            if (file_exists($cache)) {
                $cachemtime = filemtime($cache);
            } else {
                $cachemtime = 0;
            }
            if (file_exists($cache) && $cachemtime >= $this->mtime) {
                $data = @file_get_contents($cache);
                if (isset($data) && $data !== false) {
                    $data = unserialize($data);
                    if (isset($data) && $data !== false) {
                        if (
                            ! $this->data instanceof SiteConfigGroup ||
                            $data->get('__files', array()) != $this->files
                        ) {
                            $this->compile(false);
                        } else {
                            $this->data = $data;
                            $this->loadedMtime = $cachemtime;
                        }
                        return;
                    }
                }
            }
        }

        // Merge config files
        $this->loadedMtime = time();
        if (count($this->files)) {
            foreach ($this->files as $file) {
                $data = $this->load($file);
                if (isset($this->data)) {
                    $this->data->merge($data);
                } else {
                    $this->data = $data;
                }
            }
        } else {
            $this->data = new SiteConfigGroup();
        }

        $this->data->set('__files', $this->files);

        // Cache merged configuration
        file_put_contents($cache, serialize($this->data));
    }

    /**
     * Loads a configuration file
     *
     * If loadCache returns null, the files contents are loaded, parsed, and
     * then cached.
     *
     * @param string $file
     * @return SiteConfigGroup
     * @see loadCache, parseString, cache
     */
    protected function load($file)
    {
        $data = $this->loadCache($file);
        if (isset($data)) {
            return $data;
        }

        $s = @file_get_contents($file);
        if ($s === false) {
            throw new RuntimeException("failed to load $file");
        }
        $this->curFile = $file;
        try {
            $data = $this->parseString($s);
        } catch (Exception $e) {
            $this->curFile = null;
            throw $e;
        }
        $this->curFile = null;

        return $this->cache($file, $data);
    }

    /**
     * Loads a cached version of a configuration file, or returns null if the
     * cached data is out of date.
     *
     * @param string $file
     * @return SiteConfigGroup or null
     * @see cacheName
     */
    protected function loadCache($file)
    {
        $cache = $this->cacheName($file);
        if (! file_exists($cache)) {
            return null;
        }
        if (filemtime($cache) < filemtime($file)) {
            return null;
        }

        $data = @file_get_contents($cache);
        if ($data === false) {
            return null;
        }

        $data = unserialize($data);
        if ($data === false) {
            return null;
        }

        if ($data instanceof SiteConfigGroup) {
            return $data;
        } else {
            return null;
        }
    }

    /**
     * Saves a cached version of a configuration file
     *
     * @param string $file
     * @param SiteConfigGroup $data
     * @return SiteConfigGroup
     * @see cacheName
     */
    protected function cache($file, $data)
    {
        $cache = $this->cacheName($file);

        if (! file_put_contents($cache, serialize($data))) {
            throw new RuntimeException("Failed to cache $file");
        }
        return $data;
    }

    /**
     * Parses the contents of a configuration file
     *
     * Configuration files should be formated like:
     *   ; a comment
     *   # also a comment
     *   [some.group.path]
     *   someval=simple string
     *   anotherval=json:["complexity","is","okay","too"]
     *   anarray[]=more notural way
     *   anarray[]=to define
     *   anarray[]=a list
     *   ; will set a literal null value
     *   something=
     *   something=null
     *   ; will set a literal false value, also for true
     *   something=false
     *   ; values are trim()ed by default, so if for some reason you need
     *   ; trailing whitespace, a literal quoted "false", "true", or "null",
     *   ; or just for whatever reason, you can quote values:
     *   something="   space   "
     *   something="Can have \' \" escaped sequences ala stripslashes()"
     *
     * @param string $s
     * @return SiteConfigGroup
     */
    protected function parseString($s)
    {
        $data = new SiteConfigGroup();

        $lines = explode("\n", $s);
        $s = null;
        $group = null;
        $this->curLine = 0;
        try {
            foreach ($lines as $line) {
                $this->curLine++;
                $line = trim($line);
                if ($line == '' || $line[0] == ';' || $line[0] == '#') {
                    continue;
                }
                $matches = array();
                if (preg_match('/^\[([\w_\-\.]+)\]$/', $line, $matches)) {
                    $name = $matches[1];
                    $group = $data;
                    if ($name != 'global') {
                        $path = explode('.', $name);
                        $down = array();
                        foreach ($path as $comp) {
                            if (! $group->has($comp)) {
                                $group = $group->addGroup($comp);
                            } else {
                                $group = $group->get($comp);
                                if (! $group instanceof SiteConfigGroup) {
                                    throw new SiteConfigParseException($this,
                                        implode('.', $down)." exists as a value"
                                    );
                                }
                            }
                            array_push($down, $comp);
                        }
                    }
                } elseif (preg_match('/^([^=]+)=(.*)$/', $line, $matches)) {
                    $name = $matches[1];
                    $val = trim($matches[2]);
                    $vallen = strlen($val);
                    if ($vallen == 0) {
                        $val = null;
                    } elseif ($vallen > 5 && substr($val, 0, 5) == 'json:') {
                        $val = json_decode($val, true);
                        if (! isset($val)) {
                            throw new SiteConfigParseException(
                                $this, 'invalid json string'
                            );
                        }
                    } elseif ($vallen > 2 && $val[0] == '"' && $val[$vallen-1] == '"') {
                        $val = stripslashes(substr($val, 1, $vallen-1));
                    } else {
                        switch ($val) {
                        case 'true':
                            $val = true;
                            break;
                        case 'false':
                            $val = false;
                            break;
                        case 'null':
                            $val = null;
                            break;
                        }
                    }
                    $namelen = strlen($name);
                    if (
                        $namelen > 3 &&
                        substr($name, $namelen-2) == '[]'
                    ) {
                        $name = substr($name, 0, $namelen-2);
                        $val = (array) $val;
                        if ($group->has($name, $val)) {
                            $val = array_merge(
                                (array) $group->get($name), $val
                            );
                        }
                    }
                    $group->set($name, $val);
                } else {
                    throw new SiteConfigParseException(
                        $this, 'malformed line'
                    );
                }
            }
        } catch (Exception $e) {
            $this->curLine = null;
            throw $e;
        }
        $this->curLine = null;

        return $data;
    }

    /**
     * Returns the name under which a configuration file should be cached
     *
     * This is a string like:
     *   SiteLayout->getCacheArea('config')/<sha1 of file path>
     *
     * @param string $file
     * @return string
     */
    protected function cacheName($file)
    {
        return
            $this->site->layout->getCacheArea('config').'/'.
            sha1($file);
    }
}

/**
 * A group of configuration
 */
class SiteConfigGroup
{
    protected $path;
    protected $data;

    public function __construct($path='global')
    {
        $this->path = $path;
        $this->data = array();
    }

    public function getPath()
    {
        return $this->path;
    }

    /**
     * Returns an associative array representation of this group, this is a bit
     * more than just returning $data since it also recurses and converts any
     * subgroups as well.
     *
     * @retrun array
     */
    public function toArray()
    {
        $a = array();
        foreach ($this->data as $key => $value) {
            if ($value instanceof self) {
                $a[$key] = $value->toArray();
            } else {
                $a[$key] = $value;
            }
        }
        return $a;
    }

    /**
     * Merges this group with another, the other's values take precedence in
     * case of conflict, arrays that are shared are array_merge()ed
     *
     * @param SiteConfigGroup $other
     * @return void
     */
    public function merge(SiteConfigGroup $other)
    {
        foreach ($other->data as $key => $val) {
            if (array_key_exists($key, $this->data)) {
                if ($val instanceof self) {
                    if (! $this->data[$key] instanceof self) {
                        throw new RuntimeException(
                            "cannot merge group $key with non group value"
                        );
                    }
                    $this->data[$key]->merge($val);
                } elseif (is_array($val)) {
                    if (! is_array($this->data[$key])) {
                        throw new RuntimeException(
                            "cannot merge array $key with non array value"
                        );
                    }
                    $this->data[$key] = array_merge($this->data[$key], $val);
                } else {
                    $this->data[$key] = $val;
                }
            } else {
                $this->data[$key] = $val;
            }
        }
    }

    /**
     * Adds a sub group to this group
     *
     * @param string $key
     * @return SiteConfigGroup
     */
    public function addGroup($key)
    {
        if ($this->has($key)) {
            throw new RuntimeException("$key already exists");
        }
        return $this->data[$key] = new self(
            $this->path == 'global' ? $key : "$this->path.$key"
        );
    }

    /**
     * Returns all keys set in this group
     *
     * @return array
     */
    public function keys()
    {
        return array_keys($this->data);
    }

    /**
     * Checks whether the named item exists in this group
     *
     * @param string $key
     * @return boolean
     */
    public function has($key)
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Gets a value from this group
     *
     * @param string $key
     * @param mixed $default=null the default value to return if name is unset
     * @return mixed
     */
    public function get($key, $default=null)
    {
        if (
            array_key_exists($key, $this->data) &&
            isset($this->data[$key])
        ) {
            return $this->data[$key];
        } else {
            return $this->data[$key] = $default;
        }
    }

    /**
     * Like get, except there is no default, a RuntimeException will be thrown
     * if the value isn't set
     * @param string $path
     * @return mixed
     */
    public function getRequired($path)
    {
        $val = $this->get($path);
        if (! isset($val)) {
            throw new RuntimeException("unset configuration value $path");
        }
        return $val;
    }

    /**
     * Sets a value in the group and returns it
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function set($key, $value)
    {
        return $this->data[$key] = $value;
    }
}

/**
 * A configuration parsing exception, thrown by SiteConfig::parseString, used
 * to indicate the file and line number where the parse error occured
 */
class SiteConfigParseException extends RuntimeException
{
    public function __construct(SiteConfig $config, $mess)
    {
        if (isset($config->curFile)) {
            $mess .= " in $config->curFile";
        }
        if (isset($config->curLine)) {
            $mess .= " on line $config->curLine";
        }
        parent::__construct($mess);
    }
}

?>
