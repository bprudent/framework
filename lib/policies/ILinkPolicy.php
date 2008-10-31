<?php

/**
 * ILinkPolicy interface definition
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
 * @category     Application
 * @author       Red Tree Systems, LLC <support@redtreesystems.com>
 * @copyright    2007 Red Tree Systems, LLC
 * @license      MPL 1.1
 * @version      3.0
 * @link         http://framework.redtreesystems.com
 */

/**
 * Defines a link policy. An implementation of this policy may be set to change the 
 * framework's standard behavior.
 *
 * @category     Policies
 * @package      Core
 */
interface ILinkPolicy
{
    /**
     * Parse the current request and populate the superglobals
     *
     * @return  void
     */
    public function parse();

    /**
     * Returns text in href form suitable for linking to other actions within the framework.
     * 
     * @static 
     * @access public
     * @param string a component class name
     * @param string $action the action id you want to link to
     * @param array $options an associative array of implementation-dependent parameters
     * @param int $stage the stage you want to link to, default Stage::VIEW
     * @return string text to use in an href upon success; null upon failure
     */
    public function getActionURI($component, $action, $options=array(), $stage=Stage::VIEW);    
}

?>