<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
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
  class Eisodos extends Singleton {
    /**
     * @var ParameterHandler $parameterHandler
     */
    public static ParameterHandler $parameterHandler;
    
    /**
     * @var ConfigLoader $configLoader
     */
    public static ConfigLoader $configLoader;
    
    /**
     * @var TemplateEngine $templateEngine
     */
    public static TemplateEngine $templateEngine;
    
    /**
     * @var Translator $translator
     */
    public static Translator $translator;
    
    /**
     * @var Mailer $mailer
     */
    public static Mailer $mailer;
    
    /**
     * @var Render $render
     */
    public static Render $render;
    
    /**
     * @var Logger $logger
     */
    public static Logger $logger;
    
    /**
     * @var DBConnectors $dbconnectors
     */
    public static DBConnectors $dbConnectors;
    
    /**
     * @var string $applicationName Application's name, must be initialized at init
     */
    public static string $applicationName;
    
    /**
     * @var Utils $utils
     */
    public static Utils $utils;
    
    /**
     * @var string $applicationDir Application's dir, must be initialized at init
     */
    public static string $applicationDir;
  
    /**
     * @param array|mixed $options_ =[
     *     'applicationDir',   // Application directory
     *     'applicationName',  // Application name
     *     ] Application options - mandatory
     * @return Eisodos
     */
    public function init(array $options_): Eisodos {
      try {
        self::$utils = Utils::getInstance();
        self::$logger = Logger::getInstance();
        self::$configLoader = ConfigLoader::getInstance();
        self::$parameterHandler = ParameterHandler::getInstance();
        self::$render = Render::getInstance();
        self::$templateEngine = TemplateEngine::getInstance();
        self::$translator = Translator::getInstance();
        self::$mailer = Mailer::getInstance();
        self::$dbConnectors = DBConnectors::getInstance();
      } catch (Exception $e) {
        die('Initialization failure');
      }
  
      self::$applicationDir = $options_[0];
      self::$applicationName = $options_[1];
      self::$parameterHandler->setParam('._applicationName', self::$applicationName, false, false, 'eisodos');
      self::$parameterHandler->setParam('._applicationDir', self::$applicationDir, false, false, 'eisodos');
  
      return $this;
    }
    
  }