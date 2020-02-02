<?php

namespace Eisodos;

use Eisodos\Abstracts\Singleton;

class Utils extends Singleton
{

    public function safe_array_value($array_, $key_, $defaultValue_ = '')
    {
        if (isset($array_) and array_key_exists($key_, $array_)) {
            if ($array_[$key_] == '') {
                return $defaultValue_;
            } else {
                return $array_[$key_];
            }
        } else {
            return $defaultValue_;
        }
    }

    /**
     * @param $needle
     * @param $haystack
     * @param $occurence
     * @return bool|int
     */
    public function _strpos_offset($needle, $haystack, $occurence)
    {
        if (($o = strpos($haystack, $needle)) === false) {
            return false;
        }
        if ($occurence > 1) {
            $found = $this->_strpos_offset($needle, substr($haystack, $o + strlen($needle)), $occurence - 1);

            return ($found !== false) ? $o + $found + strlen($needle) : false;
        }

        return $o;
    }

    /**
     * @param $search
     * @param $replace
     * @param $subject
     * @param $times
     * @return string
     */
    public function str_replace_count($search, $replace, $subject, $times)
    {
        $subject_original = $subject;
        $len = strlen($search);
        $pos = 0;
        for ($i = 1; $i <= $times; $i++) {
            $pos = strpos($subject, $search, $pos);
            if ($pos !== false) {
                $subject = substr($subject_original, 0, $pos);
                $subject .= $replace;
                $subject .= substr($subject_original, $pos + $len);
                $subject_original = $subject;
            } else {
                break;
            }
        }

        return ($subject);
    }

    /**
     * @param $search
     * @param $replace
     * @param $subject
     * @param $times
     * @return string
     */
    public function str_ireplace_count($search, $replace, $subject, $times)
    {
        $subject_original = $subject;
        $len = strlen($search);
        $pos = 0;
        for ($i = 1; $i <= $times; $i++) {
            $pos = strpos(strtolower($subject), strtolower($search), $pos);
            if ($pos !== false) {
                $subject = substr($subject_original, 0, $pos);
                $subject .= $replace;
                $subject .= substr($subject_original, $pos + $len);
                $subject_original = $subject;
            } else {
                break;
            }
        }

        return ($subject);
    }

    /**
     * @param $InString
     * @param $SearchFor
     * @param $ReplaceTo
     * @param bool $All
     * @param bool $NoCase
     * @return string|string[]
     */
    public function replace_all($InString, $SearchFor, $ReplaceTo, $All = true, $NoCase = true)
    {
        if (($NoCase == true) and ($All == true)) {
            return str_ireplace($SearchFor, $ReplaceTo, $InString);
        } elseif (($NoCase == false) and ($All == true)) {
            return str_replace($SearchFor, $ReplaceTo, $InString);
        } elseif (($NoCase == false) and ($All == false)) {
            return $this->str_replace_count($SearchFor, $ReplaceTo, $InString, 1);
        } else { // if (($NoCase == true) and ($All == false))
            return $this->§str_ireplace_count($SearchFor, $ReplaceTo, $InString, 1);
        }
    }

    /**
     * @param $mixed
     * @param bool $allowNegative
     * @return bool
     */
    public function isInteger($mixed, $allowNegative = false)
    {
        if ($allowNegative) {
            return (preg_match('/^-?\d*$/', $mixed) == 1);
        } else {
            return (preg_match('/^\d*$/', $mixed) == 1);
        }
    }

    /**
     * @param $mixed
     * @param bool $allowNegative
     * @return bool
     */
    public function isFloat($mixed, $allowNegative = false)
    {
        if ($allowNegative) {
            return (preg_match('/^-?\d*\.?\d*$/', $mixed) == 1);
        } else {
            return (preg_match('/^\d*\.?\d*$/', $mixed) == 1);
        }
    }

    /**
     * @return string
     */
    public function generateUUID()
    {
        // The field names refer to RFC 4122 section 4.1.2

        return sprintf(
            '%04x%04x-%04x-%03x4-%04x-%04x%04x%04x',
            mt_rand(0, 65535),
            mt_rand(0, 65535), // 32 bits for "time_low"
            mt_rand(0, 65535), // 16 bits for "time_mid"
            mt_rand(0, 4095),  // 12 bits before the 0100 of (version) 4 for "time_hi_and_version"
            bindec(substr_replace(sprintf('%016b', mt_rand(0, 65535)), '01', 6, 2)),
            // 8 bits, the last two of which (positions 6 and 7) are 01, for "clk_seq_hi_res"
            // (hence, the 2nd hex digit after the 3rd hyphen can only be 1, 5, 9 or d)
            // 8 bits for "clk_seq_low"
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 65535) // 48 bits for "node"
        );
    }

    /**
     * ORACLE like decode function
     * @param array $listOfValuePairs_
     * @return mixed|string
     */
    public function ODecode($listOfValuePairs_ = array())
    {
        if (count($listOfValuePairs_) % 2 != 0) {
            $listOfValuePairs_[count($listOfValuePairs_)] = "";
        }
        for ($a = 1; $a <= floor((count($listOfValuePairs_) - 2) / 2); $a++) {
            if ($listOfValuePairs_[0] == $listOfValuePairs_[$a * 2 - 1]) {
                return $listOfValuePairs_[$a * 2];
            }
        }
        if ((count($listOfValuePairs_) - 1) % 2 == 0) {
            return $listOfValuePairs_[0];
        } elseif (is_string($listOfValuePairs_[count($listOfValuePairs_) - 1])) {
            return $this->replace_all(
                $listOfValuePairs_[count($listOfValuePairs_) - 1],
                "\$0",
                $listOfValuePairs_[0],
                true,
                true
            );
        } else {
            return $listOfValuePairs_[count($listOfValuePairs_) - 1];
        }
    }

    /**
     * @inheritDoc
     */
    protected function init($options_)
    {
        // noop
    }
}