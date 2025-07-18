<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
  namespace Eisodos\Abstracts;
  
  use RuntimeException;
  
  abstract class Singleton {
    
    /**
     * The Singleton's instance is stored in a static field. This field is an
     * array, because we'll allow our Singleton to have subclasses. Each item in
     * this array will be an instance of a specific Singleton's subclass.
     *
     * @var static $instances instances
     */
    protected static Singleton $instances;
    
    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::getInstance() instead
     */
    protected function __construct() {
    }
    
    /**
     * Returns the *Singleton* instance of this class
     */
    public static function getInstance() {
      $cls = static::class;
      if (!isset(static::$instances[$cls])) {
        static::$instances[$cls] = new static();
      }
      
      return static::$instances[$cls];
    }
    
    /**
     * prevent from being unserialized (which would create a second instance of it)
     * @throws RuntimeException
     */
    public function __wakeup() {
      throw new RuntimeException('Cannot unserialize a singleton.');
    }
    
    /**
     * prevent the instance from being cloned (which would create a second instance of it)
     */
    protected function __clone() {
    }
    
    /**
     * after getInstance() it must be initialized
     * @var array $options_ Object's options
     */
    abstract protected function init(array $options_);
    
  }