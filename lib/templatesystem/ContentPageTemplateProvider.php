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
 * The Original Code is RedTree Framework TemplateSystem Module
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
 * Provides templates for resources of the format "pageContent:path"
 *
 * It delegates to sub-providers so that there is a clear difference between
 * templates exposed to the world as content and templates used to provide
 * internal functions like page structure.
 *
 * Each sub-provider is asked to simply resolve a template resource of "path";
 * in other words, the original resource with the "pageContent:" prefix
 * stripped.
 *
 * If all sub-providers decline a resource, a further attempt is made for
 * path/index_file, where index_file the value of the "contentpage_index_file"
 * PHP-STL option.
 *
 * A PHPSTLDirectoryProvider is created for each existant directory listed in
 * the "contentpage_path" option and appended to any custom providers specified
 * in "contetpage_providers".
 *
 * Note: The PHPSTLTemplate objcets created by the sub-providers are returned
 * verbatim, and so their resource strings will not be directly loadable from
 * the top level; this is a known issue and will be addressed in the future.
 */
class ContentPageTemplateProvider extends PHPSTLTemplateProvider
{
    public static $Prefix = 'pageContent:';

    /**
     * The list of sub-providers
     *
     * contentpage_path
     * - should be set to a comma-separated string of paths, or an array of
     *   path strings; a PHPSTLDirectoryProvider will be created for eath path
     *   that exists
     *
     * contentpage_providers
     * - if set, should be set to an array of PHPSTLTemplateProvider objects,
     *   these will precede any directory providers in the provider list
     *
     * @var array of PHPSTLTemplateProvider
     */
    private $providers;

    /**
     * contentpage_index_file defaults to 'index.html'
     * - When loading a path, if it doesn't exit, tryis to load
     *   path/index.html before failing.
     *
     * @var string
     */
    private $indexFile;

    /**
     * contentpage_pathtransform default null
     * - If set, should be set to a callable, this callable will be called
     *   at the beginning of load to transform the path; it gets the path, it
     *   should return the new path. If the callable returns null, the
     *   provider will return PHPSTLTemplateProvider::FAIL,
     * - Additionally, the callable can retrun a two-elemenet array, where the
     *   first element is the path, and the second element is an associative
     *   array of arguments to be set on the template object once resolved
     *
     * @var callable
     */
    private $transform;

    /**
     * @see $indexFile, $transform
     */
    public function __construct(PHPSTL $pstl)
    {
        parent::__construct($pstl);
        $this->providers = $this->pstl->getOption(
            'contentpage_providers', array()
        );
        if (! is_array($this->providers)) {
            throw new InvalidArgumentException(
                'contetpage_providers should be null or an array'
            );
        }
        $path = $this->pstl->getOption('contentpage_path');
        if (isset($path)) {
            if (is_string($path)) {
                $path = explode(',', $path);
            }
            if (! is_array($path)) {
                throw new InvalidArgumentException(
                    'contentpage_path should be a string or array'
                );
            }
            foreach ($path as &$dir) {
                if (is_dir($dir)) {
                    array_push($this->providers,
                        new PHPSTLDirectoryProvider($this->pstl, $dir)
                    );
                }
            }
            unset($dir);
        }

        $this->indexFile = $this->pstl->getOption(
            'contentpage_index_file', 'index.html'
        );
        $this->transform = $this->pstl->getOption('contentpage_pathtransform');
        if (isset($this->transform) && ! is_callable($this->transform)) {
            throw new InvalidArgumentException(
                'contentpage_pathtransform isn\'t callable'
            );
        }
    }

    public function addProvider(PHPSTLTemplateProvider $provider, $prepend=false)
    {
        if ($prepend) {
            array_unshift($this->providers, $provider);
        } else {
            array_push($this->providers, $provider);
        }
        return $provider;
    }

    public function load($resource)
    {
        if (substr($resource, 0, strlen(self::$Prefix)) != self::$Prefix) {
            return PHPSTLTemplateProvider::DECLINE;
        }
        $path = substr($resource, strlen(self::$Prefix));
        if ($path === false) {
            $path = '';
        }

        if (isset($this->transform)) {
            list($path, $data) = (array) call_user_func($this->transform, $path);
            if (! isset($path)) {
                return PHPSTLTemplateProvider::FAIL;
            }
            assert(! isset($data) || is_array($data));
        }
        if (! isset($data)) {
            $data = array();
        }

        $gotit = new StopException('catch!');
        try {
            if ($path != '') {
                $r = PHPSTLTemplateProvider::provide($this->providers, $path);
                if (isset($r)) {
                    $gotit->template = $r;
                    throw $gotit;
                }
            }

            if ($path == '') {
                $path = $this->indexFile;
            } else {
                $path = "$path/$this->indexFile";
            }
            $r = PHPSTLTemplateProvider::provide($this->providers, $path);
            if (isset($r)) {
                $gotit->template = $r;
                throw $gotit;
            }
        } catch (StopException $e) {
            if ($e === $gotit) {
                $template = $e->template;
                $template->setArguments($data);
                $template->assign('pageUrl', $path);
                return $template;
            }
            throw $e;
        }

        return PHPSTLTemplateProvider::FAIL;
    }

    public function getLastModified(PHPSTLTemplate $template)
    {
    }

    public function getContent(PHPSTLTemplate $template)
    {
    }

    public function __tostring()
    {
        return '[Site Content Provider]';
    }
}

?>
