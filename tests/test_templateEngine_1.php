<?php

use Eisodos\Eisodos;

require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using Composer autoload

try {
    Eisodos::getInstance()->init(
        [
            __DIR__,
            'test_templateEngine_1'
        ]
    );

    Eisodos::$render->start(
        ['configType' => Eisodos::$configLoader::CONFIG_TYPE_INI],
        [],
        [],
        'trace'
    );

    print ("* Template - test1 \n");
    print (Eisodos::$templateEngine->getTemplate('test1',[],false));

} catch (Exception $e) {
    if (!isset(Eisodos::$logger)) {
        die($e->getMessage());
    }

    Eisodos::$logger->writeErrorLog($e);
}