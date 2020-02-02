<?php

namespace Eisodos;

require_once('Utils.php');

use DateTime;
use Eisodos\Abstracts\Singleton;
use Exception;
use PC;
use Psr\Log\LoggerInterface;

final class Logger extends Singleton implements LoggerInterface
{

    // Private variables

    /**
     * @var bool $_cliMode script running in CLI mode
     */
    private $_cliMode = false;

    /**
     * @var bool|null DebugEnabled
     */
    private $debugEnabled = null;

    /**
     * @var int
     */
    private $traceStep = 0;

    // Public variables

    /**
     * @var string $debugLevel Debug level set
     */
    public $debugLevel = '';

    // Private functions

    /**
     * Generates log file
     * @param Exception|null $exception_
     * @param string $debugInformation_
     * @return string
     */
    private function _generateFileLog($exception_, $debugInformation_ = '')
    {
        $st = "\n";
        $st .= "---------- " . Eisodos::$applicationName . " ----------\n";
        $st .= date("Y.m.d. H:i:s") . "\n";
        if ($exception_) {
            $st .= $exception_->getMessage() . "\n";
            $st .= $exception_->getFile() . " at line " . $exception_->getLine() . "\n";
            $st .= $exception_->getTraceAsString() . "\n";
        }
        $st .= "     ----- Extended Error -----\n";
        $st .= Eisodos::$utils->safe_array_value($_POST, "__EISODOS_extendedError") . "\n";
        $st .= "     ----- URL -----\n";
        $st .= $_SERVER["REQUEST_URI"] . "\n";
        $st .= "     ----- Parameters -----\n";
        $st .= Eisodos::$parameterHandler->params2log();
        if ($debugInformation_ != "") {
            $st .= "     ----- Extended info -----\n";
            $st .= $debugInformation_;
        }

        return $st;
    }

    // Public functions

    /**
     * Logger initialization
     * @param mixed $debugLevel_ Comma separated list of levels, ex: critical,error,emergency,debug
     */
    public function init($debugLevel_)
    {
        $this->debugLevel = $debugLevel_;
        $this->_cliMode = (php_sapi_name() == "cli");
    }

    /**
     * Writes error log to file, mail, screen, callback
     * target can be set in config by ERROROUTPUT=file,mail,screen,"@callback"
     * In CLI Mode screen echos the log to the standard output
     * @param Exception $exception_ Exception object
     * @param string $debugInformation_ Extra debug information
     * @param array $extraMails_ Send the debug to the mail address specified
     * @return void
     */
    public function writeErrorLog($exception_, $debugInformation_ = "", $extraMails_ = array())
    {
        try {
            if (strpos(Eisodos::$parameterHandler->getParam('ERROROUTPUT'), '@') !== false) {
                $erroroutput = Eisodos::$parameterHandler->getParam('ERROROUTPUT') . ',';
                $error_function = substr($erroroutput, strpos($erroroutput, '@') + 1);
                $error_function = substr($error_function, 0, strpos($error_function, ',') - 1);
                call_user_func(
                    $error_function,
                    $this,
                    array(
                        'Message' => $exception_->getMessage() . "\n" . Eisodos::$utils->safe_array_value(
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
                Eisodos::$parameterHandler->getParam('ERROROUTPUT') . ',Mail'
            );
        }

        $errorString = $this->_generateFileLog($exception_, $debugInformation_);

        try {
            if (strpos(Eisodos::$parameterHandler->getParam('ERROROUTPUT'), 'File') !== false) {
                $logfile = fopen(
                    Eisodos::$templateEngine->replaceParamInString(Eisodos::$parameterHandler->getParam('ERRORLOG')),
                    'a'
                ) or die("can't open log file");
                fwrite($logfile, $errorString);
                fclose($logfile);
            }
        } catch (Exception $ex) {
            Eisodos::$parameterHandler->setParam(
                'ERROROUTPUT',
                Eisodos::$parameterHandler->getParam('ERROROUTPUT') . ',Mail'
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
    public function log($text_, $debugLevel_ = 'debug', $sender_ = null)
    {
        if ($this->debugEnabled === null) {
            $d = explode(',', $this->debugLevel);
            $this->debugEnabled = (in_array('trace', $d) or in_array($debugLevel_, $d));
        }

        if ($this->debugEnabled) {
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

            if (array_key_exists(2, $dbt)) {
                $functionName = Eisodos::$utils->safe_array_value($dbt[2], 'function');
                $className = Eisodos::$utils->safe_array_value($dbt[2], 'class');
            } else {
                $functionName = '';
                $className = get_class($sender_);
            }

            $now = DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));

            $debugText = str_pad(
                    '[' . $now->format('Y-m-d H:i:s.u') . '] [' . mb_strtoupper($debugLevel_) . '] ' .
                    '[' . $className . ']' .
                    ($functionName == '' ? '' : ' [' . $functionName . ']')
                    ,
                    100
                ) . '|' . str_repeat(
                    ' ',
                    ($text_ == 'BEGIN' ? (2 * $this->traceStep++) : ($text_ == 'END' ? (2 * --$this->traceStep) : 2 * $this->traceStep))
                ) . $text_;

            if ($this->_cliMode) {
                echo($debugText . PHP_EOL);
            } else {
                PC::debug($debugText);
            }
        }
    }

    /**
     * Log a critical message
     *
     * @param string $text_ Message to be displayed in the log message
     * @param object $sender_ Sender object. When specified debug will display the name of the sender object. Defaults to `null`.
     */
    public function critical($text_, $sender_ = null)
    {
        self::log($text_, 'critical', $sender_);
    }

    /**
     * Log an error message
     *
     * @param string $text_ Message to be displayed in the log message
     * @param object|null $sender_ Sender object. When specified debug will display the name of the sender object. Defaults to `null`.
     */
    public function error($text_, $sender_ = null)
    {
        self::log($text_, 'error', $sender_);
    }

    /**
     * Log an info message
     *
     * @param string $text_ Message to be displayed in the log message
     * @param object|null $sender_ Sender object. When specified debug will display the name of the sender object. Defaults to `null`.
     */
    public function info($text_, $sender_ = null)
    {
        self::log($text_, 'info', $sender_);
    }

    /**
     * Log a warning message
     *
     * @param string $text_ Message to be displayed in the log message
     * @param object|null $sender_ Sender object. When specified debug will display the name of the sender object. Defaults to `null`.
     */
    public function warning($text_, $sender_ = null)
    {
        self::log($text_, 'warning', $sender_);
    }

    /**
     * Log a debug message
     *
     * @param string $text_ Message to be displayed in the log message
     * @param object|null $sender_ Sender object. When specified debug will display the name of the sender object. Defaults to `null`.
     */
    public function debug($text_, $sender_ = null)
    {
        self::log($text_, 'debug', $sender_);
    }

    /**
     * Log a trace message
     *
     * @param string $text_ Message to be displayed in the log message
     * @param object|null $sender_ Sender object. When specified debug will display the name of the sender object. Defaults to `null`.
     */
    public function trace($text_, $sender_ = null)
    {
        self::log($text_, 'trace', $sender_);
    }

    /**
     * Log an alert message
     *
     * @param string $text_ Message to be displayed in the log message
     * @param object|null $sender_ Sender object. When specified debug will display the name of the sender object. Defaults to `null`.
     */
    public function alert($text_, $sender_ = null)
    {
        self::log($text_, 'alert', $sender_);
    }

    /**
     * Log an emergency message
     *
     * @param string $text_ Message to be displayed in the log message
     * @param object|null $sender_ Sender object. When specified debug will display the name of the sender object. Defaults to `null`.
     */
    public function emergency($text_, $sender_ = null)
    {
        self::log($text_, 'emergency', $sender_);
    }

    /**
     * Log a notice message
     *
     * @param string $text_ Message to be displayed in the log message
     * @param object|null $sender_ Sender object. When specified debug will display the name of the sender object. Defaults to `null`.
     */
    public function notice($text_, $sender_ = null)
    {
        self::log($text_, 'notice', $sender_);
    }

}