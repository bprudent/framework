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

/**
 * Resolves urls to a template resource "pageContent:url"
 */
class ContentPageProvider extends PageProvider
{
    private static $loadingPage = null;

    /**
     * Returns the page being currently loaded, this is so that template
     * providers can poke at it
     *
     * @return HTMLPage
     */
    public static function getLoadingPage()
    {
        return self::$loadingPage;
    }

    /**
     * Resolves content pages, currently limited to being "only" an HTMLPage
     * @see PageProvider::resolve
     */
    public function resolve($url)
    {
        try {
            self::$loadingPage = $page = new HTMLPage(
                $this->pagesys->getSite(),
                null,
                "pageContent:$url"
            );
            self::$loadingPage = null;
            return $page;
        } catch (PHPSTLNoSuchResource $ex) {
            self::$loadingPage = null;
            return PageProvider::DECLINE;
        } catch (Exception $e) {
            self::$loadingPage = null;
            throw $e;
        }
    }
}

?>