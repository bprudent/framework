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

/**
 * This is a static class to ease parameter handling. Note that
 * if magic_quotes_gpc is on, this will stripslashes() where
 * applicable.
 *
 * @static
 * @package        Utils
 */
class Params
{
    const VALIDATE_EMPTY = 0;
    const VALIDATE_EMPTY_STRICT = 1;
    const VALIDATE_NUMERIC = 1;
    const VALIDATE_EMAIL = 2;
    const VALIDATE_EMAIL_BLACKLIST = 3;

    /**
     * Constructor; Private
     *
     * @access private
     * @return Params new instance
     */
    private function __construct()
    {

    }

    /**
     * preg callback
     *
     * @param $x
     * @return transformed $x
     */
    private static function fieldToPropertyCallback($x)
    {
        return strtoupper($x[0][1]);
    }

    /**
     * Convert an underscored name to a class-worthy field name.
     * Examples:
     *      some_field => someField
     *      other_field_name => otherFieldName
     *
     * @param string $name the underscored name to convert
     * @return string the fielded name
     */
    public static function fieldToProperty($name)
    {
        return preg_replace_callback('/_(\w)/', array('Params', 'fieldToPropertyCallback'), $name);
    }

    /**
     * preg callback
     *
     * @param $x
     * @return transformed $x
     */
    private static function propertyToFieldCallback($x)
    {
        return "_" . strtolower($x[0]);
    }

    /**
     * Convert a class property name to it's underscored counterpart.
     * Examples:
     *      someField => some_field
     *      otherFieldName => other_field_name
     *
     * @param string $name the camelCase name to convert
     * @return string the underscored name
     */
    static public function propertyToField($field)
    {
        return preg_replace_callback('/[A-Z]/', array('Params', 'propertyToFieldCallback'), $field);
    }

    /**
     * Used primarly for populating a custom object from the given array.
     * This converts the standard _ param notation to the camel case object
     * notation. This method can be used to create properties that don't
     * exist, effectivly creating a parameter object.
     *
     * @static
     * @access public
     * @param array $array the associative array you wish to use to populate the object
     * @param object $object the object you wish to be populated
     * @param boolean $copyNonExistant defaults to false, which is to say
     * do not create properties - only populate existing properties
     * @return void
     */
    static public function arrayToObject(&$array, &$object, $copyNonExistant=false)
    {
        $op = null;

        if (!is_array($array)) {
            $op = get_object_vars($array);
        }
        else {
            $op =& $array;
        }

        if (!is_array($op)) {
            throw new Exception("bad param for $array");
        }

        foreach ($op as $name => $value) {
            $property = Params::fieldToProperty($name);

            if ($copyNonExistant || property_exists($object, $property)) {
                $object->$property = $value;
            }
        }
    }

    /**
     * Validates the given keys. The keys used may be in dotted (or colon)
     * format, indicating an object property on the given object.
     *
     * @param mixed $mixed an object or associtive array on which to validate
     * @param array $validation an associtive array of key/warning pairs.
     * The warning may be an array which holds other arrays
     * where the first element specifies how to
     * validate the key, and the second element is the warning.
     * which parameters were invalid.
     * @return boolean true if we passed validation, false otherwise
     */
    static public function validate(&$mixed, $validation)
    {
        $passed = true;
        $array = (is_object($mixed) ? get_object_vars($mixed) : $mixed);

        foreach ($validation as $key => $deep) {
            $deep = (is_array($deep) ? $deep : array(array(Params::VALIDATE_EMPTY, $deep)));

            foreach ($deep as $specification) {
                list($type, $message) = $specification;
                $value = null;

                $m = array();
                if (preg_match('/^(\w+)[:.](\w+)/', $key, $m)) {
                    $o = $m[1];
                    $p = $m[2];

                    if (!is_object($array[$o]) || !property_exists($array[$o], $p)) {
                        throw new InvalidArgumentException("bad param for key $key");
                    }

                    $value = $array[$o]->$p;
                }
                else {
                    $value = $array[$key];
                }

                switch ($type) {
                    case Params::VALIDATE_EMPTY:
                        if (!trim($value)) {
                            Site::getPage()->addWarning($message, $key);
                            $passed = false;
                            continue;
                        }

                        break;
                    case Params::VALIDATE_EMPTY_STRICT:
                        if (null === $value) {
                            Site::getPage()->addWarning($message, $key);
                            $passed = false;
                            continue;
                        }

                        break;
                    case Params::VALIDATE_NUMERIC:
                        if ($value && (!is_numeric($value))) {
                            Site::getPage()->addWarning($message, $key);
                            $passed = false;
                            continue;
                        }

                        break;
                    case Params::VALIDATE_EMAIL:
                        if ($value && (!Email::IsValid($value))) {
                            Site::getPage()->addWarning($message, $key);
                            $passed = false;
                            continue;
                        }

                        break;
                    case Params::VALIDATE_EMAIL_BLACKLIST:
                        if ($value && Email::IsValid($value) && (Email::IsBlackListed($value))) {
                            Site::getPage()->addWarning($message, $key);
                            $passed = false;
                            continue;
                        }

                        break;
                }
            }
        }

        return $passed;
    }

    /**
     * Safely gets a variable from $array. If $key does not exist,
     * $default is provided.
     *
     * @param array $array the array
     * @param string $name the array key
     * @param mixed $default the default value to supply if $name
     * does not exist in the array. defaults to null
     * @return mixed the value for key $name
     */
    static public function generic($array, $key, $default=null)
    {
        $value = (isset($array[$key]) ? $array[$key] : $default);
        if ($value && get_magic_quotes_gpc()) {
            if (is_array($value)) {
                $newArray = array();
                foreach ($value as $item) {
                    array_push($newArray, stripslashes($item));
                }
            }
            else {
                $value = stripslashes($value);
            }
        }

        return $value;
    }

    /**
     * Safely gets a $_POST variable. If $name does not exist,
     * $default is provided.
     *
     * @param string $name the array key
     * @param mixed $default the default value to supply if $name
     * does not exist in the array. defaults to null
     * @return mixed the value for key $name
     */
    static public function post($name, $default=null)
    {
        return Params::Generic($_POST, $name, $default);
    }

    /**
     * Safely gets a $_GET variable. If $name does not exist,
     * $default is provided.
     *
     * @param string $name the array key
     * @param mixed $default the default value to supply if $name
     * does not exist in the array. defaults to null
     * @return mixed the value for key $name
     */
    static public function get($name, $default=null)
    {
        return Params::Generic($_GET, $name, $default);
    }

    /**
     * Safely gets a $_REQUEST variable. If $name does not exist,
     * $default is provided.
     *
     * @param string $name the array key
     * @param mixed $default the default value to supply if $name
     * does not exist in the array. defaults to null
     * @return mixed the value for key $name
     */
    static public function request($name, $default=null)
    {
        return Params::Generic($_REQUEST, $name, $default);
    }

    /**
     * Safely gets a $_SERVER variable. If $name does not exist,
     * $default is provided.
     *
     * @param string $name the array key
     * @param mixed $default the default value to supply if $name
     * does not exist in the array. defaults to null
     * @return mixed the value for key $name
     */
    static public function server($name, $default=null)
    {
        return (isset($_SERVER[$name]) ? $_SERVER[$name] : $default);
    }

     /**
     * Safely gets a $_COOKIE variable. If $name does not exist,
     * $default is provided.
     *
     * @param string $name the array key
     * @param mixed $default the default value to supply if $name
     * does not exist in the array. defaults to null
     * @return mixed the value for key $name
     */
    static public function cookie($name, $default=null)
    {
        return (isset($_COOKIE[$name]) ? $_COOKIE[$name] : $default);
    }

}

?>
