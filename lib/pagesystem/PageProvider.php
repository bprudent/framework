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

abstract class PageProvider
{
    const DECLINE  = null;
    const FAIL     = 2;

    protected $pagesys;

    /**
     * Creates a new page provider
     *
     * @param PageSystem $pagesys the page system module
     */
    public function __construct(PageSystem $pagesys)
    {
        $this->pagesys = $pagesys;
        $this->pagesys->addProvider($this);
    }

    /**
     * Allows the provider to handle exception rendering.
     *
     * @param Exception $ex
     * @param boolean $isResponsible true if this provider is responsible for
     * the current page, false otherwise
     * @return Page or null
     */
    public function provideExceptionPage(Exception $ex, $isResponsible)
    {
        return null;
    }

    /**
     * Called to ask the provider to resolve an url to a Page object
     *
     * @param url string, the url being served, relative to Site::$url
     * @return mixed one of:
     *   DECLINE      - declines to serve the page
     *   FAIL         - the requested url should be error'd as not found
     *   Page object  - the page to serve
     *   Any of the last 3 will stop the page resolution process with indicated
     *   result.
     */
    abstract public function resolve($url);
}

?>
