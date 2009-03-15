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
 * Provides template interface to Page
 */
class TemplatePageHandler extends PHPSTLNSHandler
{
    /**
     * Makes things like:
     *   <core:if page:test="isBufferEmpty:name" />
     * Work as you would exect
     */
    public function handleAttrTest(DOMAttr $attr)
    {
        $val = $attr->value;
        if (($i = strpos($val, ':')) !== false) {
            $type = trim(substr($val, 0, $i));
            $val = trim(substr($val, $i+1));
        } else {
            throw new PHPSTLCompilerException($this->compiler, 'missing value');
        }
        switch ($type) {
        case 'hasBuffer':
            return '${page.hasBuffer('.$this->quote($val).')}';
            break;
        case 'hasData':
            return '${page.hasData('.$this->quote($val).')}';
            break;
        default:
            throw new PHPSTLCompilerException($this->compiler,
                "invalid test type $type"
            );
            break;
        }
    }

    /**
     * Outputs the contents of a named page buffer
     *
     * Attributes:
     *   name  string  required the name of the page buffer
     *   clear boolean optional default true, clear the buffer afterwords
     *
     * @see Page::getBuffer, Page::clearBuffer
     *
     * @param DOMElement element the tag such as <page:buffer />
     * @return void
     */
    public function handleElementBuffer(DOMElement $element)
    {
        $area = $this->requiredAttr($element, 'area');
        $clear = $this->getBooleanAttr($element, 'clear', true);
        $clear = $clear ? 'true' : 'false';
        $this->compiler->write(
            "<?php print \$page->getBuffer($area, false, $clear); ?>"
        );
    }

    /**
     * Adds content to the named page buffer, example:
     *   <page:addToBuffer area="content">
     *     Here be content!
     *   </page:addToBuffer>
     *
     * Or if you have content in a variable:
     *   <page:addToBuffer area="content" var="${some.content.var}" />
     *
     * @param DOMElement element the tag such as <page:buffer />
     * @return void
     * @see Page::addToBuffer
     */
    public function handleElementAddTobuffer(DOMElement $element)
    {
        $area = $this->requiredAttr($element, 'area');
        if ($element->hasAttribute('var')) {
            $var = $this->getAttr($element, 'var');
            $this->compiler->write(
                "<?php \$page->addToBuffer($area, $var); ?>"
            );
        } else {
            $this->compiler->write("<?php\n".
                "ob_start();\n".
                "try {\n".
            "?>");
            $this->process($element);
            $this->compiler->write("<?php\n".
                "  \$page->addToBuffer($area, @ob_get_clean());\n".
                "} catch (Exception \$e) {\n".
                "  ob_end_clean();\n".
                "  throw \$e;\n".
                "}\n".
            "?>");
        }
    }

    /**
     * Gets a page data item
     *
     * @param var string required
     * @param name string required
     * @param default mixed optional
     */
    public function handleElementData(DOMElement $element)
    {
        $var = $this->requiredAttr($element, 'var', false);
        $name = $this->requiredAttr($element, 'name');
        $default = $this->getAttr($element, 'default');
        $this->compiler->write("<?php \$$var = \$page->getData($name); ?>");
        if (isset($default)) {
            $this->compiler->write(
                "<?php if (!isset(\$$var)) \$$var = $default; ?>"
            );
        }
    }

    protected function handleElement(DOMElement $element)
    {
        switch ($element->localName) {
        case 'notices':
        case 'warnings':
            $this->getMessList($element, $element->localName);
            break;
        default:
            parent::handleElement($element);
            break;
        }
    }

    private function getMessList(DOMElement $element, $type)
    {
        $tsys = Site::getModule('TemplateSystem');
        if (! $tsys->hasModule('PageSystem')) {
            throw new RuntimeException(
                "cannot get $type, PageSystem not available"
            );
        }

        $get = 'get'.ucfirst($type);
        $clear = 'clear'.ucfirst($type);
        $contClass = $this->getUnquotedAttr($element, 'class', "$type-container");
        $this->compiler->write("<?php\n".
            "if (count(\$page->$get())) { \n".
            "  ?><ul class=\"$contClass\"><?php\n".
            "  foreach (\$page->$get() as \$w) {\n".
            "    ?><li><?php echo \$w ?></li><?php\n".
            "  } \n?></ul><?php\n".
            "  \$page->$clear();\n".
            "}\n".
        "?>");
    }

    /**
     * Adds assets to the page
     *
     * Expects child elements like:
     *   <script href="some.js" />
     *   <stylesheet href="some.css" />
     *   <link href="some/resource" rel="something" type="some/mime" />
     *   <alternate href="some.rss" type="application/rss+xml" title="RSS Feed" />
     *
     * If any href is a simple string (i.e. no ${...} expression), and it is
     * relative, it will be passed to CurrentPath->url->down to form an
     * absolute url.
     *
     * Full gory attribute details:
     *   script: a HTMLPageScript asset
     *     href string required
     *     type string optional default 'text/javascript'
     *
     *   stylesheet: a HTMLPageStylesheet asset
     *     href      string  required
     *     alternate boolean optional default false
     *     title     string  optional default null
     *     media     string  optional default null
     *
     *   alternate: a HTMLPageAlternateLink asset
     *     href  string required
     *     type  string required
     *     title string optional default null
     *
     *   link: a HTMLPageLinkedResource asset
     *     href  string required
     *     rel   string required
     *     type  string required
     *     title string optional default null
     *
     * @param DOMElement element the tag such as <ui:pageBuffer />
     * @return void
     */
    public function handleElementAddAssets(DOMElement $element)
    {
        if ($element->hasAttribute('base')) {
            $base = 'new StupidPath('.$this->getAttr($element, 'base').')';
        } else {
            $base = 'CurrentPath::get()->url';
        }
        $this->compiler->write(
            "<?php if (! \$page instanceof HTMLPage) {\n".
            "  throw new RuntimeException('Can only add html assets to an html page');\n".
            '} ?>'
        );
        $assets = array();
        $baseVar = '$__base'.uniqid();
        foreach ($element->childNodes as $n) {
            if ($n->nodeType == XML_ELEMENT_NODE) {
                $href = $this->requiredAttr($n, 'href');
                $path = CurrentPath::get();
                $href = "(string) ${baseVar}->rel2abs($href)";
                switch ($n->tagName) {
                case 'script':
                    $asset = array('HTMLPageScript', $href);
                    $type = $this->getAttr($n, 'type');
                    if (isset($type)) {
                        array_push($asset, $type);
                    }
                    break;
                case 'stylesheet':
                    $alt = $this->getBooleanAttr($n, 'alternate');
                    $title = $this->getAttr($n, 'title');
                    $media = $this->getAttr($n, 'media');

                    $asset = array('HTMLPageStylesheet', $href);
                    array_push($asset, $alt ? 'true' : 'false');
                    if (isset($title)) {
                        array_push($asset, $title);
                    } elseif (isset($media)) {
                        array_push($asset, 'null');
                    }
                    if (isset($media)) {
                        array_push($asset, $media);
                    }
                    break;
                case 'link':
                    $rel = $this->requiredAttr($n, 'rel');
                    $type = $this->requiredAttr($n, 'type');
                    $title = $this->getAttr($n, 'title');
                    $asset = array('HTMLPageLinkedResource', $href, $type, $rel);
                    if (isset($title)) {
                        array_push($asset, $title);
                    }
                    break;
                case 'alternate':
                    $type = $this->requiredAttr($n, 'type');
                    $title = $this->getAttr($n, 'title');
                    $asset = array('HTMLPageAlternateLink', $href, $type);
                    if (isset($title)) {
                        array_push($asset, $title);
                    }
                    break;
                default:
                    throw new RuntimeException(
                        'Unknown asset element '.$n->tagName
                    );
                }
                array_push($assets, sprintf('new %s(%s)',
                    array_shift($asset), implode(', ', $asset)
                ));
            }
        }
        if (count($assets)) {
            $buffer = '';
            foreach ($assets as $asset) {
                $buffer .= "\$page->addAsset($asset);\n";
            }
            $this->compiler->write("<?php\n".
                "$baseVar = $base;\n".
                $buffer.
            "?>");
        }
    }
}

?>
