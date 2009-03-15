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
 * Loading stub for the framework
 */
class Loader
{
    /**
     * Framework version handling
     *
     * A version string is of the form Major.minor.micro
     *   3.0.0 or 3.1.99
     */
    static public $FrameworkVersion = "3.0.76";
    static private $fwVersion = null;
    static private $targetVersion = 3.00074;


    /**
     * Returns a version floating number suitable for comparing from 3 numeric
     * components.
     *
     * Example:
     *   Loader::makeVersion(3, 1, 20) == 3.01020
     *   Loader::makeVersion(1, 23, 456) == 1.23456
     *
     * This makes comparing versions far more straihtforward than a sequence
     * boolean logic.
     *
     * @param int $major
     * @param int $minor
     * @param int $micro=null
     * @return float
     */
    static public function makeVersion($major, $minor, $micro=null)
    {
        assert(is_int($major));
        assert(is_int($minor));
        assert($major > 0);
        assert($minor > 0 && $minor < 100);

        $ver = $major + $minor/100;

        if (isset($micro)) {
            assert(is_int($micro));
            assert($micro > 0 && $micro < 1000);
            $ver += $micro/100000;
        }

        // A float like a.bbccc such as 3.00090 or 3.01005
        return $ver;
    }

    /**
     * Parses a version string like "n.nn.nnn" into a floating number like
     * n.nnnnn, this basically just explodes the string, verifies each
     * component, and calls makeVersion.
     *
     * @param string $string
     * @return float
     */
    static public function makeVersionFromString($string)
    {
        $matches = array();
        if (preg_match('/(\d+)\.(\d+)(?:\.(\d+))?/', $string, $matches)) {
            $matches = array_slice($matches, 1);
            $major = (int) $matches[0];
            $minor = (int) $matches[1];
            if (count($matches) > 2) {
                $micro = (int) $matches[2];
            } else {
                $micro = null;
            }
            return self::makeVersion($major, $minor, $micro);
        } else {
            throw new InvalidArgumentException("Invalid version $major");
        }
    }

    /**
     * Tests whether the framework version is over a given version
     *
     * @param major int
     * @param minor int
     * @param micro int optional
     * @return boolean
     */
    static public function frameworkVersionOver($major, $minor, $micro=null)
    {
        if (! isset(self::$fwVersion)) {
            self::$fwVersion = self::makeVersionFromString(self::$FrameworkVersion);
        }
        $ver = self::makeVersion($major, $minor, $micro);
        return self::$fwVersion >= $ver;
    }

    /**
     * This is the version of the framework that the site is targeted against.
     *
     * This defaults to "3.0" since that's when versioning was introduced.
     *
     * If the target version doesn't contain a micro version, it will default to 74.
     *
     * This is due to the versioning convention such that:
     *   The development version of "3.2.0" will be versioned like "3.1.80"
     *   or so with a micro version over 75.
     *
     * The end result of this is that a site that says it targets "3.0" is
     * effectively saying "I'm okay with 3.0.0-3.0.74", which can be
     * restated "all stable 3.0.x releases".
     */
    static public function setTargetVersion($version)
    {
        self::$targetVersion = self::makeVersionFromString($version);
    }

    /**
     * Tests whether the site's targeted framework version is past a given
     * version
     *
     * @param major int
     * @param minor int
     * @return boolean
     */
    static public function targetVersionOver($major, $minor, $micro)
    {
        $ver = self::makeVersion($major, $minor, $micro);
        return $this->targetVersion >= $ver;
    }

    /**
     * The absolute path to the site root, such as /var/www/full/path/to/app.
     */
    static public $Base;

    /**
     * The absolute path of the framework
     */
    static public $FrameworkPath;

    /**
     * The absolute path to the site's specific code area
     */
    static public $LocalPath;

    /**
     * Whether we're running from the cli or not
     */
    static public $IsCli;
}

/**
 * Variables index.php can set:
 *   $FrameworkTargetVersion - a string like "3.5"
 *   $FrameworkPath          - a string, set this only if you've done something
 *                             really horrible like moving Loader.php to
 *                             somewhere odd
 *   $SiteBase               - a string like "/path/to/base", set only if
 *                             normal determination doesn't hold, i.e. if
 *                             $_SERVER['SCRIPT_FILENAME'] doesn't live at the
 *                             top of the site
 */
if (isset($FrameworkTargetVersion)) {
    Loader::setTargetVersion($FrameworkTargetVersion);
}

if (isset($SiteBase)) {
    Loader::$Base = realpath($SiteBase);
    if (Loader::$Base === false || ! is_dir($SiteBase)) {
        throw new InvalidArgumentException("invalid \$SiteBase $SiteBase");
    }
} else {
    Loader::$Base = dirname(realpath($_SERVER['SCRIPT_FILENAME']));
}

if (isset($FrameworkPath)) {
    Loader::$FrameworkPath = realpath($FrameworkPath);
} else {
    Loader::$FrameworkPath = dirname(__FILE__);
}

// TODO make this go away
Loader::$LocalPath = Loader::$Base;
set_include_path(implode(':', array(
    get_include_path(),
    Loader::$FrameworkPath,
    Loader::$LocalPath
)));

Loader::$IsCli = 'cli' === php_sapi_name();
if (Loader::$IsCli) {
    $_SERVER = array(
        'SERVER_NAME' => 'cli',
        'SERVER_PORT' => 80,
        'PHP_SELF'    => '/'
    );
}

require_once 'lib/site/Site.php';

?>
