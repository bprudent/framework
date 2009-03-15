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

require_once 'lib/util/CallbackManager.php';

/**
 * A site module is a self contained sub-system, such as a CMS, DMS, login
 * system, etc
 *
 * Incidentally, this has absolutely nothing to do with the Module class
 */
abstract class SiteModule extends CallbackManager
{
    /**
     * Priavte utility, go away
     */
    static private function collectStaticArray(ReflectionClass $class, $name, $a=array()) {
        $c = $class;
        while ($c) {
            if ($c->hasProperty($name)) {
                $prop = $c->getProperty($name);
                if (! $prop->isStatic()) {
                    throw new RuntimeException(
                        "$c->name::\$$name isn't static"
                    );
                }
                $v = $prop->getValue();
                if (! is_array($v)) {
                    throw new RuntimeException(
                        "$c->name::\$$name isn't an array"
                    );
                }
                $a = array_unique(array_merge($a, $v));
            }
            $c = $c->getParentClass();
        }
        return $a;
    }

    protected $site;

    private $required = array();
    private $optional = array();
    private $hasOptional = array();

    protected $dir;
    protected $prefix;

    /**
     * @return Site
     */
    public function getSite()
    {
        return $this->site;
    }

    /**
     * Returns the list of required module names
     *
     * @return array
     */
    public function getRequired()
    {
        return $this->required;
    }

    /**
     * Returns the list of optional module names
     *
     * @return array
     */
    public function getOptional()
    {
        return $this->optional;
    }

    /**
     * Method for subclasses to test if one of their declared optional modules
     * is present
     *
     * @param module string a module listed in $OptionalModules
     * @return bool
     */
    public function hasModule($module)
    {
        assert(array_key_exists($module, $this->hasOptional));
        return $this->hasOptional[$module];
    }

    public function getDir()
    {
        return $this->dir;
    }

    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Creates a new SiteModule
     *
     * Collects all static $RequiredModules and $OptionalModules into the
     * $required and $optional properties.
     *
     * Verifys that all $required modules are loaded.
     *
     * @param site Site
     */
    public function __construct(Site $site)
    {
        $this->site = $site;

        $class = new ReflectionClass(get_class($this));
        $this->dir = dirname($class->getFileName());
        $fp = Loader::$FrameworkPath.'/';
        $fl = strlen($fp);
        if (substr($this->dir, 0, $fl) == $fp) {
            $this->prefix = substr($this->dir, $fl);
        } else {
            $lp = Loader::$LocalPath.'/';
            $ll = strlen($lp);
            if (substr($this->dir, 0, $ll) == $lp) {
                $this->prefix = substr($this->dir, $ll);
            } else {
                throw new RuntimeException(
                    'unable to determine prefix for the '.get_class($this).
                    ' module in '.$this->dir
                );
            }
        }

        // Build the list of required/optional modules
        $this->required = self::collectStaticArray(
            $class, 'RequiredModules', $this->required
        );
        $this->optional = self::collectStaticArray(
            $class, 'OptionalModules', $this->optional
        );

        $r = array();
        foreach ($this->required as $c) {
            SiteModuleLoader::loadModule($c);
            $gotit = false;
            for ($i=0; $i<count($r); $i++) {
                $in =& $r[$i];
                if (is_subclass_of($in, $c)) {       // superceded
                    $gotit = true;
                    break;
                } elseif (is_subclass_of($c, $in)) { // supercedes
                    $in = $c;
                    $gotit = true;
                    break;
                }
            }
            if (! $gotit) {
                array_push($r, $c);
            }
        }
        $this->required = $r;

        foreach ($this->required as $req) {
            if (! $this->site->modules->has($req)) {
                $reqclass = new ReflectionClass($req);
                if ($reqclass->isAbstract()) {
                    throw new RuntimeException(
                        "Required module $req for module $class->name not ".
                        "loaded, can't provied a default"
                    );
                }
                $this->site->modules->add($req);
            }
        }
    }

    /**
     * Called by SiteModuleLoader after every module has been instantiated and
     * the list of active modules stabalized.
     *
     * Sets $hasOptional keys for each $optional module.
     * @return void
     */
    public function initialize()
    {
        foreach ($this->optional as $opt) {
            $this->hasOptional[$opt] = $this->site->modules->has($opt);
        }
    }
}

?>
