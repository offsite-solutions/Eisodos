<?php


namespace Eisodos;

use Eisodos\Abstracts\Singleton;
use Exception;

/**
 * Eisodos singleton class, usage:
 *
 * Eisodos::getInstance()->init([__DIR__,pathinfo(__FILE__)['filename']]);
 * Eisodos::$render->start(['configType' => Eisodos::$configLoader::CONFIG_TYPE_INI],[],[],'trace');
 *
 * @package Eisodos
 */
class Eisodos extends Singleton
{
    /**
     * @var ParameterHandler $parameterHandler
     */
    public static $parameterHandler;

    /**
     * @var ConfigLoader $configLoader
     */
    public static $configLoader;

    /**
     * @var TemplateEngine $templateEngine
     */
    public static $templateEngine;

    /**
     * @var Translator $translator
     */
    public static $translator;

    /**
     * @var Mailer $mailer
     */
    public static $mailer;

    /**
     * @var Render $render
     */
    public static $render;

    /**
     * @var Logger $logger
     */
    public static $logger;
    
    /**
     * @var DBConnectors $dbconnectors
     */
    public static $dbconnectors;

    /**
     * @var string $applicationName Application's name, must be initialized at init
     */
    public static $applicationName;

    /**
     * @var Utils $utils
     */
    public static $utils;

    /**
     * @var string $applicationDir Application's dir, must be initialized at init
     */
    public static $applicationDir;

    /**
     * @param array $applicationOptions_ =[
     *     'applicationDir',   // Application directory
     *     'applicationName',  // Application name
     *     ] Application options - mandatory
     * @return Eisodos
     */
    public function init($applicationOptions_): Eisodos
    {
        try {
            self::$utils = Utils::getInstance();
            self::$logger = Logger::getInstance();
            self::$configLoader = ConfigLoader::getInstance();
            self::$parameterHandler = ParameterHandler::getInstance();
            self::$render = Render::getInstance();
            self::$templateEngine = TemplateEngine::getInstance();
            self::$translator = Translator::getInstance();
            self::$mailer = Mailer::getInstance();
            self::$dbconnectors = DBConnectors::getInstance();
        } catch (Exception $e) {
            die('Initialization failure');
        }

        self::$applicationDir = $applicationOptions_[0];
        self::$applicationName = $applicationOptions_[1];
        self::$parameterHandler->setParam('_applicationName', self::$applicationName);
        self::$parameterHandler->setParam('_applicationDir', self::$applicationDir);

        return $this;
    }

}