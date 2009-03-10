<?php

/**
 * SiteHandler definition
 *
 * PHP version 5
 *
 * LICENSE: The contents of this file are subject to the Mozilla Public License Version 1.1
 * (the "License"); you may not use this file except in compliance with the
 * License. You may obtain a copy of the License at http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License for
 * the specific language governing rights and limitations under the License.
 *
 * The Original Code is Red Tree Systems Code.
 *
 * The Initial Developer of the Original Code is Red Tree Systems, LLC. All Rights Reserved.
 *
 * @category     Site
 * @author       Red Tree Systems, LLC <support@redtreesystems.com>
 * @copyright    2009 Red Tree Systems, LLC
 * @license      MPL 1.1
 * @version      2.0
 * @link         http://framework.redtreesystems.com
 */

/**
 * Abstract base class for site request handlers
 * Handler stages:
 *   initialize
 *   access
 *   response
 *   cleanup
 *
 */
abstract class SiteHandler
{
    /**
     * @var Site
     */
    protected $site;

    /**
     * Constructor
     *
     * @param site Site
     * @see Site::loadHandler
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    /**
     * Sets per request arguments, these are additional arguments passed to
     * Site->handle.
     *
     * The default implementation does nothing, if a subclass cares about extra
     * arguments, it needs to override this method
     *
     * @param args array
     */
    public function setArguments($args)
    {
    }

    /**
     * Sets up the environment
     *
     * @return void
     */
    public function initialize()
    {
    }

    /**
     * Cleanps up the request, called after page output has been flushed
     *
     * @return void
     */
    public function cleanup()
    {
    }

    /**
     * Flushes output to the browser
     *
     * The default implementation renders the current SitePage
     * @return void
     */
    public function sendResponse()
    {
        if (isset($this->site->page)) {
            $this->site->page->render();
        }
    }
}

?>
