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

class PageSystem extends SiteModule
{
    public static $RequiredModules = array(
        'TemplateSystem'
    );

    /**
     * The list of registered page providers
     *
     * @var array of PageProvider
     */
    protected $providers=array();

    /**
     * The page being currently built/rendered
     *
     * @var Page
     */
    protected $currentPage;

    public function initialize()
    {
        parent::initialize();

        require_once $this->dir.'/Page.php';
        require_once $this->dir.'/HTMLPage.php';
        require_once $this->dir.'/PageProvider.php';
        require_once $this->dir.'/ContentPageProvider.php';
        require_once $this->dir.'/ExceptionPage.php';
        require_once $this->dir.'/NotFoundPage.php';

        $this->site->config->addFile($this->dir.'/defaults.ini');

        $this->site->addCallback('onPostConfig', array($this, 'onPostConfig'));
    }

    public function onPostConfig(Site $site)
    {
        new ContentPageProvider($this);

        $this->site->addCallback('onException', array($this, 'onException'));
        $this->site->addCallback('onParseUrl', array($this, 'onParseUrl'));
        $this->site->addCallback('onSendResponse', array($this, 'renderCurrentPage'));
    }

    public function onParseUrl(Site $site)
    {
        foreach ($this->providers as $provider) {
            $r = $provider->resolve($this->site->requestUrl);
            if ($r === PageProvider::FAIL || $r instanceof Page) {
                $this->currentPage = $r;
                break;
            } elseif (isset($r)) {
                throw new InvalidArgumentException(
                    'Expecting a provider constant or a Page object '.
                    'from '.get_class($provider).'::resolve'
                );
            }
        }

        if (
            ! isset($this->currentPage) ||
            $this->currentPage === PageProvider::FAIL
        ) {
            $this->currentPage = new NotFoundPage($this->site);
        }
        $this->dispatchCallback('onPageResolved', $this, $this->currentPage);
    }

    public function renderCurrentPage(Site $site)
    {
        if (isset($this->currentPage)) {
            $this->currentPage->render();
        }
    }

    /**
     * Site exception handler
     *
     * Builds and renders an ExceptionPage
     * @param Site $site
     * @param Exception $ex
     * @return void
     */
    public function onException(Site $site, Exception $ex)
    {
        $this->currentPage = new ExceptionPage($this->site, $ex, $this->currentPage);
        $this->renderCurrentPage($site);
    }

    /**
     * Gets the current page, or throws a RuntimeException if there is none
     *
     * @return Page
     */
    public function getCurrentPage()
    {
        if (! isset($this->currentPage)) {
            throw new RuntimeException('no current page');
        }
        return $this->currentPage;
    }

    /**
     * @return array of PageProvider
     */
    public function getProviders()
    {
        return $this->providers;
    }

    /**
     * @param PageProvider $provider
     * @param boolean $prepend defaults false
     * @return void
     */
    public function addProvider(PageProvider $provider, $prepend=false)
    {
        foreach ($this->providers as $p) {
            if ($provider === $p) {
                return;
            }
        }
        if ($prepend) {
            array_unshift($this->providers, $provider);
        } else {
            array_push($this->providers, $provider);
        }
    }

    /**
     * Determines the layout for a page
     *
     * This is called from the HTMLPage constructor, a layout may or may not
     * have been set by the constructor depending on if one was provided by the
     * instantiating scope.
     *
     * The onLayoutHTMLPage callback is dispatched, callbacks should modify the
     * page directly and throw a StopException if they wan to halt the callback.
     * If the page has no layout set after dispatching, then it will be set to
     * the config value [page.html.defaultLayout].
     *
     * @param page HTMLPage
     * @return string
     * @see HTMLPage, CallbackManager::dispatchCallback
     */
    public function layoutHTMLPage(HTMLPage $page)
    {
        $this->dispatchCallback('onLayoutHTMLPage', $page);
        if (! $page->hasLayout()) {
            $page->setLayout($this->site->config->get('page.html.defaultLayout'));
        }
    }
}

?>
