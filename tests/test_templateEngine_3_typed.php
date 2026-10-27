<?php
  
  use Eisodos\Eisodos;
  
  require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload
  
  $failures = 0;
  
  function check(string $name_, string $expected_, string $actual_): void {
    global $failures;
    if ($expected_ === $actual_) {
      print('PASS ' . $name_ . "\n");
    } else {
      $failures++;
      print('FAIL ' . $name_ . "\n  expected: [" . $expected_ . "]\n  actual:   [" . $actual_ . "]\n");
    }
  }
  
  function checkThrows(string $name_, callable $fn_): void {
    global $failures;
    try {
      $result = $fn_();
      $failures++;
      print('FAIL ' . $name_ . "\n  expected RuntimeException, got: [" . $result . "]\n");
    } catch (RuntimeException) {
      print('PASS ' . $name_ . "\n");
    }
  }
  
  function visitor(string $name_, mixed $value_, bool $cookie_ = false): void {
    Eisodos::$parameterHandler->setParam($name_, $value_, false, $cookie_, $cookie_ ? 'cookie' : 'request');
  }
  
  function render(string $template_): string {
    return Eisodos::$templateEngine->parse($template_);
  }
  
  try {
    Eisodos::getInstance()->init(
      [
        __DIR__,
        'test_templateEngine_3_typed'
      ]
    );
    
    Eisodos::$render->start(
      ['configType' => Eisodos::$configLoader::CONFIG_TYPE_INI],
      [],
      [],
      ''
    );
    
    // := and ~= closing semicolon handling
    check('assign without closing semicolon keeps next char', 'X', render("\$a:='x'X"));
    check('default without closing semicolon keeps next char', 'dX', render("\$empty1~='d'X"));
    check('assign with semicolon', 'X', render("\$a:='x';X"));
    
  } catch (Exception $e) {
    if (!isset(Eisodos::$logger)) {
      die($e->getMessage());
    }
    
    Eisodos::$logger->writeErrorLog($e);
    $failures++;
  }
  
  if ($failures > 0) {
    print($failures . " FAILED\n");
    exit(1);
  }
  print("ALL PASSED\n");
