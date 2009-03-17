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

require_once 'lib/util/StupidPath.php';

/**
 * Singleton php-stl template
 */
class TemplateSystem extends SiteModule
{
    public static $TemplateClass = 'Template';
    public static $CompilerClass = 'FrameworkCompiler';

    public static $OptionalModules = array(
        'PageSystem'
    );

    private $pstl;

    public function initialize()
    {
        parent::initialize();

        require_once "$this->dir/php-stl/PHPSTL.php";
        require_once "$this->dir/FrameworkCompiler.php";
        require_once "$this->dir/Template.php";
        if ($this->hasModule('PageSystem')) {
            require_once "$this->dir/ContentPageTemplateProvider.php";
        }

        PHPSTL::registerNamespace(
            'urn:redtree:ui:form:v1.0',
            'TemplateFormHandler',
            dirname(__FILE__).'/TemplateFormHandler.php'
        );

        if ($this->hasModule('PageSystem')) {
            PHPSTL::registerNamespace(
                'urn:redtree:ui:page:v1.0',
                'TemplatePageHandler',
                dirname(__FILE__).'/TemplatePageHandler.php'
            );
        }

        $this->site->addCallback('onPostConfig', array($this, 'onPostConfig'));
    }

    public function onPostConfig()
    {
        $copt = $this->site->config->getGroup('templatesystem')->toArray();

        $inc = array();
        if (array_key_exists('include_path', $copt)) {
            $inc = Site::pathArray($copt['include_path']);
            unset($copt['include_path']);
        }

        // TODO maybe we shouldn't add local paths here at all, leave that
        // up to the config
        array_push($inc, Loader::$LocalPath);
        array_push($inc, Loader::$FrameworkPath);

        $nos = false;
        if (array_key_exists('contentpage_noshared_content', $copt)) {
            $nos = (bool) $copt['contentpage_noshared_content'];
            unset($copt['contentpage_noshared_content']);
        }

        if ($this->hasModule('PageSystem')) {
            $content = array();
            if (array_key_exists('contentpage_path', $copt)) {
                $content = Site::pathArray($copt['contentpage_path']);
                unset($copt['contentpage_path']);
            }
            array_push($content, Loader::$LocalPath.'/content');
            if (! $nos) {
                array_push($content, Loader::$FrameworkPath.'/content');
            }
            $copt['contentpage_path'] = $content;
        }

        $this->pstl = new PHPSTL(array_merge(array(
            'include_path'        => $inc,
            'template_class'      => self::$TemplateClass,
            'compiler_class'      => self::$CompilerClass,
            'diskcache_directory' => $this->site->layout->getCacheArea('template')
        ), $copt));
        if ($this->hasModule('PageSystem')) {
            $this->pstl->addProvider(new ContentPageTemplateProvider($this->pstl));
        }
    }

    public function getPHPSTL()
    {
        if (! isset($this->pstl)) {
            throw new RuntimeException('php-stl not initialized');
        }
        return $this->pstl;
    }

    public function load($resource)
    {
        return $this->getPHPSTL()->load($resource);
    }

    public function process($resource, $args=null)
    {
        return $this->getPHPSTL()->process($resource, $args);
    }
}

?>
