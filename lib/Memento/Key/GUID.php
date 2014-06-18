<?php
/**
 * Memento Key GUID generator and validation class
 *
 * @author Gary Rogers <gmrwebde@gmail.com>
 */

namespace Memento\Key;

class GUID
{
    /**
     * Generates a GUID
     * @return string guid
     */
    static function generate()
    {
        mt_srand((double)microtime() * 10000);
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid = substr($charid, 0, 8) . $hyphen
            .substr($charid, 8, 4) . $hyphen
            .substr($charid,12, 4) . $hyphen
            .substr($charid,16, 4) . $hyphen
            .substr($charid,20,12);
        return $uuid;
    }

    /**
     * Validate a GUID
     *
     * @param  string  $guid guid
     * @return boolean       returns true if the string matches
     */
    static function isValid($guid)
    {
        $regex = '/^([0-9a-fA-F]){8}-([0-9a-fA-F]){4}-([0-9a-fA-F]){4}-([0-9a-fA-F]){4}-([0-9a-fA-F]){12}(?:\:{1}.*)$/';
        if(preg_match($regex, $guid))
        {
            return true;
        }
        return false;
    }
}