<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
  namespace Eisodos;
  
  use Eisodos\Abstracts\Singleton;
  use Exception;
  use Mail;
  use Mail_mime;
  use PC;
  use PEAR;
  
  final class Mailer extends Singleton {
    
    // Private variables
    
    // Public variables
    
    // Private functions
    
    // Public functions
    
    /**
     * Mailer initializer
     * @inheritDoc
     */
    public function init($options_ = []
    ): void {
    }
    
    /**
     * Send a simple UTF-8 encoded mail
     * @param string $to_
     * @param string $subject_
     * @param string $body_
     * @param string $from_
     * @return bool|string
     */
    public function utf8_html_mail(string $to_, string $subject_, string $body_, string $from_) {
      try {
        $message = new Mail_mime(
          array(
            'text_charset' => 'utf-8',
            'html_charset' => 'utf-8',
            'eol' => "\n"
          )
        );
      
        $message->setHTMLBody($body_);
        $extraHeaders = array(
          'From' => $from_,
          'Subject' => $subject_
        );
        foreach ($extraHeaders as $name => $value) {
          $extraHeaders[$name] = $message->encodeHeader($name, $value, 'utf-8', 'quoted-printable');
        }
        $headers = $message->headers($extraHeaders);
        
        if (Eisodos::$parameterHandler->neq("SMTP.Host", "")) {
          $SMTPOptions = array('host' => Eisodos::$parameterHandler->getParam("SMTP.Host"),
            'auth' => Eisodos::$parameterHandler->neq("SMTP.Username", ""),
            'port' => Eisodos::$parameterHandler->getParam("SMTP.Port"),
            'username' => Eisodos::$parameterHandler->getParam("SMTP.Username"),
            'password' => Eisodos::$parameterHandler->getParam("SMTP.Password"));
          $mail = Mail::factory("smtp", $SMTPOptions);
        } else {
          $mail = Mail::factory("mail");
        }
        
        $mail->send($to_, $headers, $message->get());
        
        if (PEAR::isError($mail)) {
          return $mail->getMessage();
        }
        
      } catch (Exception $e) {
        PC::debug($e->getMessage());
        
        return $e->getMessage();
      }
      
      if (isset($mail)) {
        unset($mail);
      }
      if (isset($message)) {
        unset($message);
      }
      
      return true;
    }
    
    /**
     * Sends an UTF-8 encoded mail with attachments
     * @param string $to_
     * @param string $subject_
     * @param string $body_
     * @param string $from_
     * @param array $filesToAttach_
     */
    public function utf8_html_mail_attachment(string $to_, string $subject_, string $body_, string $from_, $filesToAttach_ = array()): void {
      try {
        $message = new Mail_mime(
          array(
            'text_charset' => 'utf-8',
            'html_charset' => 'utf-8',
            'eol' => "\n"
          )
        );
      
        $message->setHTMLBody($body_);
        foreach ($filesToAttach_ as $f) {
          $message->addAttachment($f);
        }
        $extraHeaders = array(
          'From' => $from_,
          'Subject' => $subject_
        );
        foreach ($extraHeaders as $name => $value) {
          $extraHeaders[$name] = $message->encodeHeader($name, $value, 'utf-8', 'quoted-printable');
        }
        $headers = $message->headers($extraHeaders);
        
        if (Eisodos::$parameterHandler->neq("SMTP.Host", "")) {
          $SMTPOptions = array('host' => Eisodos::$parameterHandler->getParam("SMTP.Host"),
            'auth' => Eisodos::$parameterHandler->neq("SMTP.Username", ""),
            'port' => Eisodos::$parameterHandler->getParam("SMTP.Port"),
            'username' => Eisodos::$parameterHandler->getParam("SMTP.Username"),
            'password' => Eisodos::$parameterHandler->getParam("SMTP.Password"));
          $mail = Mail::factory("smtp", $SMTPOptions);
        } else {
          $mail = Mail::factory("mail");
        }
        
        $mail->send($to_, $headers, $message->get());
        
      } catch (Exception $e) {
        PC::debug($e->getMessage());
      }
      
      if (isset($mail)) {
        unset($mail);
      }
      if (isset($message)) {
        unset($message);
      }
    }
    
    /**
     * Sends mail to series of targets with attachments
     * @param TemplateEngine $templateEngine_ Template Engine for generating body of the mail
     * @param array $to_ Mail targets ["address"=>"param1=value\nparam2=value"]
     * @param string $subject_ Subject
     * @param string $bodyTemplate_ Name of the body template
     * @param string $from_ From address
     * @param array $filesToAttach_ Array of files to attach
     * @param int $batch_loopCount_ Number of mails to send in one loop
     * @param int $batch_waitBetweenLoops_ Wait seconds between loops
     * @param bool $batch_echo_ Echo back the result
     * @param int $batch_skip_ Start processing from index of $toparams_
     * @param bool $testOnly_ Dont sends the mails just prepare them
     * @return string Log
     */
    public function utf8_html_mail_params_attachment_batch(
      TemplateEngine $templateEngine_,
      array $to_,
      string $subject_,
      string $bodyTemplate_,
      string $from_,
      $filesToAttach_ = array(),
      $batch_loopCount_ = 50,
      $batch_waitBetweenLoops_ = 60,
      $batch_echo_ = false,
      $batch_skip_ = 0,
      $testOnly_ = false
    ): string {
      $log = '';
      $num = 0;
      
      foreach ($to_ as $toSend => $paramstext) {
        $num++;
        if ($num < $batch_skip_) {
          $logTXT = "SKIP\t$num\t" . $toSend . "\t\t" . date('H:i:s') . "\n";
          if ($batch_echo_) {
            print($logTXT);
            @ob_flush();
          }
        } else {
          if ($num % $batch_loopCount_ === 0) {
            $logTXT = "WAIT\t\t\t$batch_waitBetweenLoops_ sec\t" . date('H:i:s') . "\n";
            $log .= $logTXT;
            if ($batch_echo_) {
              print($logTXT);
              @ob_flush();
            }
            sleep($batch_waitBetweenLoops_);
          }
          $toSend = trim($toSend);
          
          // creating mail
          $message = new Mail_mime(
            array(
              'text_charset' => 'utf-8',
              'html_charset' => 'utf-8',
              'eol' => "\n"
            )
          );
          
          // adding params
          $params = explode("\n", $paramstext);
          foreach ($params as $line) {
            $pieces = explode('=', $line, 2);
            Eisodos::$parameterHandler->setParam($pieces[0], $pieces[1], false, false, 'eisodos::mailer');
          }
          
          $message->setHTMLBody($templateEngine_->getTemplate($bodyTemplate_, array(), false));
          foreach ($filesToAttach_ as $f) {
            $message->addAttachment($f);
          }
          $body = $message->get();
          $extraHeaders = array(
            'From' => $from_,
            'Subject' => $subject_
          );
          foreach ($extraHeaders as $name => $value) {
            $extraHeaders[$name] = $message->encodeHeader($name, $value, 'utf-8', 'quoted-printable');
          }
          $headers = $message->headers($extraHeaders);
          
          if (Eisodos::$parameterHandler->neq("SMTP.Host", "")) {
            $SMTPOptions = array('host' => Eisodos::$parameterHandler->getParam("SMTP.Host"),
              'auth' => Eisodos::$parameterHandler->neq("SMTP.Username", ""),
              'port' => Eisodos::$parameterHandler->getParam("SMTP.Port"),
              'username' => Eisodos::$parameterHandler->getParam("SMTP.Username"),
              'password' => Eisodos::$parameterHandler->getParam("SMTP.Password"));
            $mail = Mail::factory("smtp", $SMTPOptions);
          } else {
            $mail = Mail::factory("mail");
          }
          
          try {
            if (!$testOnly_) {
              $mail->send($toSend, $headers, $body);
            }
            $logTXT = "OK\t$num\t" . $toSend . "\t\t" . date('H:i:s') . "\n";
            $log .= $logTXT;
            if ($batch_echo_) {
              print($logTXT);
              @ob_flush();
            }
          } catch (Exception $e) {
            $logTXT = "Error\t$num\t" . $toSend . "\t" . $e->getMessage() . "\t" . date('H:i:s') . "\n";
            $log .= $logTXT;
            if ($batch_echo_) {
              print($logTXT);
              @ob_flush();
            }
          }
          
          if (isset($mail)) {
            unset($mail);
          }
          if (isset($message)) {
            unset($message);
          }
        }
      }
      
      return $log;
    }
    
  }