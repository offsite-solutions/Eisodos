<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
  namespace Eisodos;
  
  use Eisodos\Abstracts\Singleton;
  use Exception;
  
  class Utils extends Singleton {
    
    public function safe_array_value($array_, $key_, $defaultValue_ = ''): string {
      if (isset($array_) and array_key_exists($key_, $array_)) {
        if ($array_[$key_] === '') {
          return $defaultValue_;
        }
        
        return $array_[$key_];
      }
      
      return $defaultValue_;
    }
    
    /**
     * @param $needle
     * @param $haystack
     * @param $occurence
     * @return bool|int
     */
    public function _strpos_offset($needle, $haystack, $occurence) {
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
     * @param $mixed
     * @param bool $allowNegative
     * @return bool
     */
    public function isInteger($mixed, $allowNegative = false): bool {
      if ($allowNegative) {
        return (preg_match('/^-?\d*$/', $mixed) === 1);
      }
      
      return (preg_match('/^\d*$/', $mixed) === 1);
    }
    
    /**
     * @param $mixed
     * @param bool $allowNegative
     * @return bool
     */
    public function isFloat($mixed, $allowNegative = false): bool {
      if ($allowNegative) {
        return (preg_match('/^-?\d*\.?\d*$/', $mixed) === 1);
      }
      
      return (preg_match('/^\d*\.?\d*$/', $mixed) === 1);
    }
    
    /**
     * @return string
     * @throws Exception
     */
    public function generateUUID(): string {
      // The field names refer to RFC 4122 section 4.1.2
      
      return sprintf(
        '%04x%04x-%04x-%03x4-%04x-%04x%04x%04x',
        random_int(0, 65535),
        random_int(0, 65535), // 32 bits for "time_low"
        random_int(0, 65535), // 16 bits for "time_mid"
        random_int(0, 4095),  // 12 bits before the 0100 of (version) 4 for "time_hi_and_version"
        bindec(substr_replace(sprintf('%016b', random_int(0, 65535)), '01', 6, 2)),
        // 8 bits, the last two of which (positions 6 and 7) are 01, for "clk_seq_hi_res"
        // (hence, the 2nd hex digit after the 3rd hyphen can only be 1, 5, 9 or d)
        // 8 bits for "clk_seq_low"
        random_int(0, 65535),
        random_int(0, 65535),
        random_int(0, 65535) // 48 bits for "node"
      );
    }
    
    /**
     * ORACLE like decode function
     * @param array $listOfValuePairs_
     * @return mixed|string
     */
    public function ODecode($listOfValuePairs_ = array()): string {
      if (count($listOfValuePairs_) % 2 !== 0) {
        $listOfValuePairs_[] = '';
      }
      
      $count = count($listOfValuePairs_);
      
      for ($a = 1, $aMax = floor(($count - 2) / 2); $a <= $aMax; $a++) {
        if ($listOfValuePairs_[0] === $listOfValuePairs_[$a * 2 - 1]) {
          return $listOfValuePairs_[$a * 2];
        }
      }
      if (($count - 1) % 2 === 0) {
        return $listOfValuePairs_[0];
      }
      
      if (is_string($listOfValuePairs_[$count - 1])) {
        return $this->replace_all(
          $listOfValuePairs_[$count - 1],
          '$0',
          $listOfValuePairs_[0]
        );
      }
      
      return $listOfValuePairs_[$count - 1];
    }
    
    /**
     * @param $InString
     * @param $SearchFor
     * @param $ReplaceTo
     * @param bool $All
     * @param bool $NoCase
     * @return string
     */
    public function replace_all($InString, $SearchFor, $ReplaceTo, $All = true, $NoCase = true): string {
      if (($NoCase === true) and ($All === true)) {
        return str_ireplace($SearchFor, $ReplaceTo, $InString);
      }
      
      if (($NoCase === false) and ($All === true)) {
        return str_replace($SearchFor, $ReplaceTo, $InString);
      }
      
      if (($NoCase === false) and ($All === false)) {
        return $this->str_replace_count($SearchFor, $ReplaceTo, $InString, 1);
      }
      
      // if (($NoCase == true) and ($All == false))
      return $this->str_ireplace_count($SearchFor, $ReplaceTo, $InString, 1);
    }
    
    /**
     * @param $search
     * @param $replace
     * @param $subject
     * @param $times
     * @return string
     */
    public function str_replace_count($search, $replace, $subject, $times): string {
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
    public function str_ireplace_count($search, $replace, $subject, $times): string {
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
     * @inheritDoc
     */
    protected function init($options_): void {
      // noop
    }
  }