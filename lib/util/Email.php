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
 * Defines standard methods the system may use to ascertain
 * information about this objects subclass.
 *
 * @static
 * @category     Email
 * @package        Utils
 */
class Email
{
    /**
     * Constructor; Private
     *
     * @access private
     * @return Email a new instance
     */
    private function __construct()
    {

    }


    /**
     * A rudimentery validation on the email address, and a check
     * if the specified domain exists. This was found on a devshed
     * article, and modified slightly.
     *
     * @static
     * @param string the email address
     * @return boolean true if the email address is properly formatted,
     * and the domain exists.
     */
    public static function IsValid($email)
    {
        if(!preg_match("/.+@.+/" , $email)) {
            return false;
        }

        list($username, $domain) = split('@', $email);

        if(!checkdnsrr("$domain.", 'MX')) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if the specified email address is found on
     * public blacklists. This method was found in the php.net
     * user notes with the following statement:
     * "written by satmd, do what you want with it, but keep the author please"
     *
     * @static
     * @access public
     * @param string $email the email address in question
     * @return true if the email domain is blacklisted
     */
    public static function IsBlackListed($email)
    {
        list($username, $domain) = split('@', $email);

        $dnsbl_check = array('bl.spamcop.net',
                'list.dsbl.org',
                'sbl.spamhaus.org');

        $ip = gethostbyname($domain);

        $quads = explode('.', $ip);
        if (count($quads) != 4) {
            return false;
        }

        $rip = $quads[3] . '.' . $quads[2] . '.' . $quads[1] . '.' . $quads[0];

        for ($i = 0; $i < count($dnsbl_check); $i++) {
            if (checkdnsrr("$rip." . $dnsbl_check[$i] . '.', 'A')) {
                return true;
            }
        }

        return false;
    }
}

?>
