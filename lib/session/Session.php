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
 * The Original Code is RedTree Framework Session Module
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

class Session extends SiteModule
{
    /**
     * What key to store the time the session was started in, can be overidden
     * by [session.timeKey]
     */
    protected $timeKey = '__session_start_time';

    public function initialize()
    {
        parent::initialize();

        $lifetime = $this->site->config->get('session.expire', 0);
        $path = $this->site->config->get('session.path', $this->site->url);

        if ($path[strlen($path)-1] != '/') {
            $path .= '/';
        }

        session_set_cookie_params($lifetime, $path);

        $this->site->addCallback('onPostConfig', array($this, 'onPostConfig'));
        $this->site->addCallback('onInitialize', array($this, 'start'));
        $this->site->addCallback('onAccessCheck', array($this, 'check'));
    }

    public function onPostConfig()
    {
        $this->timeKey = $this->site->config->get('session.timeKey', $this->timeKey);
    }

    public function start()
    {
        $r = session_start();
        if (! $this->has($this->timeKey)) {
            $this->set($this->timeKey, time());
        }

        $this->site->dispatchCallback('onSessionStart');

        return $r;
    }

    public function check()
    {
        $lifetime = $this->site->config->get('session.expire', 0);
        if (
            $lifetime > 0 &&
            time() - $this->get($this->timeKey) >= $lifetime
        ) {
            $this->end($this->site);
            $this->start($this->site);
        }
    }

    public function end()
    {
        $this->site->dispatchCallback('onSessionEnd');
        $sname = session_name();
        if (array_key_exists($sname, $_COOKIE) && isset($_COOKIE[$sname])) {
            setcookie($sname, '', time()-42000, '/');
        }
        $_SESSION = array();
        return session_destroy();
    }

    public function set($key, $data)
    {
        if (isset($data)) {
            $_SESSION[$key] = $data;
        } else {
            unset($_SESSION[$key]);
        }
    }

    public function get($key, $default=null)
    {
        if (array_key_exists($key, $_SESSION) && isset($_SESSION[$key])) {
            return $_SESSION[$key];
        } else {
            return $default;
        }
    }

    public function has($key)
    {
        return array_key_exists($key, $_SESSION);
    }
}

?>
