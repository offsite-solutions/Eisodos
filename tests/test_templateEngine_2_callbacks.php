<?php
  
  use Eisodos\Eisodos;
  use Eisodos\Parsers\CallbackFunctionParser;
  use Eisodos\Parsers\CallbackFunctionShortParser;
  
  require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload
  
  try {
    Eisodos::getInstance()->init(
      [
        __DIR__,
        'test_templateEngine_1'
      ]
    );
    
    Eisodos::$templateEngine->registerParser(new CallbackFunctionParser());
    Eisodos::$templateEngine->registerParser(new CallbackFunctionShortParser());
    require_once('callback1.php'); // default callbacks
    Eisodos::$templateEngine->setDefaultCallbackFunction('callback_default');
    
    Eisodos::$render->start(
      ['configType' => Eisodos::$configLoader::CONFIG_TYPE_INI],
      [],
      [],
      'trace'
    );
    
    print ("* Template Engine - test2 - parser and callback functionality\n");
    print (Eisodos::$templateEngine->getTemplate('test2_callback', ['test2' => 'Y'], false));
    
  } catch (Exception $e) {
    if (!isset(Eisodos::$logger)) {
      die($e->getMessage());
    }
    
    Eisodos::$logger->writeErrorLog($e);
  }