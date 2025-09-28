<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
  namespace Eisodos;
  
  // TODO: (normal) implement loopcount to protect infinite loops in replaceParamInString()
  // TODO: (low - must check performance test first) use multibyte substr, strpos, etc functions
  
  use Eisodos\Abstracts\Singleton;
  use Exception;
  use PC;
  use RuntimeException;
  
  /**
   * Class ParameterHandler
   * @package Eisodos
   */
  final class ParameterHandler extends Singleton {
    
    // Private variables
    
    private array $_collectedParams = array();   // collected request parameters
    private bool $_collectedParamsFileError = false;
    private int $_param_SEQ = 0;               // inner sequence
    private int $_param_SEQ2 = 0;              // inner sequence #2
    private array $_params = array();            // parameter values
    private array $_cookies = array();           // permanent cookies
    private array $_skippedParams = array();     // skipped parameters
    
    // Public variables
    
    // Private functions
    
    /**
     * Parameter Handler initializer
     * @param array $options_
     * @return void
     */
    public function init(array $options_ = []): void {
      $this->_initParameterCollecting();
      
      // loading session variables
      $this->_loadSessionVariables();
      
      // load cookies to the parameter array
      foreach ($_COOKIE as $p => $v) {
        $this->setParam($p, $v, false, true, 'cookie');
      }
      
      $LInputParams = array_change_key_case(array_merge($_POST, $_GET));
      
      Eisodos::$configLoader->initVersioning(
        Eisodos::$utils->safe_array_value($LInputParams, 'devversion', $this->getParam('DevVersion'))
      );
      
      $crc = $this->_loadInputParams($LInputParams);
      
      // Parameter filter can cause immediate redirect
      if ($this->neq('Redirect', '')) {
        $this->finish();
        exit;
      }
      
      // re-post, reload detection
      
      // init REPOST variable
      $this->setParam('RePost', 'F', false, false, 'eisodos::parameterHandler');
      
      // init LastPostID variable
      if ($this->eq('LastPostID', '')) {
        $this->setParam('LastPostID', '0', true, false, 'eisodos::parameterHandler');
      }
      
      // check REPOST
      if ($this->neq('PostID', '')) {
        if ((integer)$this->getParam('PostID') <= (integer)$this->getParam('LastPostID')) {
          $this->setParam('RePost', 'T', false, false, 'eisodos::parameterHandler');
        } else {
          $this->setParam('LastPostID', $this->getParam('PostID'), true, false, 'eisodos::parameterHandler');
        }
      }
      
      // increase LastPostID
      $this->setParam('PostID', (string)((integer)$this->getParam('LastPostID') + 1), false, false, 'eisodos::parameterHandler');
      
      if ($this->neq('Reload', 'F')) {
        if ((string)$crc === $this->getParam('CRC')) {
          $this->setParam('Reload', 'T', false, false, 'eisodos::parameterHandler');
        } else {
          $this->setParam('Reload', 'F', false, false, 'eisodos::parameterHandler');
          $this->setParam('CRC', (string)$crc, true, false, 'eisodos::parameterHandler');
        }
      }
      
    }
    
    /**
     * Collects the input parameters into a log file
     */
    private function _initParameterCollecting(): void {
      if ($this->neq('COLLECTPARAMSTOFILE', '') && file_exists(
          Eisodos::$templateEngine->replaceParamInString($this->getParam('COLLECTPARAMSTOFILE'))
        )) {
        $file = fopen(Eisodos::$templateEngine->replaceParamInString($this->getParam('COLLECTPARAMSTOFILE')), 'rb');
        if (!($file === false)) {
          while (!feof($file)) {
            $line = rtrim(fgets($file));
            if ($line === '') {
              continue;
            }
            $l = explode('=', $line, 2);
            $this->_collectedParams[strtolower($l[0])] = (count($l) === 1 ? '' : trim($l[1]));
          }
          fclose($file);
        } else {
          $this->_collectedParamsFileError = true;
        }
      }
    }
    
    /**
     * Checks if parameter's value is not equal to the value specified
     * @param string $parameterName_ Parameter's name
     * @param mixed $value_ Parameter's value
     * @param string $defaultValue_ Default value if parameter nt exists
     * @param bool $caseInsensitive_ Case insensitive parameter name search
     * @param bool $trimValue_ Trim value
     * @return bool
     */
    public function neq(
      string $parameterName_,
      mixed  $value_,
      string $defaultValue_ = '',
      bool   $caseInsensitive_ = true,
      bool   $trimValue_ = true
    ): bool {
      return (!$this->eq($parameterName_, $value_, $defaultValue_, $caseInsensitive_, $trimValue_));
    }
    
    /**
     * Checks if parameter's value is on ('T','ON','1','TRUE')
     * @param string $parameterName_ Name of the parameter
     * @param string $defaultValue_ If value is empty use this instead
     * @return bool
     */
    public function isOn(
      string $parameterName_,
      string $defaultValue_ = 'F'
    ): bool {
      
      if (str_starts_with($parameterName_, '^')) {
        $parameterName_ = (string)$this->getParam(substr($parameterName_, 1));
      }
      
      $value = strtoupper($this->getParam($parameterName_, $defaultValue_));
      
      return (in_array($value, ['T', 'ON', '1', 'TRUE', 'YES', 'Y', true], true));
    }
    
    /**
     * Checks if parameter's value is off ('T','ON','1','TRUE')
     * @param string $parameterName_ Name of the parameter
     * @param string $defaultValue_ If value is empty use this instead
     * @return bool
     */
    public function isOff(
      string $parameterName_,
      string $defaultValue_ = 'T'
    ): bool {
      if (str_starts_with($parameterName_, '^')) {
        $parameterName_ = (string)$this->getParam(substr($parameterName_, 1));
      }
      
      $value = strtoupper($this->getParam($parameterName_, $defaultValue_));
      
      return (in_array($value, ['F', 'OFF', '0', 'FALSE', 'NO', 'N', false], true));
    }
    
    /**
     * Checks if parameter's value is the same as value specified
     * @param string $parameterName_ Name of the parameter
     * @param mixed $value_ Value, if first character is ^ it is parsed as a parameter name
     * @param string $defaultValue_ If value is empty use this instead
     * @param bool $caseInsensitive_ Case insensitive check
     * @param bool $trimValue_ Trim value before compare
     * @return bool
     */
    public function eq(
      string $parameterName_,
             $value_,
      string $defaultValue_ = '',
      bool   $caseInsensitive_ = true,
      bool   $trimValue_ = true
    ): bool {
      if (str_starts_with($value_, '^')) {
        $value_ = (string)$this->getParam(substr($value_, 1));
      }
      
      if (str_starts_with($parameterName_, '^')) {
        $parameterName_ = (string)$this->getParam(substr($parameterName_, 1));
      }
      
      if ($trimValue_ === true) {
        if ($caseInsensitive_) {
          return (strtolower($value_) === strtolower(trim($this->getParam($parameterName_, $defaultValue_))));
        }
        
        return ((string)$value_ === trim($this->getParam($parameterName_, $defaultValue_)));
      }
      
      if ($caseInsensitive_) {
        return (strtolower($value_) === strtolower($this->getParam($parameterName_, $defaultValue_)));
      }
      
      return ((string)$value_ === $this->getParam($parameterName_, $defaultValue_));
    }
    
    // Public functions
    
    /**
     * Get parameter's value with some internal variables (mostly for backward compatibility)
     * @param string $parameterName_ Parameter's name - case insensitive
     * @param string $defaultValue_ in case of parameter is not exists or its value is empty, return with this
     * @return string|array
     * @noinspection TypeUnsafeComparisonInspection
     */
    public function getParam(string $parameterName_, string $defaultValue_ = ''): mixed {
      try {
        $parameterName_ = str_replace('___', '-', strtolower($parameterName_));
        switch ($parameterName_) {
          case 'seq':
            $this->_param_SEQ++;
            
            return (string)$this->_param_SEQ;
          case 'seq0':
            $this->_param_SEQ = 0;
            
            return (string)$this->_param_SEQ;
          case 'seql':
            return (string)$this->_param_SEQ;
          case 'seqbit':
            $this->_param_SEQ++;
            
            return (string)($this->_param_SEQ % 2);
          case 'seqlbit':
            return (string)($this->_param_SEQ % 2);
          case 'seq2':
            $this->_param_SEQ2++;
            
            return (string)$this->_param_SEQ2;
          case 'seq20':
            $this->_param_SEQ2 = 0;
            
            return (string)$this->_param_SEQ2;
          case 'seq2l':
            return (string)$this->_param_SEQ2;
          case 'seq2bit':
            $this->_param_SEQ2++;
            
            return (string)($this->_param_SEQ2 % 2);
          case 'seq2lbit':
            return (string)($this->_param_SEQ2 % 2);
          case 'currdate':
            return date('Y');
          case 'lnbr':
            return PHP_EOL;
          case '_':
            return '_';
          case '_sessionid':
            return session_id();
          case 'https':
            return (!empty($_SERVER['HTTPS']) and $_SERVER['HTTPS'] !== 'off' or $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
          case 'random':
            $lastrandom = '';
            for ($a = 1; $a <= 8; $a++) {
              $lastrandom .= chr(ord('a') + random_int(0, 25));
            }
            $this->setParam('lastrandom', $lastrandom, false, false, 'eisodos::parameterHandler');
            
            return $lastrandom;
        }
        if (str_starts_with($parameterName_, 'env_')) {
          $v = getenv(substr($parameterName_, 4));
          if ($v === false || $v === '') {
            $v = getenv(strtoupper(substr($parameterName_, 4)));
            if ($v === false) {
              $v = '';
            }
          }
        } else if (isset($this->_params[$parameterName_])) {
          $v = $this->_params[$parameterName_]['value'];
        } else {
          $v = '';
        }
        
        return ($v === '' ? $defaultValue_ : $v);
      } catch (Exception) {
        return '';
      }
    }
    
    /**
     * Add parameter to the parameter's array
     * If parameter name starts with !, then it will readonly
     * @param string $parameterName_ Parameter's name
     * @param mixed $value_ Parameter's value
     * @param bool $sessionStored_ If true the parameter will be stored in the session variables
     * @param bool $cookieStored_ If true the parameter will be stored as cookie
     * @param string $source_ Source of the parameter (config,input,environment,etc.)
     */
    public function setParam(
      string $parameterName_,
      mixed  $value_ = '',
      bool   $sessionStored_ = false,
      bool   $cookieStored_ = false,
      string $source_ = ''): void {
      
      if (!$parameterName_) {
        return;
      }
      $parameterName_ = strtolower($parameterName_);
      if ($parameterName_[0] === '.') {
        $parameterName_ = substr($parameterName_, 1);
        $readOnly = true;
      } else {
        $readOnly = false;
      }
      if ($cookieStored_) {
        $sessionStored_ = false;
      }
      if (!is_array($value_)) {
        $value_ = (string)$value_;
      }
      if (($source_ !== 'request' && $source_ !== '')
        || !array_key_exists($parameterName_, $this->_params)
        || !$this->_params[$parameterName_]['readonly']
      ) {
        if (!is_array($value_) && ($value_ !== '') && ($value_[0] === '^')) {
          $this->_params[$parameterName_]['value'] = $this->getParam(substr($value_, 1, strlen($value_)));
        } else {
          $this->_params[$parameterName_]['value'] = $value_;
        }
        $this->_params[$parameterName_]['readonly'] = $readOnly;
        if (isset($this->_params[$parameterName_]['flag'])) {
          if (($this->_params[$parameterName_]['flag'] === '') && ($sessionStored_)) {
            $this->_params[$parameterName_]['flag'] = 's';
          }
          if (($this->_params[$parameterName_]['flag'] === '') && ($cookieStored_)) {
            $this->_params[$parameterName_]['flag'] = 'c';
          }
        } else {
          $this->_params[$parameterName_]['flag'] = '';
          if ($cookieStored_) {
            $this->_params[$parameterName_]['flag'] = 'c';
          }
          if ($sessionStored_) {
            $this->_params[$parameterName_]['flag'] = 's';
          }
        }
        $this->_params[$parameterName_]['source'] = ((isset($this->_params[$parameterName_]['source']) && $this->_params[$parameterName_]['source'] !== '') ? $this->_params[$parameterName_]['source'] . ';' : '') . $source_;
      } else {
        Eisodos::$logger->error('Parameter ' . $parameterName_ . ' overwrite is forbidden to ' . $value_);
      }
    }
    
    /**
     * Loads session variables into the parameter array and filters them by the rules defined in the .params file
     */
    private function _loadSessionVariables(): void {
      mt_srand((double)microtime() * 1000000);
      if (!session_id()) {
        session_set_cookie_params($this->getCookieParams());
        session_name($this->getParam('_environment') . ($this->getParam('_environment') ? '-' : '') . Eisodos::$applicationName);
        session_start();
        $this->setParam('SessionJustStarted', 'T');
      }
      // if the parameter was skipped in the parameter filter file, it must be skipped here too
      if (isset($_SESSION)) {
        foreach ($_SESSION as $p => $v) {
          $skipParameter = false;
          foreach ($this->_skippedParams as $skipName => $skipValue) {
            if (($skipValue && stripos($p, $skipName) === 0)
              || (!$skipValue && strtolower($p) === strtolower($skipName))) {
              $skipParameter = true;
              break;
            }
          }
          if (!$skipParameter) {
            $this->setParam($p, $v, true, false, 'session');
          }
        }
      }
    }
    
    /**
     * Loads parameters into the parameter array by the rules defined in the .params file
     * @param array $parameters_
     * @param bool $base64Decode_
     * @return float|int
     */
    private function _loadInputParams(
      array $parameters_ = array()
    ) {
      $result = 0;
      
      $LParamFilters2 = array();
      
      $parameterFilterLines = [];
      Eisodos::$configLoader->loadParameterFilters($parameterFilterLines);
      
      foreach ($parameterFilterLines as $line) {
        $line = trim($line);
        if ($line && !str_starts_with($line, '#')) {
          $line .= ';;;;;';
          $items = explode(';', $line);
          if ($items[0] === 'permanent') {
            $a = explode('=', $items[1]);
            if (Eisodos::$utils->safe_array_value($this->_cookies, $a[0]) === ''
              && count($a) > 1) {
              $this->_cookies[$a[0]] = $a[1];
            }
            $items[1] = $a[0];
          }
          $LParamFilters2[$items[1]] = $items;
          // 0: command
          // 1: parameternev
          // 2: tipus
          // 3: tipus hiba
          // 4: errorlog szoveg
        }
      }
      
      $LParamFilters2 = array_change_key_case($LParamFilters2);
      $this->_cookies = array_change_key_case($this->_cookies);
      $this->_collectedParams = array_change_key_case($this->_collectedParams);
      
      // removing parameters from collected params which declared in .params
      
      if ($this->neq('COLLECTPARAMSTOFILE', '')) {
        foreach ($this->_collectedParams as $n => $v) {
          foreach ($LParamFilters2 as $fn => $fv) // filterparam is a substring of inputparams and its marked with *
          {
            if (($fn === $n) ||
              ((str_contains($fn, '*')) && (str_starts_with($n,
                  Eisodos::$utils->replace_all($fn,
                    '*',
                    '',
                    false,
                    false
                  ))))) {
              unset($this->_collectedParams[$n]);
              break;
            }
          }
        }
      }
      
      $trimInputParams = $this->isOn('TRIMINPUTPARAMS', 'T');
      $trimTrailingPer = $this->isOn('TRIMTRAILINGPER', 'T');
      
      foreach ($parameters_ as $n => $v) {
        $doNotAddIt = false;
        $decodeIt = false;
        $cookieIt = false;
        $storeIt = false;
        $SIDCoded = false;
        
        // type checking
        $parameterType = '';
        $parameterTypeError = '';
        $parameterTypeErrorLog = '';
        
        // Null byte injection protection
        $v = str_replace(chr(0), '', $v);
        
        if ($trimInputParams && !is_array($v)) {
          $v = trim($v);
          if ($trimTrailingPer && $v !== '') {
            $v = rtrim($v, '/');
          }
        }
        
        foreach ($LParamFilters2 as $fn => $fv) {
          if (($fn === $n) ||
            ((str_contains($fn, '*'))
              && (str_starts_with($n, Eisodos::$utils->replace_all($fn, '*', '', false, false))))) {
            switch ($fv[0]) {
              case 'exclude':
                $doNotAddIt = true;
                break;
              case 'encoded':
                $decodeIt = true;
                break;
              case 'permanent':
              case 'cookie':
                $cookieIt = true;
                break;
              case 'session':
                $storeIt = true;
                break;
              case 'protected':
                $storeIt = true;
                $SIDCoded = true;
                break;
              case 'cookie_encoded':
                $cookieIt = true;
                $decodeIt = true;
                break;
              case 'session_encoded':
                $storeIt = true;
                $decodeIt = true;
                break;
              case 'protected_encoded':
                $storeIt = true;
                $decodeIt = true;
                $SIDCoded = true;
                break;
              case 'input':
                break; // nothing to do, just type check
              case 'skip':
                $this->_skippedParams[Eisodos::$utils->replace_all($fn, '*', '', false, false)] = (str_contains($fn, '*'));
                break;
            }
            $parameterType = $fv[2];
            $parameterTypeError = $fv[3];
            $parameterTypeErrorLog = $fv[4];
            break;
          }
        }
        
        if ($doNotAddIt) {
          Eisodos::$render->pageDebugInfo('Invalid parameter: ' . $n);
          continue;
        }
        
        try {
          if ($decodeIt) {
            $v = $this->udSDecode($v);
          }
        } catch (Exception $e) {
          Eisodos::$render->pageDebugInfo('Invalid input param (decode) ' . $n);
          continue;
        }
        
        try {
          if ($SIDCoded
            && ($this->getParam($n) !== $v)
            && !(($this->udSDecode($parameters_['csid']) === $this->getParam('SESSIONID'))
              || (($this->neq('ALLOWADMIN', ''))
                && (str_contains($this->getParam('ALLOWADMIN', '127.0.0.1'), $_SERVER['REMOTE_ADDR'])))
            )) {
            throw new RuntimeException('Error SID Decoding parameter');
          }
        } catch (Exception) {
          Eisodos::$render->pageDebugInfo("Invalid input param (siddecode) $n");
          continue;
        }
        
        if ($parameterType !== '') { // parameter tipus ellenorzese, dekodolas utan
          $typeError = false;
          if ($parameterType === 'numeric') {
            if (!Eisodos::$utils->isInteger($v, true)) {
              $typeError = true;
            }
          } elseif (preg_match($parameterType, $v) !== 1) {
            $typeError = true;
          } // regexp
          
          if ($typeError) {
            if ($parameterTypeErrorLog !== '') {
              Eisodos::$logger->writeErrorLog(
                new Exception('Invalid parameter value [' . $n . ']=[' . $v . ']')
              );
            } else {
              Eisodos::$logger->debug('Invalid parameter value [' . $n . ']=[' . $v . ']');
            }
            if (str_starts_with($parameterTypeError, '/') || str_starts_with($parameterTypeError, 'http')) {
              $this->setParam('Redirect', $parameterTypeError, false, false, 'eisodos::parameterHandler');
            } else {
              $v = $parameterTypeError;
            }
          }
        }
        
        $this->setParam($n, $v, $storeIt, $cookieIt, 'request');
        
        if (!is_array($v)) {
          if (($v !== '')
            && !array_key_exists($n, $LParamFilters2)
            && $this->isOn('DEBUGMISSINGPARAMS')) {
            Eisodos::$logger->debug('Missing parameter: [' . strtoupper($n) . '] ');
          }
          
          if (($v !== '') && !array_key_exists($n, $LParamFilters2)) {
            $this->_collectedParams[$n] = 'input;' . strtoupper($n) . ';' . (Eisodos::$utils->isInteger(
                $v,
                true
              ) ? 'numeric;' : 'text;') . ';';
          }
          
          $row = $n . '=' . $v;
          for ($b = 1, $bMax = strlen($row); $b <= $bMax; $b++) {
            $result += $b / ord($row[$b - 1]);
          }
        }
      }
      
      /* in case no lang parameter defined and header recognition is on */
      if ($this->eq('Lang', '') && $this->isOn('LANGFROMHEADER')) {
        $headers = Eisodos::$utils->get_request_headers();
        $browserLanguages = [];
        // if specific header is configured, get its value and split them
        if ($this->neq('LANGHEADER', '') && array_key_exists($this->getParam('LANGHEADER'), $headers)) {
          $browserLanguages = explode(',', $headers[$this->getParam('LANGHEADER')]);
        }
        // languages are split from hu, hu-HU, en, en-US;q=0.8 hu;q=0.7 en
        if (array_key_exists('Accept-Language', $headers)) {
          $browserLanguages = array_merge($browserLanguages, explode(',', explode(';', $headers['Accept-Language'])[0]));
        }
        // leave the first part of languages hu, hu-HU, en, en-US
        foreach ($browserLanguages as $i => $iValue) {
          $browserLanguages[$i] = explode('-', strtoupper(trim($iValue)))[0];
        }
        // match input language to the languages configured
        $acceptedLanguages = explode(',', $this->getParam('LANGS'));
        foreach ($browserLanguages as $browserLanguage) {
          if (in_array($browserLanguage, $acceptedLanguages, true)) {
            $this->setParam('Lang', $browserLanguage, true, false, 'eisodos::parameterHandler');
            break;
          }
        }
      }
      
      if ($this->neq('DEFLANG', '') && $this->eq('Lang', '')) {
        $this->setParam('Lang', $this->getParam('DEFLANG'), true, false, 'eisodos::parameterHandler');
      }
      
      $this->_collectedParams = array_change_key_case($this->_collectedParams, CASE_UPPER);
      
      return $result;
    }
    
    /**
     * Decrypts udSEncoded input
     * @param string $textToDecode_ Text to decrypt
     * @param bool $useMarks_ If encrypted with useMarks option
     * @return string
     * @throws Exception
     */
    public function udSDecode(
      string $textToDecode_ = '',
      bool   $useMarks_ = false
    ): string {
      $d = '';
      $c = $textToDecode_;
      for ($a = 1, $aMax = strlen($c); $a <= $aMax; $a++) {
        if (preg_match('/[G-Zg-z]/', $c[$a - 1]) || ($useMarks_ && preg_match("/[!-\/]/", $c[$a - 1]))) {
          $d .= str_pad(dechex(ord($c[$a - 1])), 2, '0', STR_PAD_LEFT);
        } else {
          $d .= $c[$a - 1];
        }
      }
      $c = '';
      for ($a = 1, $aMax = floor(strlen($d) / 2); $a <= $aMax; $a++) {
        $hex = $this->_swap($d[$a * 2 - 2] . $d[$a * 2 - 1]);
        if (!preg_match('/[0-9a-fA-F]/', $hex)) {
          throw new RuntimeException('Error decoding parameter');
        }
        $c .= chr(hexdec($hex) - $a);
      }
      
      return $c; // TODO exception generalas, ha ervenytelen karakter a decode utan
    }
    
    /**
     * Swaps two letters strings
     * @param $text_
     * @return string
     */
    private function _swap(
      $text_
    ): string {
      return $text_[1] . $text_[0];
    }
    
    public function finish(bool $saveSessionVariables_ = true): void {
      if ($saveSessionVariables_ &&
        !Eisodos::$parameterHandler->isOn('Logout')
      ) {
        $this->_saveSessionVariables();
      }
      
      if ($this->_collectedParamsFileError === false
        && Eisodos::$parameterHandler->neq('COLLECTPARAMSTOFILE', '')
        && !Eisodos::$parameterHandler->isOn('EditorMode')
      ) {
        $file = fopen(Eisodos::$templateEngine->replaceParamInString($this->getParam('COLLECTPARAMSTOFILE')), 'wb');
        if (flock($file, LOCK_EX | LOCK_NB)) {
          ksort($this->_collectedParams);
          $mx = 0;
          foreach ($this->_collectedParams as $key => $value) {
            if (strlen($key) > $mx) {
              $mx = strlen($key);
            }
          }
          foreach ($this->_collectedParams as $key => $value) {
            fwrite($file, str_pad($key . '=', $mx + 1) . $value . "\n");
          }
          flock($file, LOCK_UN);
        } else {
          Eisodos::debug('Parameter file was blocked for writing!');
        }
        fclose($file);
      }
      
      Eisodos::$utils->removeDuplicatePHPSessionCookies();
    }
    
    /** Gives back cookie domain if set */
    private function getCookieDomain(): string {
      if ($this->neq('COOKIE_DOMAIN', '')) {
        $domain = $this->getParam('COOKIE_DOMAIN');
        if (str_contains($domain, ',')) {
          $domains = explode(',', $domain);
          $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
          $domainClear = preg_replace('/:\d+$/', '', $host);
          if (in_array($domainClear, $domains, true)) {
            return $domainClear;
          }
          
          return $domains[0];
        }
        
        return $domain;
        
      }
      
      return '';
    }
    
    public function getCookieParams(): array {
      $domain = $this->getCookieDomain();
      
      return
        [
          'path' => $this->getParam('COOKIE_PATH.' . $domain, $this->getParam('COOKIE_PATH')),
          'domain' => $domain, // leading dot for compatibility or use subdomain
          'secure' => ($this->getParam('COOKIE_SECURE.' . $domain, $this->getParam('COOKIE_SECURE', 'F')) === 'T'),
          'httponly' => ($this->getParam('COOKIE_HTTPONLY.' . $domain, $this->getParam('COOKIE_HTTPONLY', 'F')) === 'T'),    // or false
          'samesite' => $this->getParam('COOKIE_SAMESITE.' . $domain, $this->getParam('COOKIE_SAMESITE', 'None')) // None || Lax  || Strict
        ];
    }
    
    
    /**
     *
     */
    private function _saveSessionVariables(): void {
      // ha valami direktbe toltott a session tombbe, akkor azt felvesszuk az LParams tombbe
      // pl. facebook login felveszi a fb_0000_state valtozot ide
      if (isset($_SESSION)) {
        foreach ($_SESSION as $key => $v) {
          if (!array_key_exists($key, $this->_params)) {
            Eisodos::$parameterHandler->setParam($key, $v, true, false, 'session');
          }
        }
      }
      $_SESSION = array();
      // default cookie options
      $_cookie_options = $this->getCookieParams();
      foreach ($this->_params as $key => $v) {
        if ($v['flag'] === 's') {
          $_SESSION[$key] = $v['value'];
        } elseif ($v['flag'] === 'c') {
          if (Eisodos::$utils->safe_array_value($this->_cookies, $key) !== '') {
            if (in_array(
              strtolower($key),
              explode(',', strtolower(Eisodos::$parameterHandler->getParam('RAWCOOKIES'))),
              true
            )) {
              setcookie($key, $v['value'], array_merge($_cookie_options, ['expires' => time() + 60 * 60 * 24 * $this->_cookies[$key]]));
            } else {
              setrawcookie($key, $v['value'], array_merge($_cookie_options, ['expires' => time() + 60 * 60 * 24 * $this->_cookies[$key]]));
            }
          } elseif (in_array(
            strtolower($key),
            explode(',', strtolower(Eisodos::$parameterHandler->getParam('RAWCOOKIES'))),
            true
          )) {
            setrawcookie($key, $v['value'], $_cookie_options);
          } else {
            setcookie($key, $v['value'], $_cookie_options);
          }
        }
      }
      Eisodos::$utils->removeDuplicatePHPSessionCookies();
    }
    
    /**
     * Encrypts input
     * @param string $textToCode_
     * @param bool $useMarks_ If false the output will contain only letters
     * @return string
     */
    public function udSCode(
      string $textToCode_,
      bool   $useMarks_ = false
    ): string {
      $c = $textToCode_;
      $d = '';
      for ($a = 0, $aMax = strlen($c); $a < $aMax; $a++) {
        $d .= $this->_swap(str_pad(dechex(ord($c[$a]) + $a + 1), 2, '0', STR_PAD_LEFT));
      }
      $c = '';
      for ($a = 1, $aMax = floor(strlen($d) / 2); $a <= $aMax; $a++) {
        try {
          if ((preg_match('/[G-Zg-z]/', chr(hexdec($d[$a * 2 - 2] . $d[$a * 2 - 1]))))
            || ($useMarks_ && (preg_match("/[!-\/]/", chr(hexdec($d[$a * 2 - 2] . $d[$a * 2 - 1])))))
          ) {
            $c .= chr(hexdec($d[$a * 2 - 2] . $d[$a * 2 - 1]));
          } else {
            $c .= $d[$a * 2 - 2] . $d[$a * 2 - 1];
          }
        } catch (Exception) {
          $c .= $d[$a * 2 - 2] . $d[$a * 2 - 1];
        }
      }
      
      return $c;
    }
    
    /**
     * Generates a concatenated string from the parameters array
     * @return string
     */
    public function params2log(): string {
      $st = '';
      foreach ($this->_params as $key => $value) {
        $st .=
          ($value['source'] === '' ? '' : '[' . $value['source'] . '] ') .
          ($value['flag'] === '' ? '' : '(' . $value['flag'] . ') ') .
          ($value['readonly'] ? '(ro) ' : '') .
          $key . '=' . (strlen($value['value']) > 255 ? substr($value['value'], 0, 255) . '...' : $value['value']) .
          "\n";
      }
      
      return $st;
    }
    
    /**
     * Returns with an array with the given pattern's parameter names
     * @param string $pattern_ Regular expression pattern
     * @return array
     */
    public function getParamNames(string $pattern_): array {
      return array_keys(
        array_intersect_key($this->_params, array_flip(preg_grep($pattern_, array_keys($this->_params))))
      );
    }
    
    public function clean(): void {
      foreach ($this->_params as $key => $v) {
        if ($v['flag'] === 's') { // cleanup session variables
          $this->_params[$key]['value'] = '';
          $this->_params[$key]['flag'] = '';
        } // cleanup cookies except permanent and raw cookies
        elseif ($v['flag'] === 'c'
          && Eisodos::$utils->safe_array_value($this->_cookies, $key) === ''
          && !in_array(strtolower($key), explode(',', strtolower($this->getParam('RAWCOOKIES', ''))), true)) {
          $this->_params[$key]['value'] = '';
        }
      }
    }
    
    public function getParameterArray(): array {
      return $this->_params;
    }
    
    public function mergeParameterArray(array $params_): void {
      $this->_params = array_merge($this->_params, $params_);
    }
  }