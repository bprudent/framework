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

require_once dirname(__FILE__).'/HTMLPageAsset.php';
require_once dirname(__FILE__).'/HTMLPageMeta.php';

/**
 * HTMLPages are Pages with codified detail needed to coherently build an
 * HTML page such as:
 *   whether to include an <?xml ... ?> header
 *   doctype
 *   page title
 *   <meta /> data
 *   assets such as scripts, stylesheets, and links
 *
 * @package Ui
 */
class HTMLPage extends Page
{
    /**
     * Whether to output a <?xml ... ?> header
     */

    public $xmlHeader = true;
    /**
     * The <!DOCTYPE ... > of the current page
     *
     * This should be a string known by CoreTag::doctype
     *
     * @var string
     * @see CoreTag::doctype
     */
    public $doctype = 'xhtml 1.1';

    /**
     * The title for the page
     *
     * @var string
     */
    public $title;

    /**
     * Page meta data
     *
     * @var HTMLPageMeta
     */
    public $meta;

    /**
     * List of HTMLPageAssets
     *
     * @see addAsset, getAssets
     */
    private $assets;

    /**
     * Constructor
     *
     * Creates a new HTMLPage.
     *
     * While this is publically accessible for flexibility, this should be
     * sparingly used; you likely meant to call the static method Current.
     *
     * @param site Site
     *
     * @param layout string the layout name, if non-null, setLayout is called
     * with this argument. Whether a layout was specified or not,
     * Site->setHTMLPageLayout will be called after possibly calling setLayout.
     *
     * @param content mixed convenience argument, will be added to the
     * 'content' buffer if a string, the string is treated as a template
     * resource and passed through TemplateSystem->load
     *
     * @param data mixed null or an array of data items to initially set
     *
     * The layout template should start like:
     *   <template
     *     xmlns:core="class://CoreTag"
     *     core:extends="${this.pageTemplate}">
     *
     * Details:
     *   The value of $layout, whatever it ends up being, is set as the
     *   $template property and so is processed by Page::render. If the page
     *   layout cares to be a well behaved html page in the normal sense, it
     *   needs to extend the normal html template.
     *
     *   When the HTMLPage is rendered, it sets the template argument
     *   'pageTemplate' to the value of the $template property usually provided
     *   for a text/html page, see Page for whatever that means,
     *
     * @see Current, Page::$template, Page::render, setLayout,
     * Site::setHTMLPageLayout, CoreTag::_extends
     */
    public function __construct(Site $site, $layout=null, $content=null, $data=null)
    {
        parent::__construct($site, 'text/html');
        $this->headers->setContentTypeCharset('utf-8');
        $this->assets = array();
        $this->meta = new HTMLPageMeta();
        $this->setData('pageTemplate', $this->template);
        if (isset($data)) {
            assert(is_array($data));
            $this->setDataArray($data);
        }
        if (isset($layout)) {
            if (preg_match('/(^\/|^\w:\/|\/(~\w*|\.\.)|(~\w*|\.\.)\/)/', $layout)) {
                throw new InvalidArgumentException(
                    "Invalid path components in '$layout'"
                );
            }
            $this->setLayout($layout);
        }
        $this->site->modules->get('PageSystem')->layoutHTMLPage($this);
        if (isset($content)) {
            if (is_string($content)) {
                $tsys = $this->site->modules->get('TemplateSystem');
                $content = $tsys->load($content);
            }
            $this->addToBuffer('content', $content);
        }
    }

    /**
     * Sets the page layout
     *
     * The value will be set to the 'pageLayout' data item, the $template
     * property will be set to "PageSystemPrefix/layouts/$layout".
     *
     * @param layout string
     * @return void
     */
    public function setLayout($layout)
    {
        $this->setData('pageLayout', $layout);
        $this->template = implode('/', array(
            $this->site->modules->getModulePrefix('PageSystem'),
            'layouts', $layout
        ));
    }

    /**
     * Tests whether the page has a layout set
     *
     * @return boolean
     */
    public function hasLayout()
    {
        return $this->hasData('pageLayout');
    }

    /**
     * Returns the layout string
     *
     * @return string
     */
    public function getLayout()
    {
        return $this->getData('pageLayout');
    }

    /**
     * Returns a list of assets in this page filtered by class
     *
     * You likely wanted to call getAssets
     *
     * @param class string
     * @return array
     */
    public function getAssetsByClass($class)
    {
        $r = array();
        foreach ($this->assets as $asset) {
            if (is_a($asset, $class)) {
                array_push($r, $asset);
            }
        }
        return $r;
    }

    /**
     * Returns a list of HTMLPageAsset objects, optionally filtered by asset type
     *
     * @param type string optional, one of:
     *   script     - returns script assets
     *   stylesheet - returns stylesheet assets
     *   link       - returns link assets
     *   linkonly   - returns link assets that aren't stylesheets
     *   alternate  - returns alternate link assets
     * @return array
     */
    public function getAssets($type=null)
    {
        switch ($type) {
        case 'script':
            return $this->getAssetsByClass('HTMLPageScript');
        case 'stylesheet':
            return $this->getAssetsByClass('HTMLPageStylesheet');
        case 'link':
            return $this->getAssetsByClass('HTMLPageLinkedResource');
        case 'alternate':
            return $this->getAssetsByClass('HTMLPageAlternateLink');
        case 'linkonly':
            return array_diff(
                $this->getAssetsByClass('HTMLPageLinkedResource'),
                $this->getAssetsByClass('HTMLPageStylesheet')
            );
        default:
            return $this->assets;
        }
    }

    /**
     * Adds an asset to the page if not already added
     *
     * @param asset HTMLPageAsset
     * @return void
     */
    public function addAsset(HTMLPageAsset $asset)
    {
        if (! $this->hasAsset($asset)) {
            array_push($this->assets, $asset);
        }
    }

    /**
     * Tests whether an asset is in this page
     *
     * @param asset HTMLPageAsset
     * @return boolean
     */
    public function hasAsset(HTMLPageAsset $asset)
    {
        foreach ($this->assets as $a) {
            if ($a->compare($asset)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Formats the page title
     *
     * @return string
     */
    public function formatTitle()
    {
        if (
            property_exists($this->site, 'title') &&
            isset($this->site->title)
        ) {
            $siteTitle = $this->site->title;
        } elseif (
            property_exists($this->site->config, 'title') &&
            isset($this->site->config->title)
        ) {
            $siteTitle = $this->site->config->title;
        } else {
            return $this->title;
        }
        if ($this->title) {
            return "$siteTitle - $this->title";
        } else {
            return "$siteTitle";
        }
    }

    protected static $exceptionDisplayTemplate = null;
    protected function handleBufferedItemException($buffer, $item, Exception $e)
    {
        if (! isset(self::$exceptionDisplayTemplate)) {
            $tsys = $this->site->modules->get('TemplateSystem');
            self::$exceptionDisplayTemplate = $tsys->load('exceptionDisplay');
        }
        return self::$exceptionDisplayTemplate->render(array(
            'exception' => $e,
            'message' => "Error while processing buffer '$buffer' item"
        ));
    }
}

?>
