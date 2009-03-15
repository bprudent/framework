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
 *   Brandon Prudent <bprudent@redtreesystems.com>
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

require_once 'lib/util/IOutputFilter.php';

/**
 * Allows an object to be buffered through standard methods.
 * Obviously this class is worthless if clients don't behave.
 */
class BufferedObject
{
    /**
     * The filter chain
     *
     * @access protected
     * @var array
     */
    private $filters = array();

    /**
     * Provides the buffer for this object
     *
     * @access protected
     * @var string a binary buffer
     */
    protected $buffer = null;

    /**
     * Adds a filter to the output chain
     *
     * @param IOutputFilter $filter the filter to add
     * @return void
     */
    public function addFilter(IOutputFilter $filter)
    {
        array_push($this->filters, $filter);
    }

    /**
     * Removes a filter from the output chain
     *
     * @param IOutputFilter $remove the filter to remove
     * @return void
     */
    public function removeFilter(IOutputFilter &$remove)
    {
        $filters = array();
        foreach ($this->filters as &$filter) {
            if ($filter !== $remove) {
                array_push($filters, $filter);
            }
        }
        $this->filters = $filters;
    }

    /**
     * Sets the content of the buffer.
     *
     * Note that the previous contents will be destroyed.
     *
     * @access public
     * @param string $content
     * @return void
     */
    public function setBuffer($content)
    {
        assert(is_string($content) || !isset($content));
        $this->buffer = strlen($content) > 0 ? $content : null;
    }

    /**
     * Gets the content of the current buffer
     *
     * @access public
     * @return string the current buffer
     */
    public function getBuffer()
    {
        if (!isset($this->buffer)) {
            return '';
        }

        $buffer = $this->buffer;
        foreach ($this->filters as &$filter) {
            $filter->filterOutput($buffer);
        }
        return $buffer;
    }

    /**
     * Write the string to the current buffer
     *
     * @access public
     * @param string $str
     * @return void
     */
    public function write($str)
    {
        if (isset($this->buffer)) {
            $this->buffer .= $str;
        } else {
            $this->buffer = $str;
        }
    }

    /**
     * Empty the current buffer
     *
     * @access public
     * @return void
     */
    public function clear()
    {
        $this->buffer = null;
    }

    /**
     * Flushes the current buffer via a 'print', calling each filter as it was
     * added.
     *
     * Note that this also calls the clear() method.
     *
     * @access public
     * @return void
     */
    public function flush()
    {
        print $this->getBuffer();
        $this->clear();
    }

    /**
     * A simple method to simply view a template, optionally setting aruments
     *
     * @param string|Template name the location of the template
     * @param array arguments [optional] the arguments to pass to the template,
     * expressed as name/value pairs
     * @return void
     */
    public function writeTemplate($template, $arguments=null)
    {
        $tsys = Site::getModule('TemplateSystem');
        if (is_string($template)) {
            $template = $tsys->load($name);
        } elseif (
            ! is_object($template) ||
            ! is_a($template, $tsys->getPHPSTL()->getTemplateClass())
        ) {
            throw new InvalidArgumentException('not a template');
        }
        $this->write($template->render($arguments));
    }
}

?>
