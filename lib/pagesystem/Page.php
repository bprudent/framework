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

require_once 'lib/util/CallbackManager.php';
require_once dirname(__FILE__).'/PageHeaders.php';

/**
 * Describes a page in a site (amazing)
 *
 * Basic page, manages things such as page "data" which is named arbitrary
 * mixed values and "buffers", which is a primitive form of page content on the
 * way out to the client.
 *
 * @package Site
 */
class Page extends CallbackManager
{
    /**
     * Outgoing HTTP Header interface
     *
     * @var PageHeaders
     */
    public $headers;

    /**
     * The mime type of the page
     *
     * @var string
     */
    protected $type;

    /**
     * The template resource sting to use to render this page
     *
     * The default implementation defaults to "page/type" where the "type"
     * portion is the $type property with all '/'s replaced with '_'s.
     *
     * This can be null if a subclass does not wish to use a template-based
     * approach
     *
     * @var string
     * @see onRender
     */
    protected $template;

    /**
     * Named page content buffers such as 'head', 'top', 'left', etc.
     */
    private $buffers;

    /**
     * The buffer currently being rendered
     */
    private $renderingBuffer = null;

    /**
     * Arbitrary page data by group name such as 'breadCrumbs' or
     * 'topNavigation'.
     *
     * The distinction between buffers and data is that buffers are clearly
     * defined as equivilent to string data and rendered at the end of the
     * request; however data can be absolutely anything.
     */
    private $data;

    /**
     * @var Site
     */
    protected $site;

    /**
     * Constructor
     *
     * Creates a new Page.
     *
     * @param site Site
     * @param type string the type of the page, defaults to 'text/plain'
     * @param template the template resource string used to render this page
     * if set to the false value, then the $template property will not be set,
     * either way the resulting template value will then be appended to the
     * PageSystem module prefix
     * @see $template
     */
    public function __construct(Site $site, $type='text/plain', $template=null)
    {
        $this->site = $site;
        $this->buffers = array();
        $this->data = array();
        if (is_array($type) || strpos($type, ',') !== false) {
            $this->negotiateType($type);
        } else {
            $this->type = $type;
        }
        if (isset($template)) {
            if ($template === false) {
                $this->template = null;
            } else {
                $this->template = $template;
            }
        } else {
            $this->template = sprintf('page/%s',
                preg_replace('/\//', '_', $this->type)
            );
        }
        $this->template =
            $site->modules->getModulePrefix('PageSystem').
            '/'.
            $this->template;
        $this->headers = new PageHeaders();
        $this->headers->setContentType($this->type);
    }

    /**
     * Negotiates the type of the page based on the intersection of what's
     * available, and what the client accepts
     *
     * Sets the $type property to the determined type
     *
     * @param avail array of mime types that are available to be sreved
     * @return void
     */
    protected function negotiateType($avail)
    {
        if (! is_array($avail))
            $avail = preg_split('/,\s*/', $avail);

        $accept = Params::SERVER('HTTP_ACCEPT', 'text/html');

        # Fix IE brokeness
        if (stripos(Params::SERVER('HTTP_USER_AGENT', ''), 'msie') !== false) {
            # In IE 6, the script sets the Accept header to 'foo/bar', yet IE sends '*/*, foo/bar'
            $accept = preg_replace('~^\*/\*,\s*~i', '', $accept);
        }

        $matches = null;
        $types = array();
        foreach (preg_split('/,\s*/', $accept) as $type) {
            if (($i = strpos($type, ';')) !== false) {
                if (preg_match('/q=([\d\.]+)/', substr($type, $i), $matches)) {
                    $q = (float) $matches[1];
                } else {
                    $q = 1.0;
                }
                $types[substr($type, 0, $i)] = $q;
            } else {
                $types[$type] = 1.0;
            }
        }

        arsort($types);
        foreach (array_keys($types) as $acceptedType) {
            if (in_array($acceptedType, $avail)) {
                $this->type = $acceptedType;
                break;
            } else {
                $pat = array();
                foreach (explode('/', $acceptedType) as $part) {
                    if ($part == '*') {
                        array_push($pat, '.+');
                    } else {
                        array_push($pat, $part);
                    }
                }
                $pat = '/^'.implode('\\/', $pat).'$/';
                foreach ($avail as $type) {
                    if (preg_match($pat, $type)) {
                        $this->type = $type;
                        break;
                    }
                }
                if (isset($this->type)) {
                    break;
                }
            }
        }
        if (! isset($this->type)) {
            $avail = implode(', ', $avail);
            throw new RuntimeException(
                "unable to negotiate page type, available: $avail accepted: $accept"
            );
        }
    }

    public function getSite()
    {
        return $this->site;
    }

    /**
     * Gets the mime type of the page
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Tests whether the given mime type is compatible with this page
     *
     * @param t string
     * @return boolean
     */
    public function compatibleType($t)
    {
        return $t == $this->type;
    }

    /**
     * Adds an item to the named buffer
     *
     * @param name the name of the buffer, e.g. head, top, left, etc
     * @param content mixed a renderable item
     *
     * @return void
     *
     * @see renderBufferedItem
     */
    public function addToBuffer($name, &$content)
    {
        if (isset($this->renderingBuffer) && $this->renderingBuffer == $name) {
            throw new RuntimeException(
                "Cannot add to page buffer $name while rendering it"
            );
        }
        if ($content === null) {
            return;
        }
        if (! array_key_exists($name, $this->buffers)) {
            $this->buffers[$name] = array();
        }
        array_push($this->buffers[$name], $content);
    }

    /**
     * Tests whether the given buffer has any content
     *
     * @param name the name of the buffer, e.g. head, top, left, etc
     * @return boolean
     */
    public function hasBuffer($name)
    {
        assert(is_string($name));
        if (array_key_exists($name, $this->buffers)) {
            return count($this->buffers[$name]) != 0;
        } else {
            return false;
        }
    }

    /**
     * Renders and returns the named buffer
     *
     * @param name the name of the buffer, e.g. head, top, left, etc
     * @param asArray boolean if true, an array is returned containing each
     * item from the buffer rendered, otherwise the concatenation of this array
     * is returned.
     * @param flush boolean, if true call clearBuffer right before returning
     *
     * @return string or array if name exists, null otherwise
     *
     * @see renderBufferedItem
     */
    public function getBuffer($name, $asArray=false, $flush=false)
    {
        if (isset($this->renderingBuffer)) {
            throw new RuntimeException(
                'Page->getBuffer called recursively '.
                "$name inside of $this->renderingBuffer"
            );
        }

        if (! array_key_exists($name, $this->buffers)) {
            return null;
        }

        $this->renderingBuffer = $name;

        if ($asArray) {
            $ret = array();
            foreach ($this->buffers[$name] as &$item) {
                array_push($ret,
                    $this->renderBufferedItem($item)
                );
            }
        } else {
            $ret = $this->renderBufferedItem(
                $this->buffers[$name], array($name)
            );
        }

        $this->renderingBuffer = null;

        if ($flush) {
            $this->clearBuffer($name);
        }
        return $ret;
    }

    /**
     * Emptys the named buffer.
     * Doesn't return anything, call getBuffer first if you want that
     *
     * @param name string
     * @return void
     */
    public function clearBuffer($name)
    {
        if (array_key_exists($name, $this->buffers)) {
            $this->buffers[$name] = array();
        }
    }

    /**
     * Calls processBuffer on each defined buffer
     *
     * @return void
     */
    public function processBuffers()
    {
        foreach (array_keys($this->buffers) as $name) {
            $this->processBuffer($name);
        }
    }

    /**
     * Processes the named buffer
     *
     * This turns each item in the buffer into its string representation and
     * throws away the old non-string data. The buffer still remains as an array
     * of items, but each item will now be just a string.
     *
     * @param name string the buffer
     *
     * @return void
     */
    public function processBuffer($name)
    {
        if (! array_key_exists($name, $this->buffers)) {
            throw new InvalidArgumentException('Invaild buffer name');
        }
        $this->buffers[$name] = $this->getBuffer($name, true);
    }

    /**
     * Renders a buffered item
     *
     * @param mixed the item, can be any of:
     * - a string
     * - an object derived from BufferedObject, call getBuffer and concatenate
     * - an object that implements __tostring, convert to string and concatenate
     * - any other object, a string "[Objcet of type CLASS]"
     * - an array, each array element will be passed through renderBufferedItem
     *   and concatenated.
     * - a callable, call the callable, passing it args as arguments and then
     *   pass its return through renderBUfferedItem.
     *
     * @return string
     */
    private function renderBufferedItem(&$item, $args=array())
    {
        try {
            if (is_array($item)) {
                $r = '';
                foreach ($item as &$i) {
                    $r .= $this->renderBufferedItem($i, $args);
                }
                return $r;
            } elseif (is_object($item)) {
                if ($item instanceof BufferedObject) {
                    return $item->getBuffer();
                } elseif ($item instanceof PHPSTLTemplate) {
                    return $this->renderTemplate($item);
                } elseif (method_exists($item, '__tostring')) {
                    return (string) $item;
                } else {
                    return "[Object of type ".get_class($item)."]";
                }
            } elseif (is_callable($item)) {
                ob_start();
                $ret = call_user_func_array($item, $args);
                $l = ob_get_length();
                $mess = '';
                if ($l !== false) {
                    if ($l > 0) {
                        $mess = ob_get_contents();
                    }
                    ob_end_clean();
                }
                if (! is_string($ret) || is_callable($ret)) {
                    $ret = $this->renderBufferedItem($ret, $args);
                }
                return $ret.$mess;
            } else {
                return $item;
            }
        } catch (Exception $e) {
            return $this->handleBufferedItemException(
                $this->renderingBuffer, $item, $e
            );
        };
    }

    /**
     * Called by renderBufferedItem if processing an item rasises an exception.
     *
     * Subclasses should return a string to represent the exception in the page,
     * or if the page wants to be intolerant, a subclass can opt to simply throw
     * the exception.
     *
     * The default implementation renders the exception as text only.
     *
     * @param buffer string the buffer the item belongs to
     * @param item mixed the item that failed
     * @param Exception e raised by the item
     * @return string
     */
    protected function handleBufferedItemException($buffer, $item, Exception $e)
    {
        $mess = "Error while processing buffer '$buffer' item:\n";
        foreach (explode("\n", (string) $e) as $line) {
            $mess .= "  $line\n";
        }
        return $mess;
    }

    /**
     * Sets a data item
     *
     * This implements a singular data item, see addData if the item should
     * be an array.
     *
     * @param name string
     * @param value string if null, unsets the item
     * @return void
     */
    public function setData($name, $value)
    {
        if (isset($value)) {
            $this->data[$name] = $value;
        } elseif (array_key_exists($name, $this->data)) {
            unset($this->data[$name]);
        }
    }

    /**
     * Sets multiple data items on one go
     *
     * @param data array
     * @return void
     */
    public function setDataArray($data)
    {
        foreach ($data as $n => &$v) {
            $this->setData($n, $v);
        }
    }

    /**
     * Adds a data item to the page
     * Similar in spirit to addToBuffer but with less semantics
     *
     * @param name string
     * @param item mixed
     * @return void
     */
    public function addData($name, &$item)
    {
        if ($item === null) {
            return;
        }
        if (! array_key_exists($name, $this->data)) {
            $this->data[$name] = array();
        }
        array_push($this->data[$name], $item);
    }

    /**
     * Tests whether the named data item exists
     *
     * @param name string
     * @return boolean true if a call to getData($name) would return non-null
     */
    public function hasData($name)
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * Returns the named data item
     *
     * @param name string
     * @return mixed
     * @see addData, setData
     */
    public function getData($name, $default=null)
    {
        if (
            array_key_exists($name, $this->data) &&
            isset($this->data[$name])
        ) {
            return $this->data[$name];
        } else {
            return $default;
        }
    }

    /**
     * Clears the named data array
     *
     * @param name string
     * @return void
     */
    public function clearData($name)
    {
        if (array_key_exists($name, $this->data)) {
            unset($this->data[$name]);
        }
    }

    public function addWarning($warning)
    {
        $this->addData('warnings', $warning);
    }

    public function addNotice($notice)
    {
        $this->addData('notices', $notice);
    }

    public function getWarnings()
    {
        return $this->getData('warnings', array());
    }

    public function getNotices()
    {
        return $this->getData('notices', array());
    }

    public function clearWarnings()
    {
        $this->cleartData('warnings');
    }

    public function clearNotices()
    {
        $this->cleartData('notices');
    }

    /**
     * Renders this page
     *
     * All buffers are finalized using processBuffers directly before calling
     * onRender
     *
     * dispatches two callbacks: prerender and postrender
     *
     * @param Site $site
     * @see processBuffers
     * @return void
     */
    final public function render()
    {
        $this->dispatchCallback('prerender', $this);

        $this->processBuffers();

        $output = $this->onRender();
        $this->headers->send();
        print $output;

        $this->dispatchCallback('postrender', $this);
    }

    /**
     * Generates page output
     *
     * The default implementation attempts to load the $template property
     * through the TemplateSystem and render through it, or if the $template
     * property is not set, simply returns the 'content' buffer.
     *
     * @return string
     * @see $template, renderTemplate
     */
    protected function onRender()
    {
        if (isset($this->template)) {
            $tsys = $this->site->modules->get('TemplateSystem');
            return $this->renderTemplate($tsys->load($this->template));
        } else {
            return $this->getBuffer('content');
        }
    }

    /**
     * Renders a template with special argument semantics
     *
     * The template is rendered with arguments set from the $data field merged
     * with array('page' => $this)
     *
     * @return array
     */
    protected function renderTemplate(PHPSTLTemplate &$template)
    {
        $prefix = $this->site->modules->getModulePrefix('PageSystem');
        return $template->render(array_merge(
            $this->data, array(
                'pageSystemPrefix' => $prefix,
                'page' => $this
            )
        ));
    }
}

?>
