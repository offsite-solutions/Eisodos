<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
  namespace Eisodos;
  
  use Eisodos\Abstracts\Singleton;
  use Exception;
  
  class Utils extends Singleton {
    
    /**
     * Gives back an array value if exists. If it is an empty string or null it gives back the default value
     * @param array|null $array_
     * @param string $key_
     * @param string $defaultValue_
     * @param bool $caseInsensitive_
     * @return string
     */
    public function safe_array_value(?array $array_, string $key_, string $defaultValue_ = '', bool $caseInsensitive_ = false): string {
      if (!is_array($array_)) {
        return $defaultValue_;
      }
      
      if ($caseInsensitive_) {
        $array_ = array_change_key_case($array_);
        $key_ = strtolower($key_);
      }
      
      if (array_key_exists($key_, $array_)) {
        if ($array_[$key_] === '') {
          return $defaultValue_;
        }
        
        return (string)$array_[$key_];
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
    public function isInteger($mixed, bool $allowNegative = false): bool {
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
    public function isFloat($mixed, bool $allowNegative = false): bool {
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
    public function ODecode(array $listOfValuePairs_ = []): string {
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
    public function replace_all($InString, $SearchFor, $ReplaceTo, bool $All = true, bool $NoCase = true): string {
      if (($NoCase === true) && ($All === true)) {
        return str_ireplace($SearchFor, $ReplaceTo, $InString);
      }
      
      if (($NoCase === false) && ($All === true)) {
        return str_replace($SearchFor, $ReplaceTo, $InString);
      }
      
      if (($NoCase === false) && ($All === false)) {
        return $this->str_replace_count($SearchFor, $ReplaceTo, $InString, 1);
      }
      
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
    
    /**
     * Replacement for built-in function apache_request_headers
     * @return array
     */
    private function apache_request_headers(): array {
      $arh = array();
      $rx_http = '/\AHTTP_/';
      foreach ($_SERVER as $key => $val) {
        if (preg_match($rx_http, $key)) {
          $arh_key = preg_replace($rx_http, '', $key);
          $rx_matches = explode('_', $arh_key);
          if (count($rx_matches) > 0 && strlen($arh_key) > 2) {
            foreach ($rx_matches as $ak_key => $ak_val) {
              $rx_matches[$ak_key] = ucfirst($ak_val);
            }
            $arh_key = implode('-', $rx_matches);
          }
          $arh[$arh_key] = $val;
        }
      }
      
      return ($arh);
    }
    
    /* Get HTTP Request headers */
    public function get_request_headers(): array {
      if (!function_exists('apache_request_headers')) {
        return $this->apache_request_headers();
      }
      
      return apache_request_headers();
    }
    
    /** Remove duplicate session cookies from header */
    public function removeDuplicatePHPSessionCookies(): void {
      $setCookiesBack = [];
      foreach (headers_list() as $h) {
        if (false !== stripos($h, 'Set-Cookie:')) {
          if (false !== stripos($h, session_name() . '=') &&
            false === stripos($h, session_id())) {
            continue;
          }
          $setCookiesBack[] = $h;
        }
      }
      header_remove('Set-Cookie');
      foreach ($setCookiesBack as $h) {
        header($h, false);
      }
      /* $f=fopen('/var/log/greengo/cookies.txt','ab');
      fwrite($f,print_r(headers_list(),true)."\n\n");
      fclose($f); */
    }
    
  }