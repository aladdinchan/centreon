<?php
/**
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */


require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonGMT.class.php';

class CentreonUtils
{
    /**
     * Remove all <script> data
     */
    const ESCAPE_LEGACY_METHOD = 0;
    /**
     * Convert all html markups into HTML entities except links
     */
    const ESCAPE_ALL_EXCEPT_LINK = 1;
    /**
     * Convert all html markups into HTML entities
     */
    const ESCAPE_ALL = 2;

    /**
     * Converts Object into Array
     *
     * @param int $idPage
     * @param boolean $redirect
     * @return mixed
     */
    public function visit($idPage, $redirect = true)
    {
        global $centreon;
        $http = '';

        if ($_SERVER['HTTPS']) {
            $http .= 'https://';
        } else {
            $http .= 'http://';
        }

        $newUrl = $http . $_SERVER['HTTP_HOST'] . $centreon->optGen["oreon_web_path"] . 'main.php?p=' . $idPage;

        if ($redirect) {
            header("Location: " . $newUrl);
            exit;
        } else {
            return stripslashes($newUrl);
        }
    }

    /**
     * Converts Object into Array
     *
     * @param mixed $arrObjData
     * @param array $arrSkipIndices
     * @return mixed
     */
    public function objectIntoArray($arrObjData, $arrSkipIndices = array())
    {
        $arrData = array();

        if (is_object($arrObjData)) {
            $arrObjData = get_object_vars($arrObjData);
        }

        if (is_array($arrObjData)) {
            foreach ($arrObjData as $index => $value) {
                if (is_object($value) || is_array($value)) {
                    $value = self::objectIntoArray($value, $arrSkipIndices);
                }
                if (in_array($index, $arrSkipIndices)) {
                    continue;
                }
                $arrData[$index] = $value;
            }
        }
        if (!count($arrData)) {
            $arrData = "";
        }
        return $arrData;
    }

    /**
     * Condition Builder
     * Creates condition with WHERE or AND or OR
     *
     * @param string $query
     * @param string $condition
     * @param bool $or
     * @return string
     */
    public static function conditionBuilder($query, $condition, $or = false)
    {
        if (preg_match('/ WHERE /', $query)) {
            if ($or === true) {
                $query .= " OR ";
            } else {
                $query .= " AND ";
            }
        } else {
            $query .= " WHERE ";
        }
        $query .= $condition . " ";
        return $query;
    }

    /**
     * Get datetime timestamp
     *
     * @param string $datetime
     * @return int
     * @throws Exception
     */
    public function getDateTimeTimestamp($datetime)
    {
        static $db;
        static $centreonGmt;

        $invalidString = "Date format is not valid";
        if (!isset($db)) {
            $db = new CentreonDB();
        }
        if (!isset($centreonGmt)) {
            $centreonGmt = new CentreonGMT($db);
        }
        $centreonGmt->getMyGMTFromSession(session_id(), $db);
        $datetime = trim($datetime);
        $res = explode(" ", $datetime);
        if (count($res) != 2) {
            throw new Exception($invalidString);
        }
        $res1 = explode("/", $res[0]);
        if (count($res1) != 3) {
            throw new Exception($invalidString);
        }
        $res2 = explode(":", $res[1]);
        if (count($res2) != 2) {
            throw new Exception($invalidString);
        }
        $timestamp = $centreonGmt->getUTCDateFromString($datetime);
        return $timestamp;
    }

    /**
     * Convert operand to Mysql format
     *
     * @param string $str
     * @return string;
     */
    public static function operandToMysqlFormat($str)
    {
        $result = "";
        switch ($str) {
            case "gt":
                $result = ">";
                break;
            case "lt":
                $result = "<";
                break;
            case "gte":
                $result = ">=";
                break;
            case "lte":
                $result = "<=";
                break;
            case "eq":
                $result = "=";
                break;
            case "ne":
                $result = "!=";
                break;
            case "like":
                $result = "LIKE";
                break;
            case "notlike":
                $result = "NOT LIKE";
                break;
            default:
                $result = "";
                break;
        }
        return $result;
    }

    /**
     * Merge with initial values
     *
     * @param Quickform $form
     * @param string $key
     * @return array
     */
    public static function mergeWithInitialValues($form, $key)
    {
        $init = array();
        try {
            $initForm = $form->getElement('initialValues');
            $initialValues = unserialize($initForm->getValue());
            if (!empty($initialValues) && isset($initialValues[$key])) {
                $init = $initialValues[$key];
            }
            $result = array_merge((array)$form->getSubmitValue($key), $init);
        } catch (HTML_QuickForm_Error $e) {
            $result = (array) $form->getSubmitValue($key);
        }
        return $result;
    }

    /**
     * Transforms an array into a string with the following format
     * '1','2','3' or '' if the array is empty
     *
     * @param array $arr
     * @param bool $transformKey | string will be formed with keys when true,
     *                             otherwise values will be used
     * @return string
     */
    public static function toStringWithQuotes($arr = array(), $transformKey = true)
    {
        $string = "";
        $first = true;
        foreach ($arr as $key => $value) {
            if ($first) {
                $first = false;
            } else {
                $string .= ", ";
            }
            $string .= $transformKey ? "'" . $key . "'" : "'" . $value . "'";
        }
        if ($string == "") {
            $string = "''";
        }
        return $string;
    }

    /**
     *
     * @param string $currentVersion Original version
     * @param string $targetVersion Version to compare
     * @param string $delimiter Indicates the delimiter parameter for version
     * @param integer $depth Indicates the depth of comparison, if 0 it means "unlimited"
     */
    public static function compareVersion($currentVersion, $targetVersion, $delimiter = ".", $depth = 0)
    {
        $currentVersionExplode = explode($delimiter, $currentVersion);
        $targetVersionExplode = explode($delimiter, $targetVersion);
        $isCurrentSuperior = false;
        $isCurrentEqual = false;


        if ($depth == 0) {
            $maxRecursion = count($currentVersionExplode);
        } else {
            $maxRecursion = $depth;
        }

        for ($i = 0; $i < $maxRecursion; $i++) {
            if ($currentVersionExplode[$i] > $targetVersionExplode[$i]) {
                $isCurrentSuperior = true;
                $isCurrentEqual = false;
                break;
            } elseif ($currentVersionExplode[$i] < $targetVersionExplode[$i]) {
                $isCurrentSuperior = false;
                $isCurrentEqual = false;
                break;
            } else {
                $isCurrentEqual = true;
            }
        }


        if ($isCurrentSuperior) {
            return 1;
        } elseif (($isCurrentSuperior === false) && $isCurrentEqual) {
            return 2;
        } else {
            return 0;
        }
    }

    /**
     * Converted a HTML string according to the selected method
     *
     * @param string $stringToEscape String to escape
     * @param int $escapeMethod Escape method (default: ESCAPE_LEGACY_METHOD)
     * @return string Escaped string
     * @see CentreonUtils::ESCAPE_LEGACY_METHOD
     * @see CentreonUtils::ESCAPE_ALL_EXCEPT_LINK
     * @see CentreonUtils::ESCAPE_ALL
     */
    public static function escapeSecure(
        $stringToEscape,
        $escapeMethod = self::ESCAPE_LEGACY_METHOD
    ) {
        switch ($escapeMethod) {
            case self::ESCAPE_LEGACY_METHOD:
                return preg_replace("/<script.*?\/script>/s", "", $stringToEscape);
            case self::ESCAPE_ALL_EXCEPT_LINK:
                return self::escapeAllExceptLink($stringToEscape);
            case self::ESCAPE_ALL:
                return self::escapeAll($stringToEscape);
        }
    }
    
    /**
     * Convert all html markups into HTML entities
     *
     * @param type $stringToEscape String to escape
     * @return string Converted string
     */
    public static function escapeAll($stringToEscape)
    {
        return htmlentities($stringToEscape, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Convert all html markups into HTML entities except links
     *
     * @param string $stringToEscape
     * @return string Converted string (except links)
     */
    public static function escapeAllExceptLink($stringToEscape)
    {
        $linkToken = '{{__LINKx__}}';
        $index = 0;
        $linksFounded = array();
        while ($occurence = self::getHtmlMarkups('a', $stringToEscape)) {
            $linksFounded[$index] = $occurence['tag'];
            $linkTag = str_replace('x', $index, $linkToken);
            $stringToEscape = substr_replace(
                $stringToEscape,
                $linkTag,
                $occurence['start'],
                $occurence['length']
            );
            $index++;
        }
        
        $escapedString = htmlentities($stringToEscape, ENT_QUOTES, 'UTF-8');
        
        for ($index = 0; $index < count($linksFounded); $index++) {
            $linkTag = str_replace('x', $index, $linkToken);
            $escapedString = str_replace($linkTag, $linksFounded[$index], $escapedString);
        }
        
        return $escapedString;
    }
    
    /**
     * Return all occurences of a html markup found in html string
     *
     * @param string $markup HTML markup to find
     * @param string $html HTML to analyse
     * @return array (('tag'=>..; 'start' =>..., 'length'=>...), ...)
     */
    public static function getHtmlMarkups($markup, $html)
    {
        $occurrences = false;
        $start = 0;
        if (($start = stripos($html, "<$markup", $start)) !== false &&
            ($end = stripos($html, "</$markup>", $end + strlen("</$markup>")))
        ) {
            if (!is_array($occurrences[$markup])) {
                $occurrences[$markup] = array();
            }
            $occurrences =
                array(
                    'tag' => substr(
                        $html,
                        $start,
                        $end + strlen("</$markup>") - $start
                    ),
                    'start' => $start,
                    'length' => $end + strlen("</$markup>") - $start
                );
        }
        return $occurrences;
    }
}
