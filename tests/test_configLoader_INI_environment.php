<?php

use Eisodos\Eisodos;

require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload

try {
    $c = Eisodos::getInstance()->init(
        [
            __DIR__,
            pathinfo(__FILE__)['filename']
        ]
    );

    $c::$render->start(
        [
            'configType' => Eisodos::$configLoader::CONFIG_TYPE_INI,
            'environment' => 'test'
        ],
        [],
        [],
        'trace'
    );

    print("* Testing configuration parameters - CONFIG_TYPE_INI, but setting environment to 'test'" . PHP_EOL);
    print("PreIncluded file [Config].ErrorOutput: " . $c::$parameterHandler->getParam("ErrorOutput") . PHP_EOL);
    print("Main [Config].MainAddress: " . $c::$parameterHandler->getParam("MainAddress") . PHP_EOL);

    print("Main [Other].TestValue (must be empty): " . $c::$parameterHandler->getParam("TestValue") . PHP_EOL);
    print("* Run-time loading [Other] section from config file" . PHP_EOL);
    $c::$configLoader->importConfigSection('Other');
    print("Main [Other].TestValue (must not be empty): " . $c::$parameterHandler->getParam("TestValue") . PHP_EOL);
} catch (Exception $e) {
    if (!isset($c)) {
        die($e->getMessage());
    }

    $c::$logger->writeErrorLog($e);
}