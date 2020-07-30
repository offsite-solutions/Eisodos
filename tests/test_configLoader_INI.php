<?php
  
  use Eisodos\Eisodos;
  
  require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload
  
  try {
    Eisodos::getInstance()->init(
      [
        __DIR__,
        pathinfo(__FILE__)['filename']
      ]
    );
    
    Eisodos::$render->start(
      ['configType' => Eisodos::$configLoader::CONFIG_TYPE_INI],
      [],
      [],
      'trace'
    );
    
    print("* Testing configuration parameters - CONFIG_TYPE_INI" . PHP_EOL);
    print("PreIncluded file [Config].ErrorOutput: " . Eisodos::$parameterHandler->getParam("ErrorOutput") . PHP_EOL);
    print("Main [Config].MainAddress: " . Eisodos::$parameterHandler->getParam("MainAddress") . PHP_EOL);
    
    print("Main [Other].TestValue (must be empty): " . Eisodos::$parameterHandler->getParam("TestValue") . PHP_EOL);
    print("* Run-time loading [Other] section from config file" . PHP_EOL);
    Eisodos::$configLoader->importConfigSection('Other');
    print("Main [Other].TestValue (must not be empty): " . Eisodos::$parameterHandler->getParam("TestValue") . PHP_EOL);
  } catch (Exception $e) {
    if (!isset(Eisodos::$logger)) {
      die($e->getMessage());
    }
    
    Eisodos::$logger->writeErrorLog($e);
  }