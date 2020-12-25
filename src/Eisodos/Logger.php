<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
  namespace Eisodos;
  
  require_once('Utils.php');
  
  use DateTime;
  use Eisodos\Abstracts\Singleton;
  use Exception;
  use PC;
  use Psr\Log\LoggerInterface;
  
  final class Logger extends Singleton implements LoggerInterface {
    
    // Private variables
    private $debugLog = [];
  
    /**
     * @var array $debugLevels Debug level set
     */
    private $debugLevels = [];
    /**
     * @var bool $_cliMode script running in CLI mode
     */
    private $_cliMode = false;
  
    // Public variables
    /**
     * @var int
     */
    private $traceStep = 0;
  
    // Private functions
  
    /**
     * Generates log file
     * @param Exception|null $exception_
     * @param string $debugInformation_
     * @return string
     */
    private function _generateFileLog(?Exception $exception_, $debugInformation_ = ''): string {
      $st = "\n";
      $st .= '---------- ' . Eisodos::$applicationName . " ----------\n";
      $st .= date('Y.m.d. H:i:s') . "\n";
      if ($exception_) {
        $st .= $exception_->getMessage() . "\n";
        $st .= $exception_->getFile() . ' at line ' . $exception_->getLine() . "\n";
        $st .= $exception_->getTraceAsString() . "\n";
      }
      $st .= "----- Extended Error -----\n";
      $st .= Eisodos::$utils->safe_array_value($_POST, '__EISODOS_extendedError') . "\n";
      $st .= "----- URL -----\n";
      if (array_key_exists('REQUEST_URI', $_SERVER)) {
        $st .= $_SERVER['REQUEST_URI'] . "\n";
      }
      $st .= "----- Parameters -----\n";
      $st .= Eisodos::$parameterHandler->params2log();
      if ($debugInformation_ !== '') {
        $st .= "----- Extended info -----\n";
        $st .= $debugInformation_;
      }
    
      return $st;
    }
  
    private function traceStep($text_, &$traceStep_) {
      switch ($text_) {
        case 'BEGIN':
          {
            return 2 * $traceStep_++;
          }
        case 'END':
          {
            return 2 * max([0, --$traceStep_]);
          }
        default:
          {
            return 2 * $traceStep_;
          }
      }
    }
  
    // Public functions
  
    /**
     * Logger initialization
     * @param string $debugLevel_ Comma separated list of levels: error,info,warning,debug,trace,alert,emergency,notice
     */
    public function init($debugLevel_): void {
      $this->setDebugLevels($debugLevel_);
      $this->_cliMode = (PHP_SAPI === 'cli');
    }
  
    /** Sets debug level
     * @param string $debugLevel_ trace,debug,info,notice,alert,warning,error,emergency,critical
     */
    public function setDebugLevels($debugLevel_): void {
      if (!$debugLevel_) {
        return;
      }
      if (strpos($debugLevel_, ',') !== false) {
        $this->debugLevels = explode(',', $debugLevel_);
        Eisodos::$parameterHandler->setParam("DebugLevels", $debugLevel_, false, false, 'eisodos::logger');
      } else {
        $levels = 'trace,debug,info,notice,alert,warning,error,emergency,critical';
        $this->debugLevels = explode(',', substr($levels, strpos($levels, $debugLevel_)));
        Eisodos::$parameterHandler->setParam("DebugLevels", substr($levels, strpos($levels, $debugLevel_)), false, false, 'eisodos::logger');
      }
    }
  
    /**
     * Writes error log to file, mail, screen, callback
     * target can be set in config by ERROROUTPUT=file,mail,screen,"@callback"
     * In CLI Mode screen echos the log to the standard output
     * @param Exception|NULL $exception_ Exception object
     * @param string $debugInformation_ Extra debug information
     * @param array $extraMails_ Send the debug to the mail address specified
     * @return void
     */
    public function writeErrorLog(?Exception $exception_, $debugInformation_ = "", $extraMails_ = array()): void {
      try {
        if (strpos(Eisodos::$parameterHandler->getParam('ERROROUTPUT'), '@') !== false) {
          $errorOutput = Eisodos::$parameterHandler->getParam('ERROROUTPUT') . ',';
          $error_function = substr($errorOutput, strpos($errorOutput, '@') + 1);
          $error_function = substr($error_function, 0, strpos($error_function, ',') - 1);
          $error_function(
            $this,
            array(
              'Message' => ($exception_ ? $exception_->getMessage() . "\n" : '') . Eisodos::$utils->safe_array_value(
                  $_POST,
                  '__EISODOS_extendedError'
                ),
              'File' => $exception_->getFile(),
              'Line' => $exception_->getLine(),
              'Trace' => $exception_->getTraceAsString(),
              'Parameters' => Eisodos::$parameterHandler->params2log(),
              'Debug' => $debugInformation_
            )
          );
        }
      } catch (Exception $e) {
        Eisodos::$parameterHandler->setParam(
          'ERROROUTPUT',
          Eisodos::$parameterHandler->getParam('ERROROUTPUT') . ',Mail',
          false,
          false,
          'eisodos::logger'
        );
      }
      
      $errorString = $this->_generateFileLog($exception_, $debugInformation_);
      
      try {
        if (strpos(Eisodos::$parameterHandler->getParam('ERROROUTPUT'), 'File') !== false) {
          $logfile = fopen(
            Eisodos::$templateEngine->replaceParamInString(Eisodos::$parameterHandler->getParam('ERRORLOG')),
            'ab'
          ) or die("can't open log file (" . Eisodos::$templateEngine->replaceParamInString(Eisodos::$parameterHandler->getParam('ERRORLOG')) . ")");
          fwrite($logfile, $errorString);
          fclose($logfile);
        }
      } catch (Exception $ex) {
        Eisodos::$parameterHandler->setParam(
          'ERROROUTPUT',
          Eisodos::$parameterHandler->getParam('ERROROUTPUT') . ',Mail',
          false,
          false,
          'eisodos::logger'
        );
      }
      
      try {
        if (strpos(Eisodos::$parameterHandler->getParam('ERROROUTPUT'), 'Mail') !== false
          and Eisodos::$parameterHandler->neq('ERRORMAILTO', '')
          and Eisodos::$parameterHandler->neq('ERRORMAILFROM', '')) {
          $extraMails = $extraMails_;
          $extraMails[] = Eisodos::$parameterHandler->getParam('ERRORMAILTO');
          foreach ($extraMails as $row) {
            Eisodos::$mailer->utf8_html_mail(
              $row,
              Eisodos::$applicationName . ' error',
              '<html lang="en"><meta content="text/html; charset=utf-8" http-equiv="Content-Type" /><body><pre>' . htmlspecialchars(
                $errorString
              ) . '</pre></body></html>',
              Eisodos::$parameterHandler->getParam('ERRORMAILFROM')
            );
          }
        }
      } catch (Exception $ex) {
      }
      
      if (strpos(Eisodos::$parameterHandler->getParam('ERROROUTPUT'), 'Screen') !== false
        and $this->_cliMode === false) {
        Eisodos::$templateEngine->addToResponse(
          '<html lang="en"><meta content="text/html; charset=utf-8" http-equiv="Content-Type" /><body><pre>' . htmlspecialchars(
            $errorString
          ) . '</pre></body></html>'
        );
        // in case of screen is set, the rendering stops immediately
        Eisodos::$render->finish();
        exit;
      }
      
      if (strpos(Eisodos::$parameterHandler->getParam('ERROROUTPUT'), 'Screen') !== false
        and $this->_cliMode === true) {
        print($errorString);
      }
    }
    
    /**
     * Adds debug message to PhpConsole or in CLI Mode to the standard output
     * @param string $text_ Message
     * @param string $debugLevel_ Debug level 'critical','error','info','warning','debug','trace','emergency','alert','notice'
     * @param object|null $sender_ Sender object
     */
    public function log($text_, $debugLevel_ = 'debug', $sender_ = NULL): void {
  
      if (in_array($debugLevel_, $this->debugLevels, false)) {
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
    
        if (array_key_exists(2, $dbt)) {
          $functionName = Eisodos::$utils->safe_array_value($dbt[2], 'function');
          $className = Eisodos::$utils->safe_array_value($dbt[2], 'class');
        } else {
          $functionName = '';
          if ($sender_ === NULL) {
            $className = '';
          } else {
            $className = get_class($sender_);
          }
        }
        
        $now = DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));
        
        $debugText = str_pad(
            '[' . $now->format('Y-m-d H:i:s.u') . '] [' . mb_strtoupper($debugLevel_) . '] ' .
            '[' . $className . ']' .
            ($functionName === '' ? '' : ' [' . $functionName . ']')
            ,
            100
          ) . '|' . str_repeat(
            ' ',
            $this->traceStep($text_, $this->traceStep)
          ) . $text_;
    
        if ($this->_cliMode) {
          echo($debugText . PHP_EOL);
        } else if (class_exists('PC', false)) {
          PC::debug($debugText);
        } else {
          $this->debugLog[] = $debugText;
        }
      }
    }
  
    public function getDebugLog(): array {
      return $this->debugLog;
    }
  
    /**
     * Log a critical message
     *
     * @param string $text_ Message to be displayed in the log message
     * @param null $sender_ Sender object. When specified debug will display the name of the sender object. Defaults to `null`.
     */
    public function critical($text_, $sender_ = NULL): void {
      $this->log($text_, 'critical', $sender_);
    }
  
    /**
     * Log an error message
     *
     * @param string $text_ Message to be displayed in the log message
     * @param object|null $sender_ Sender object. When specified debug will display the name of the sender object. Defaults to `null`.
     */
    public function error($text_, $sender_ = NULL): void {
      $this->log($text_, 'error', $sender_);
    }
    
    /**
     * Log an info message
     *
     * @param string $text_ Message to be displayed in the log message
     * @param object|null $sender_ Sender object. When specified debug will display the name of the sender object. Defaults to `null`.
     */
    public function info($text_, $sender_ = NULL): void {
      $this->log($text_, 'info', $sender_);
    }
    
    /**
     * Log a warning message
     *
     * @param string $text_ Message to be displayed in the log message
     * @param object|null $sender_ Sender object. When specified debug will display the name of the sender object. Defaults to `null`.
     */
    public function warning($text_, $sender_ = NULL): void {
      $this->log($text_, 'warning', $sender_);
    }
    
    /**
     * Log a debug message
     *
     * @param string $text_ Message to be displayed in the log message
     * @param object|null $sender_ Sender object. When specified debug will display the name of the sender object. Defaults to `null`.
     */
    public function debug($text_, $sender_ = NULL): void {
      $this->log($text_, 'debug', $sender_);
    }
    
    /**
     * Log a trace message
     *
     * @param string $text_ Message to be displayed in the log message
     * @param object|null $sender_ Sender object. When specified debug will display the name of the sender object. Defaults to `null`.
     */
    public function trace($text_, $sender_ = NULL): void {
      $this->log($text_, 'trace', $sender_);
    }
    
    /**
     * Log an alert message
     *
     * @param string $text_ Message to be displayed in the log message
     * @param object|null $sender_ Sender object. When specified debug will display the name of the sender object. Defaults to `null`.
     */
    public function alert($text_, $sender_ = NULL): void {
      $this->log($text_, 'alert', $sender_);
    }
    
    /**
     * Log an emergency message
     *
     * @param string $text_ Message to be displayed in the log message
     * @param object|null $sender_ Sender object. When specified debug will display the name of the sender object. Defaults to `null`.
     */
    public function emergency($text_, $sender_ = NULL): void {
      $this->log($text_, 'emergency', $sender_);
    }
    
    /**
     * Log a notice message
     *
     * @param string $text_ Message to be displayed in the log message
     * @param object|null $sender_ Sender object. When specified debug will display the name of the sender object. Defaults to `null`.
     */
    public function notice($text_, $sender_ = NULL): void {
      $this->log($text_, 'notice', $sender_);
    }
    
  }