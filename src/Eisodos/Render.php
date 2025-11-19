<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
  namespace Eisodos;
  
  use Eisodos\Abstracts\Singleton;
  use Exception;
  
  /**
   * Class Application - Manages classes, creation and initialization orders, page generation
   * Credit - According to https://designpatternsphp.readthedocs.io/
   * @package Eisodos
   */
  final class Render extends Singleton {
    
    // Private properties
    
    /**
     * The generated page itself
     * @var string
     */
    public string $Response = '';
    /**
     * @var float $_scriptStartTime Performance measurement
     */
    private float $_scriptStartTime;
    
    // Public properties
    /**
     * @var string $_pageDebugInfo Debug messages included into the page
     */
    private string $_pageDebugInfo = '';
    
    /**
     *
     * @noinspection DuplicatedCode
     */
    
    protected function init(array $options_): void {
      // noop
    }
    
    /**
     * Adds debug message
     * @param $debugMessage_
     */
    public function pageDebugInfo($debugMessage_): void {
      $this->_pageDebugInfo .= $debugMessage_ . PHP_EOL;
    }
    
    // Public functions
    
    /**
     * Eisodos constructor
     * @param array $configOptions_ =[
     *     'configPath' => './config', // Configuration file's path
     *     'configType' => ConfigLoader::CONFIG_TYPE_INI,
     *     'environment' => '',        // environment variable
     *     'overwrites' => [
     *             $anykey => ''
     *         ]                       // Configuration value overwrites
     *     ] Config options
     * @param array $cacheOptions_ =[
     *     'disableHTMLCache' => false
     *     ] Cache options
     * @param array $templateEngineOptions_ =[
     *
     *     ] Template engine options
     * @param string $logLevel_ debugLevel can be 'critical'|'error'|'debug'|'info'|'warning'|'trace'
     * @throws Exception
     */
    public function start(
      array  $configOptions_,
      array  $cacheOptions_ = [],
      array  $templateEngineOptions_ = [],
      string $logLevel_ = ''
    ): void {
      $this->_scriptStartTime = microtime(true);               // script start time
      if (!Eisodos::$applicationName) {
        die('Application name is missing');
      }
      
      Eisodos::$logger->init(['logLevel' => $logLevel_]);
      Eisodos::$logger->trace('BEGIN', $this);
      
      ob_start();
      
      Eisodos::$configLoader->init($configOptions_);
      // override initial errorlevel from configuration
      Eisodos::$logger->setDebugLevels(null);
      Eisodos::$mailer->init();
      Eisodos::$translator->init();
      Eisodos::$parameterHandler->init();
      
      Eisodos::$logger->trace('Objects initialized', $this);
      
      // check if URL contains debugparameters
      if (($debugURLPrefix = Eisodos::$parameterHandler->getParam('DEBUGURLPREFIX')) !== '') {
        if (Eisodos::$parameterHandler->neq('SessionDebugLevel', '') ||
            Eisodos::$parameterHandler->neq($debugURLPrefix . 'DebugLevel', '')) {
          $debugLevel = Eisodos::$parameterHandler->getParam($debugURLPrefix . 'DebugLevel', Eisodos::$parameterHandler->getParam('SessionDebugLevel'));
          Eisodos::$parameterHandler->setParam('SessionDebugLevel', $debugLevel, true, false, 'eisodos::render');
          Eisodos::$logger->setDebugLevels(Eisodos::$parameterHandler->getParam('SessionDebugLevel', Eisodos::$parameterHandler->getParam('DEBUGLEVELS')));
          if (Eisodos::$parameterHandler->neq($debugURLPrefix . 'DebugToUrl', '')) {
            Eisodos::$parameterHandler->setParam('SessionDebugToUrl', Eisodos::$parameterHandler->getParam($debugURLPrefix . 'DebugToUrl'),true, false, 'eisodos::render');
          }
          Eisodos::$parameterHandler->setParam('DebugToUrl', Eisodos::$parameterHandler->getParam('SessionDebugToUrl', Eisodos::$parameterHandler->getParam('DEBUGTOURL')));
          if ($debugLevel !== '') {
            Eisodos::$parameterHandler->setParam('DEBUGEXCEPTIONS', 'T', false, false, 'eisodos::render');
            Eisodos::$parameterHandler->setParam('DEBUGMESSAGES', 'T', false, false, 'eisodos::render');
            Eisodos::$parameterHandler->setParam('DEBUGERRORS', 'T', false, false, 'eisodos::render');
          }
        }
        
        if (Eisodos::$parameterHandler->neq('SessionDebugRequestLog', '') || Eisodos::$parameterHandler->neq($debugURLPrefix . "RequestLog", "")) {
          $debugLevel = Eisodos::$parameterHandler->getParam($debugURLPrefix . 'RequestLog', Eisodos::$parameterHandler->getParam('SessionDebugRequestLog'));
          Eisodos::$parameterHandler->setParam('SessionDebugRequestLog', $debugLevel, true, false, 'eisodos::render');
          Eisodos::$parameterHandler->setParam('DEBUGREQUESTLOG', Eisodos::$parameterHandler->getParam('SessionDebugRequestLog'), false, false, 'eisodos::render');
        }
      }
      
      if (Eisodos::$utils->safe_array_value($cacheOptions_, 'disableHTMLCache', false)
        || Eisodos::$parameterHandler->isOn('ALWAYSNOCACHE')) {
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
      }
      
      Eisodos::$parameterHandler->setParam('.CGI', $_SERVER['PHP_SELF'], false, false, 'eisodos::render');
      Eisodos::$parameterHandler->setParam(
        'IsAJAXRequest',
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
          and strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ? 'T' : 'F',
        false,
        false,
        'eisodos::render'
      );
      
      if (Eisodos::$parameterHandler->neq('ERROROUTPUT', '')) {
        set_exception_handler(array(Eisodos::$logger, 'writeErrorLog'));
      }
      
      // check service mode
      if (Eisodos::$parameterHandler->isOn('__SERVICEMODE')) {
        header('HTTP/1.1 503 Service Temporarily Unavailable');
        header('Status: 503 Service Temporarily Unavailable');
        header('Retry-After: 300');
        exit;
      }
      
      Eisodos::$templateEngine->init($templateEngineOptions_);
      
      Eisodos::$logger->trace('END', $this);
    }
    
    /**
     * Stores the current URL into a parameter
     * @param string $parameterName_ Parameter's name
     */
    public function storeCurrentURL(string $parameterName_): void {
      Eisodos::$parameterHandler->setParam($parameterName_, $this->currentPageURL(), true, false, 'eisodos::render');
    }
    
    public function strleft($s1, $s2): string {
        return substr($s1, 0, strpos($s1, $s2));
      }
    
    /**
     * Returns the current page full URL
     * @return string
     */
    public function currentPageURL(): string {
      
      $serverReqUri = $_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'];
      
      $protocol = $this->strleft(
          strtolower($_SERVER['SERVER_PROTOCOL']),
          '/'
        ) . ((!empty($_SERVER['HTTPS']) and $_SERVER['HTTPS'] === 'on') ? 's' : '');
      $port = ($_SERVER['SERVER_PORT'] === '80') ? '' : (':' . $_SERVER['SERVER_PORT']);
      
      return $protocol . '://' . $_SERVER['SERVER_NAME'] . $port . $serverReqUri;
    }
    
    /**
     * @param bool $regenerateSessionId_
     */
    public function logout(bool $regenerateSessionId_ = true): void {
      Eisodos::$parameterHandler->clean();
      
      session_destroy();
      session_unset();
      session_set_cookie_params(Eisodos::$parameterHandler->getCookieParams());
      session_name(Eisodos::$parameterHandler->getParam('_environment') . (Eisodos::$parameterHandler->getParam('_environment') ? '-' : '') . Eisodos::$applicationName);
      session_start();
      Eisodos::$parameterHandler->setParam('SessionJustStarted', 'T');
      if ($regenerateSessionId_) {
        session_regenerate_id(true);
      }
      $_SESSION = [];
      Eisodos::$utils->removeDuplicatePHPSessionCookies();
    }
    
    /**
     * Generates the Response
     * @param bool $rawResponse_ If true the response will not be modified
     * @return void
     */
    private function _generatePage(bool $rawResponse_ = false): void {
      if (Eisodos::$parameterHandler->neq('Redirect', '')
        || Eisodos::$parameterHandler->neq('PageExpires', '')) {
        ob_end_clean();
        
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        
        if (Eisodos::$parameterHandler->neq('Redirect', '')) {
          header('Location: ' . Eisodos::$parameterHandler->getParam('Redirect'));
          
          return;
        }
      } elseif (Eisodos::$parameterHandler->neq('PermaRedirect', '')) {
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . Eisodos::$parameterHandler->getParam('PermaRedirect'));
        
        return;
      }
      
      if (!$rawResponse_) {
        $this->Response = Eisodos::$templateEngine->replaceParamInString($this->_changeEncode($this->Response));
        
        if (!Eisodos::$parameterHandler->isOn('EditorMode')) {
          $this->Response = Eisodos::$utils->replace_all($this->Response, '_dollar_', '$', true, false);
          if (!Eisodos::$parameterHandler->isOn('DISABLECURLYBRACESREPLACE')) {
            $this->Response = Eisodos::$utils->replace_all($this->Response, '{{', '[', true, false);
            $this->Response = Eisodos::$utils->replace_all($this->Response, '}}', ']', true, false);
          }
        } else {
          $this->Response = Eisodos::$utils->replace_all($this->Response, '_dollarsign_', '$', true, false);
        }
        
        $this->_makeTitle();
        $this->Response = Eisodos::$utils->replace_all(
          $this->Response,
          '%META_KEYWORDS%',
          Eisodos::$parameterHandler->getParam('META_KEYWORDS'),
          true,
          false
        );
        
        /* $a_array = explode(' ', $this->_scriptStartTime);
        $b_array = explode(' ', microtime());
        $a_array[0] = substr($a_array[0], 1);
        $b_array[0] = substr($b_array[0], 1);
        $a_string = $a_array[1] . $a_array[0];
        $b_string = $b_array[1] . $b_array[0]; */
        
        if ($this->_pageDebugInfo !== '') {
          $this->Response .= '<!-- ' . $this->_pageDebugInfo . '-->' . "\n";
        }
        
        $unit = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        $mu = memory_get_usage(true);
        $pmu = memory_get_peak_usage(true);
        $memoryUsage = (@round(
              $mu / (1024 ** ($i = (integer)floor(log($mu, 1024)))),
              2
            ) . ' ' . $unit[$i]) . ' (' . (@round(
              $pmu / (1024 ** ($i = (integer)floor(log($pmu, 1024)))),
              2
            ) . ' ' . $unit[$i]) . ')';
        //$executionTime = bcsub($b_string, $a_string). (microtime() - $this->_scriptStartTime);
        $executionTime = round(microtime(true) - $this->_scriptStartTime, 4) . ' sec';
        
        if (Eisodos::$parameterHandler->isOn('INCLUDESTATISTIC') // ajax-nal ne rakja bele
          && !(!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
          $this->Response .= "\n<!-- Memory usage: " . $memoryUsage . ', Execution time: ' . $executionTime . ' -->' . "\n";
        }
        
        Eisodos::$logger->info('Memory usage: ' . $memoryUsage);
        Eisodos::$logger->info('Execution time: ' . $executionTime);
        
        if (Eisodos::$parameterHandler->isOn('SavePageToDisk')
          && Eisodos::$parameterHandler->neq(Eisodos::$parameterHandler->getParam('SaveFileName'), '')) {
          $f = fopen('SaveFile' . Eisodos::$parameterHandler->getParam('SaveFileName'), 'wb');
          fwrite($f, $this->Response);
          fclose($f);
        }
      }
      
      header('X-Content-Length: ' . mb_strlen($this->Response));
      
      print $this->Response;
      
      ob_end_flush();
    }
    
    /**
     * Decodes embedded URL encoded text
     * {#a%3Db#} -> a=b
     * @param $page_
     * @return string
     */
    private function _changeEncode($page_): string {
      $result = $page_;
      $v = false;
      $k = strpos($page_, '{#');
      if ($k !== false) {
        $v = strpos($page_, '#}');
      }
      if ($v !== false && $v > $k) {
        $result = substr($page_, 0, $k) .
          urldecode(substr($page_, $k + 2, $v - $k - 2)) .
          substr($page_, $v + 2, strlen($page_));
      }
      
      return $result;
    }
    
    private function everything_in_tags($string, $tagname): string {
      $pattern = "#<\s*?$tagname\b[^>]*>(.*?)</$tagname\b[^>]*>#s";
      if (preg_match($pattern, $string, $matches)) {
        return $matches[1];
      }
      
      return '';
    }
    
    private function _makeTitle(): void {
      if (Eisodos::$parameterHandler->neq('TITLESTRING', '') && !Eisodos::$parameterHandler->isOn('EditorMode')) {
        $title = '';
        if (Eisodos::$parameterHandler->neq('TITLEREPLACETAG', '')) {
          $title = $this->everything_in_tags($this->Response, Eisodos::$parameterHandler->getParam('TITLEREPLACETAG'));
        } else {
          $a = @strpos(Eisodos::$parameterHandler->getParam('TITLEREPLACE'), $this->Response);
          if ($a !== false) {
            $a += strlen(Eisodos::$parameterHandler->getParam('TITLEREPLACE'));
            $b = strpos(
              Eisodos::$utils->replace_all(
                Eisodos::$parameterHandler->getParam('TITLEREPLACE'),
                '<',
                '</',
                false,
                false
              ),
              $this->Response
            );
            $title = substr($this->Response, $a, $b - $a);
            if (Eisodos::$parameterHandler->isOn('TITLECUT')) {
              if (str_contains('<', $title)) {
                $title = substr($title, 0, strpos('<', $title) - 1);
              }
            } else {
              $b = 0;
              $title2 = '';
              for ($a = 1, $aMax = strlen($title); $a <= $aMax; $a++) {
                if ($title[$a - 1] === '<') {
                  $b = 1;
                } elseif ($title[$a - 1] === '>') {
                  $b = 0;
                  $title2 .= ' ';
                } elseif ($b === 0) {
                  $title2 .= $title[$a - 1];
                }
              }
              $title = $title2;
            }
          }
        }
        
        if ($title !== '') {
          if (Eisodos::$parameterHandler->isOn('TITLECONCAT')) {
            $title .= ' - ' . Eisodos::$parameterHandler->getParam(
                'TITLEEMPTY' . Eisodos::$parameterHandler->getParam(
                  'Lang',
                  Eisodos::$parameterHandler->getParam(
                    'DEFLANG'
                  )
                )
              );
          }
          $title = trim($title);
          $this->Response = Eisodos::$utils->replace_all(
            $this->Response,
            Eisodos::$parameterHandler->getParam('TITLESTRING', '%TITLE%'),
            $title,
            true,
            false
          );
          if (Eisodos::$parameterHandler->neq('TITLECAPITALSTRING', '')) {
            $title = strtoupper($title);
            $this->Response = Eisodos::$utils->replace_all(
              $this->Response,
              Eisodos::$parameterHandler->getParam('TITLECAPITALSTRING', '%TITLECAP%'),
              $title,
              true,
              false
            );
          }
        } else {
          $this->Response = Eisodos::$utils->replace_all(
            $this->Response,
            Eisodos::$parameterHandler->getParam('TITLESTRING', '%TITLE%'),
            Eisodos::$parameterHandler->getParam(
              'TITLEEMPTY' . Eisodos::$parameterHandler->getParam(
                'Lang',
                Eisodos::$parameterHandler->getParam(
                  'DEFLANG'
                )
              )
            ),
            true,
            false
          );
          $this->Response = Eisodos::$utils->replace_all(
            $this->Response,
            Eisodos::$parameterHandler->getParam('TITLECAPITALSTRING', '%TITLECAP%'),
            Eisodos::$parameterHandler->getParam(
              'TITLEEMPTY' . Eisodos::$parameterHandler->getParam(
                'Lang',
                Eisodos::$parameterHandler->getParam(
                  'DEFLANG'
                )
              )
            ),
            true,
            false
          );
        }
      }
      
      if (Eisodos::$parameterHandler->neq('DESCRIPTIONSTRING', '') && !Eisodos::$parameterHandler->isOn('EditorMode')) {
        $a = @strpos(Eisodos::$parameterHandler->getParam('DESCRIPTIONREPLACE'), $this->Response);
        if ($a !== false) {
          $a += strlen(Eisodos::$parameterHandler->getParam('DESCRIPTIONREPLACE'));
          $b = strpos(
            Eisodos::$utils->replace_all(
              Eisodos::$parameterHandler->getParam('DESCRIPTIONREPLACE'),
              '<',
              '</',
              false,
              false
            ),
            $this->Response
          );
          $title = '';
          if ($b > 0 && $b > $a) {
            $title = substr($this->Response, $a, $b - $a);
            {
              $b = 0;
              $title2 = '';
              for ($a = 1, $aMax = strlen($title); $a <= $aMax; $a++) {
                if ($title[$a - 1] === '<') {
                  $b = 1;
                } elseif ($title[$a - 1] === '>') {
                  $b = 0;
                  $title2 .= ' ';
                } elseif ($b === 0) {
                  $title2 .= $title[$a - 1];
                }
              }
              $title = $title2;
            }
          }
          $title = trim($title);
          $this->Response = Eisodos::$utils->replace_all(
            $this->Response,
            Eisodos::$parameterHandler->getParam('DESCRIPTIONSTRING', '%DESC%'),
            $title,
            true,
            false
          );
        } else {
          $this->Response = Eisodos::$utils->replace_all(
            $this->Response,
            Eisodos::$parameterHandler->getParam('DESCRIPTIONSTRING', '%DESC%'),
            '',
            true,
            false
          );
        }
      }
    }
    
    /**
     * Finish page generation and generate the page
     */
    public function finish(): void {
      Eisodos::$parameterHandler->finish();
      if (ob_get_level() > 0) {
        $this->_generatePage();
      }  // if page cached, create response
      Eisodos::$translator->finish();
      if (Eisodos::$parameterHandler->isOn('DEBUGREQUESTLOG')) {
        Eisodos::$parameterHandler->setParam('ERROROUTPUT', str_replace('Screen', '', Eisodos::$parameterHandler->getParam('ERROROUTPUT') . ',Console'), false, false, 'eisodos::render');
        Eisodos::$logger->writeErrorLog(NULL, 'RequestLog');
      }
      session_write_close();
    }
    
    /**
     * Finish page generation with or without saving session variables or handling languages
     * @param bool $saveSessionVariables_
     * @param bool $handleLanguages_
     */
    public function finishRaw(bool $saveSessionVariables_ = false, bool $handleLanguages_ = false): void {
      Eisodos::$parameterHandler->finish($saveSessionVariables_);
      
      if ($handleLanguages_) {
        Eisodos::$translator->finish();
      }
      
      if (ob_get_level() > 0) {
        $this->_generatePage(true);
      }
      
      if (Eisodos::$parameterHandler->isOn('DEBUGREQUESTLOG')) {
        Eisodos::$parameterHandler->setParam('ERROROUTPUT', str_replace('Screen', '', Eisodos::$parameterHandler->getParam('ERROROUTPUT') . ',Console'), false, false, 'eisodos::render');
        Eisodos::$logger->writeErrorLog(NULL, 'RequestLog');
      }
      session_write_close();
    }
    
  }