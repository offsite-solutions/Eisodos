<?php
  
  use Eisodos\Eisodos;
  
  require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload
  
  $failures = 0;
  
  function check(string $name_, bool $ok_, string $detail_ = ''): void {
    global $failures;
    if ($ok_) {
      print('PASS ' . $name_ . "\n");
    } else {
      $failures++;
      print('FAIL ' . $name_ . ($detail_ === '' ? '' : "\n  " . $detail_) . "\n");
    }
  }
  
  function privateLog(string $method_): string {
    $method = new ReflectionMethod(Eisodos::$logger, $method_);
    
    return $method->invoke(Eisodos::$logger, new RuntimeException('test'), '');
  }
  
  try {
    Eisodos::getInstance()->init(
      [
        __DIR__,
        'test_parameterHandler_secret'
      ]
    );
    
    Eisodos::$render->start(
      ['configType' => Eisodos::$configLoader::CONFIG_TYPE_INI],
      [],
      [],
      ''
    );
    
    $ph = Eisodos::$parameterHandler;
    
    // the value stays usable
    check('config secret readable by name', $ph->getParam('SimplePay_Secret') === 'topsecret-HUF');
    check('config secret usable in template', Eisodos::$templateEngine->parse('k=$simplepay_secret') === 'k=topsecret-HUF');
    
    // the value never gets to the log output
    $text = $ph->params2log();
    check('text log hides secret value', !str_contains($text, 'topsecret'));
    check('text log shows masked row', str_contains($text, '(ro) (secret) simplepay_secret=***'));
    check('text log keeps plain values', str_contains($text, 'visible-plain') && str_contains($text, 'visible-ro'));
    check('file error log hides secret', !str_contains(privateLog('_generateFileLog'), 'topsecret'));
    $html = privateLog('_generateHtmlMailLog');
    check('html error mail hides secret', !str_contains($html, 'topsecret'));
    check('html error mail flags secret', str_contains($html, 'ro secret'));
    
    // set from code
    $ph->setParam('-api_token', 'tok-123', false, false, 'test');
    check('code secret readable', $ph->getParam('api_token') === 'tok-123');
    check('code secret hidden', !str_contains($ph->params2log(), 'tok-123'));
    
    // readonly against the visitor
    $ph->setParam('simplepay_secret', 'evil', false, false, 'request');
    check('visitor can not overwrite secret', $ph->getParam('simplepay_secret') === 'topsecret-HUF');
    
    // the mark sticks when the key is set again without the dash
    $ph->setParam('simplepay_secret', 'topsecret-2', false, false, 'test:config');
    check('overwrite without dash keeps value usable', $ph->getParam('simplepay_secret') === 'topsecret-2');
    check('overwrite without dash stays secret', !str_contains($ph->params2log(), 'topsecret-2'));
    $ph->setParam('simplepay_secret', 'evil2', false, false, 'request');
    check('overwrite without dash stays readonly', $ph->getParam('simplepay_secret') === 'topsecret-2');
    
    // a visitor can not hide a parameter from the log
    $ph->setParam('-visitor_hidden', 'from-visitor', false, false, 'request');
    check('visitor dash is not a secret mark', str_contains($ph->params2log(), '-visitor_hidden=from-visitor'));
    check('visitor dash does not create the bare name', $ph->getParam('visitor_hidden') === '');
    
    // typed parameter debug message
    Eisodos::$logger->setDebugLevels('debug');
    $ph->setParam('-numeric_secret', 'abc-secret', false, false, 'test');
    ob_start(); // CLI mode echoes the debug log
    Eisodos::$templateEngine->parse('$numeric_secret[[numeric,0]]');
    $debug = ob_get_clean() . implode("\n", Eisodos::$logger->getDebugLog());
    check('typed check debug logged', str_contains($debug, 'Invalid parameter value [numeric_secret]=[***]'));
    check('typed check debug hides secret', !str_contains($debug, 'abc-secret'));
    
  } catch (Throwable $e) {
    $failures++;
    print('FAIL exception: ' . $e->getMessage() . "\n");
  }
  
  print($failures === 0 ? "ALL PASS\n" : $failures . " FAILURE(S)\n");
  exit($failures === 0 ? 0 : 1);
