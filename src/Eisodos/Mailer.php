<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
  namespace Eisodos;
  
  use Eisodos\Abstracts\Singleton;
  use Exception;
  use PHPMailer\PHPMailer\PHPMailer;
  use PHPMailer\PHPMailer\Exception as PHPMailerException;
  use RuntimeException;
  
  class Mailer extends Singleton {
    
    // Private variables
    
    // Public variables
    
    // Private functions
    
    /** Writes mail log
     * @param PHPMailerException $e
     * @return void
     */
    private function writeMailLog(PHPMailerException $e): void {
      try {
        if (Eisodos::$parameterHandler->neq('MAILLOG', '')) {
          $file = fopen(Eisodos::$templateEngine->replaceParamInString(Eisodos::$parameterHandler->getParam('MAILLOG')), 'ab+');
          if ($file) {
            fwrite($file, '---- ' . date('Y-m-d H:i:s') . " ----\n");
            fwrite($file, $e->getMessage() . "\n");
            fclose($file);
          }
        } else if (Eisodos::$logger->cliMode) {
          print($e->getMessage() . "\n");
        }
      } catch (Exception) {
      
      }
    }
    
    // Public functions
    
    /**
     * Mailer initializer
     * @inheritDoc
     */
    public function init($options_ = []): void {
    }
    
    /** Simple "name <email@domain>" to [email, name] parser
     * @param string $address_
     * @return array
     */
    public function parseEmailAddress(string $address_): array {
      $address_ = trim($address_);
      if (preg_match('/^\s*("?)([^"<]+)\1\s*<\s*([^>]+)\s*>$/u', $address_, $m)) {
        return [trim($m[3]), trim($m[2])];
      }
      
      // ha nincs név, csak email
      return [$address_, ''];
    }
    
    /**
     * Send a simple UTF-8 encoded mail, addresses can be in name <email@domain> format
     * @param string $to_ To, can be multiple address separated by , or ;,
     * @param string $subject_ Subject of the mail
     * @param string $body_ HTML body
     * @param string $from_ From address
     * @param array $filesToAttach_ Attachments
     * @param array $fileStringsToAttach_ Files as string in format [['content'=>'XXX', 'filename'=>'FFF']]
     * @param string $cc_ CC
     * @param string $bcc_ BCC
     * @param string $replyto_ Reply to address
     * @return bool
     */
    public function sendMail(string $to_,
                             string $subject_,
                             string $body_,
                             string $from_,
                             array  $filesToAttach_ = [],
                             array  $fileStringsToAttach_ = [],
                             string $cc_ = '',
                             string $bcc_ = '',
                             string $replyto_ = ''): bool {
      
      try {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        
        if (Eisodos::$parameterHandler->neq('SMTP.Host', '')) {
          $mail->isSMTP();
          $mail->Host = Eisodos::$parameterHandler->getParam('SMTP.Host');
          $mail->Port = (int)(Eisodos::$parameterHandler->getParam('SMTP.Port') ?: 587);
          $mail->SMTPAuth = Eisodos::$parameterHandler->neq('SMTP.Username', '');
          if ($mail->SMTPAuth) {
            $mail->Username = Eisodos::$parameterHandler->getParam('SMTP.Username');
            $mail->Password = Eisodos::$parameterHandler->getParam('SMTP.Password');
          }
          // opcionális: SMTPSecure (tls/ssl) paraméterből
          $secure = Eisodos::$parameterHandler->getParam('SMTP.Secure');
          if ($secure !== '') {
            $mail->SMTPSecure = $secure; // 'tls' vagy 'ssl'
          }
          $mail->SMTPAutoTLS = true; // STARTTLS ha elérhető
        } else {
          $mail->isMail(); // /usr/sbin/sendmail helyett sima mail() wrapper
        }
        
        // Feladó beállítása
        [$fromEmail, $fromName] = $this->parseEmailAddress($from_);
        $mail->setFrom($fromEmail, $fromName ?: '');
        
        // Címzettek – több cím is jöhet vesszővel vagy pontosvesszővel elválasztva
        foreach (preg_split('/[;,]+/', $to_) as $addr) {
          $addr = trim($addr);
          if ($addr === '') {
            continue;
          }
          [$toEmail, $toName] = $this->parseEmailAddress($addr);
          $mail->addAddress($toEmail, $toName ?: '');
        }
        
        if ($cc_ !== '') {
          foreach (preg_split('/[;,]+/', $cc_) as $addr) {
            $addr = trim($addr);
            if ($addr === '') {
              continue;
            }
            [$toEmail, $toName] = $this->parseEmailAddress($addr);
            $mail->addCC($toEmail, $toName ?: '');
          }
        }
        
        if ($bcc_ !== '') {
          foreach (preg_split('/[;,]+/', $bcc_) as $addr) {
            $addr = trim($addr);
            if ($addr === '') {
              continue;
            }
            [$toEmail, $toName] = $this->parseEmailAddress($addr);
            $mail->addBCC($toEmail, $toName ?: '');
          }
        }
        
        if ($replyto_ !== '') {
          [$toEmail, $toName] = $this->parseEmailAddress(trim($replyto_));
          $mail->addReplyTo($toEmail, $toName ?: '');
        }
        
        // Tárgy + törzs
        $mail->Subject = $subject_;
        $mail->isHTML();
        $mail->Body = $body_;
        
        foreach ($filesToAttach_ as $f) {
          $mail->addAttachment($f);
        }
        
        foreach ($fileStringsToAttach_ as $f) {
          $mail->addStringAttachment($f['content'], $f['filename']);
        }
        
        $mail->send();
        
        return true;
        
      } catch (PHPMailerException $e) {
        $this->writeMailLog($e);
        
        return false;
      }
    }
    
    /**
     * Sends an UTF-8 encoded mail with attachments
     * @param string $to_
     * @param string $subject_
     * @param string $body_
     * @param string $from_
     * @param array $filesToAttach_
     * @param array $fileStringsToAttach_
     * @param string $cc_
     * @param string $bcc_
     * @param string $replyto_
     * @return bool
     */
    public function utf8_html_mail_attachment(string $to_,
                                              string $subject_,
                                              string $body_,
                                              string $from_,
                                              array  $filesToAttach_ = [],
                                              array  $fileStringsToAttach_ = [],
                                              string $cc_ = '',
                                              string $bcc_ = '',
                                              string $replyto_ = ''): bool {
      return $this->sendMail($to_, $subject_, $body_, $from_, $filesToAttach_, $fileStringsToAttach_, $cc_, $bcc_, $replyto_);
    }
    
    /**
     * Sends mail to series of targets with attachments
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
    public function sendBatchMail(
      array  $to_,
      string $subject_,
      string $bodyTemplate_,
      string $from_,
      array  $filesToAttach_ = array(),
      array  $fileStringsToAttach_ = [],
      string $cc_ = '',
      string $bcc_ = '',
      string $replyto_ = '',
      int    $batch_loopCount_ = 50,
      int    $batch_waitBetweenLoops_ = 60,
      bool   $batch_echo_ = false,
      int    $batch_skip_ = 0,
      bool   $testOnly_ = false
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
          
          // adding params
          $params = explode("\n", $paramstext);
          foreach ($params as $line) {
            $pieces = explode('=', $line, 2);
            Eisodos::$parameterHandler->setParam($pieces[0], $pieces[1], false, false, 'eisodos::mailer');
          }
          // generate html body
          $body = Eisodos::$templateEngine->getTemplate($bodyTemplate_, array(), false);
          
          try {
            if (!$testOnly_ && !$this->sendMail($toSend, $subject_, $body, $from_, $filesToAttach_, $fileStringsToAttach_, $cc_, $bcc_, $replyto_)) {
              throw new RuntimeException('error sending mail');
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
        }
      }
      
      return $log;
    }
    
  }