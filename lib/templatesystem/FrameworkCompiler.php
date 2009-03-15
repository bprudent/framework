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
 * FrameworkCompiler
 *
 * This class overrides the default php-stl Compiler to provide more functionality
 */
class FrameworkCompiler extends PHPSTLCompiler
{
    protected static $proxy;
    public static function getParamsProxy()
    {
        if (! isset(self::$proxy)) {
            self::$proxy = new ParamsProxyStub();
        }
        return self::$proxy;
    }

    // Framework Template preamble
    protected function writeTemplateHeader()
    {
        parent::writeTemplateHeader(array(
            'Framework Version' => Loader::$FrameworkVersion
        ));
        $this->write('<?php $site = Site::Site(); ?>');
        $tsys = Site::getModule('TemplateSystem');
        if ($tsys->hasModule('PageSystem')) {
            $this->write('<?php '.
                "if (isset(\$this->page)) {\n".
                "  \$page = \$this->page;\n".
                "} else {\n".
                "  \$page = \$site->modules->get('PageSystem')->getCurrentPage();\n".
                "}\n".
            ' ?>');
        }
        $this->write("<?php \$params = ".__CLASS__."::getParamsProxy(); ?>");
    }
}

/**
 * Used to transform expressions like:
 *   ${params.post.foo}
 *   ${params.post('foo', 'default')}
 * Into calls like:
 *   Params::post('foo')
 *   Params::post('foo', 'default')
 *
 * An instance of this class is created at the top of every template and set
 * to $params
 */
class ParamsProxyStub
{
    public function __get($type)
    {
        if (! property_exists($this, $type)) {
            $this->type = new ParamsProxy($type);
        }
        return $this->type;
    }

    public function __call($name, $args)
    {
        $this->$name->call($args);
    }
}

class ParamsProxy
{
    private $call;

    public function __construct($type)
    {
        $this->call = array('Params', $type);
        if (! is_callable($this->call)) {
            throw new BadMethodCallException(
                "No such method Params::$type"
            );
        }
    }

    public function call($args)
    {
        return call_user_func_array($this->call, $args);
    }

    public function __get($name)
    {
        return call_user_func($this->call, $name);
    }
}

?>
