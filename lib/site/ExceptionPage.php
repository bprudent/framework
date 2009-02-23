<?php

/**
 * ExceptionPage definition
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

require_once 'lib/site/SitePage.php';

/**
 * ExceptionPage is the page used to display unhandled exceptions
 *
 * It's a basic no frills html page.
 *
 * Note: this should NOT be an HTMLPage, there's no reason that an exception
 * needs to be themed, it's a programming error if this page needs to be used
 *
 * @package Ui
 */
class ExceptionPage extends SitePage
{
    /**
     * The exception being displayed
     *
     * @var Exception
     */
    public $exception;

    /**
     * The page context that the original exception was thrown from
     *
     * This may be null if the exception happened early enough
     *
     * @var SitePage
     */
    public $oldPage;

    /**
     * Constructor
     *
     * Creates a new HTMLPage.
     *
     * While this is publically accessible for flexibility, this should be
     * sparingly used; you likely meant to call the static method Current.
     *
     * @see Current
     */
    public function __construct(Exception $ex, $oldPage=null)
    {
        parent::__construct('text/html');

        if (! isset($oldPage)) {
            $oldPage = SitePage::getCurrent(true);
            if ($oldPage === $this) {
                $oldPage = null;
            }
        }
        $this->oldPage = $oldPage;
        $this->exception = $ex;
    }

    protected function getTemplate()
    {
        return TemplateSystem::load('page/exception.xml');
    }

    protected function getTemplateArguments()
    {
        return array_merge(parent::getTemplateArguments(), array(
            'exception' => $this->exception,
            'oldPage'   => $this->oldPage
        ));
    }
}

?>
