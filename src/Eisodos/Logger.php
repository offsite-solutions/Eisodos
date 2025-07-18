<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
  namespace Eisodos;
  
  require_once('Utils.php');
  
  use DateTime;
  use Eisodos\Abstracts\Singleton;
  use Exception;
  use Throwable;
  use PC;
  use Psr\Log\LoggerInterface;
  
  final class Logger extends Singleton implements LoggerInterface {
    
    // Private variables
    private array $debugLog = [];
    
    /**
     * @var array $debugLevels Debug level set
     */
    private array $debugLevels = [];
    /**
     * @var bool $_cliMode script running in CLI mode
     */
    private bool $_cliMode = false;
    
    // Public variables
    /**
     * @var int
     */
    private int $traceStep = 0;
    
    // Private functions
    
    /**
     * Generates log file
     * @param Throwable|null $throwable_
     * @param string $debugInformation_
     * @return string
     */
    private function _generateFileLog(?Throwable $throwable_, string $debugInformation_ = ''): string {
      $st = "\n";
      $st .= '---------- ' . Eisodos::$applicationName . " ----------\n";
      $st .= date('Y.m.d. H:i:s') . "\n";
      if ($throwable_) {
        $st .= $throwable_->getMessage() . "\n";
        $st .= $throwable_->getFile() . ' at line ' . $throwable_->getLine() . "\n";
        $st .= $throwable_->getTraceAsString() . "\n";
      }
      $st .= "----- Extended Error -----\n";
      $st .= Eisodos::$utils->safe_array_value($_POST, '__EISODOS_extendedError') . "\n";
      $st .= "----- URL -----\n";
      if (array_key_exists('REQUEST_URI', $_SERVER)) {
        $st .= $_SERVER['REQUEST_URI'] . "\n";
      }
      $st .= "----- Parameters -----\n";
      $st .= Eisodos::$parameterHandler->params2log();
      $st .= "----- Headers -----\n";
      foreach (Eisodos::$utils->get_request_headers() as $key => $value) {
        $st .= $key . '=' . $value . "\n";
      }
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
            $traceStep_ = max([0, $traceStep_++]);
            
            return 2 * $traceStep_;
          }
        case 'END':
          {
            $traceStep_ = max([0, --$traceStep_]);
            
            return 2 * $traceStep_;
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
     * @param array $options_ Array of debug levels: error,info,warning,debug,trace,alert,emergency,notice
     */
    public function init(array $options_): void {
      $this->setDebugLevels($options_);
      $this->_cliMode = (PHP_SAPI === 'cli');
    }
    
    /** Sets debug level
     * @param string $debugLevels_ trace,debug,info,notice,alert,warning,error,emergency,critical
     */
    public function setDebugLevels(array $debugLevels_): void {
      if (count($debugLevels_) === 0) {
        return;
      }
      if (count($debugLevels_) > 1) {
        $this->debugLevels = $debugLevels_;
        Eisodos::$parameterHandler->setParam("DebugLevels", implode(',', $debugLevels_), false, false, 'eisodos::logger');
      } else {
        $debugLevelsStr_ = implode(',', $debugLevels_);
        $levels = 'trace,debug,info,notice,alert,warning,error,emergency,critical';
        $this->debugLevels = explode(',', substr($levels, strpos($levels, $debugLevelsStr_)));
        Eisodos::$parameterHandler->setParam("DebugLevels", substr($levels, strpos($levels, $debugLevelsStr_)), false, false, 'eisodos::logger');
      }
    }
    
    /**
     * Writes error log to file, mail, screen, callback
     * target can be set in config by ERROROUTPUT=file,mail,screen,"@callback"
     * In CLI Mode screen echos the log to the standard output
     * @param Throwable|NULL $throwable_ Throwable object
     * @param string $debugInformation_ Extra debug information
     * @param array $extraMails_ Send the debug to the mail address specified
     * @return void
     */
    public function writeErrorLog(?Throwable $throwable_, $debugInformation_ = "", $extraMails_ = array()): void {
      try {
        if (strpos(Eisodos::$parameterHandler->getParam('ERROROUTPUT'), '@') !== false) {
          $errorOutput = Eisodos::$parameterHandler->getParam('ERROROUTPUT') . ',';
          $error_function = substr($errorOutput, strpos($errorOutput, '@') + 1);
          $error_function = substr($error_function, 0, strpos($error_function, ',') - 1);
          $error_function(
            $this,
            array(
              'Message' => ($throwable_ ? $throwable_->getMessage() . "\n" : '') . Eisodos::$utils->safe_array_value(
                  $_POST,
                  '__EISODOS_extendedError'
                ),
              'File' => $throwable_->getFile(),
              'Line' => $throwable_->getLine(),
              'Trace' => $throwable_->getTraceAsString(),
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
      
      $errorString = $this->_generateFileLog($throwable_, $debugInformation_);
      
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
        if (Eisodos::$parameterHandler->neq('ERRORMAILTO', '')
          && Eisodos::$parameterHandler->neq('ERRORMAILFROM', '')
          && strpos(Eisodos::$parameterHandler->getParam('ERROROUTPUT'), 'Mail') !== false) {
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
      
      if ($this->_cliMode === false
        && strpos(Eisodos::$parameterHandler->getParam('ERROROUTPUT'), 'Screen') !== false
      ) {
        Eisodos::$templateEngine->addToResponse(
          '<html lang="en"><meta content="text/html; charset=utf-8" http-equiv="Content-Type" /><body><pre>' . htmlspecialchars(
            $errorString
          ) . '</pre></body></html>'
        );
        // in case of screen is set, the rendering stops immediately
        Eisodos::$render->finish();
        exit;
      }
      
      if ($this->_cliMode === true
        && strpos(Eisodos::$parameterHandler->getParam('ERROROUTPUT'), 'Screen') !== false
      ) {
        print($errorString);
      }
      
      if ($this->_cliMode === false
        && strpos(Eisodos::$parameterHandler->getParam('ERROROUTPUT'), 'Console') !== false
      ) {
        PC::debug($errorString);
      }
      
      if ($this->_cliMode === true
        && strpos(Eisodos::$parameterHandler->getParam('ERROROUTPUT'), 'Console') !== false
      ) {
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