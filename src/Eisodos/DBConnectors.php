<?php /** @noinspection ALL */
  
  namespace Eisodos;
  
  use Eisodos\Abstracts\Singleton;
  use Eisodos\Interfaces\DBConnectorInterface;
  use RuntimeException;
  
  /**
   * Eisodos DB Connectors singleton class, usage:
   *
   * @package Eisodos
   */
  class DBConnectors extends Singleton {
    
    private array $_dbConnectors = [];
    
    /**
     * @inheritDoc
     */
    public function init($options_ = NULL): DBConnectors {
      return $this;
    }
    
    /**
     * @param $connector_ DBConnectorInterface DB Connector Interface object
     * @param string $key_ Connector's index if needed
     * @return DBConnectorInterface
     * @throws RuntimeException
     */
    public function registerDBConnector(DBConnectorInterface $connector_, string $key_ = '0'): DBConnectorInterface {
      if ($key_ === '') {
        $key_ = '0';
      }
      
      if (array_key_exists($key_, $this->_dbConnectors)) {
        throw new RuntimeException('DB Connector index already exists: ' . $key_);
      }
      
      $this->_dbConnectors[$key_] =& $connector_;
      
      return $connector_;
    }
    
    /**
     * Access one of the DB Connectors object - kept for backward compatibility
     * @param string $key_ connector object's index
     * @return DBConnectorInterface DB Connector object
     * @throws RuntimeException
     */
    public function db(string $key_ = '0'): DBConnectorInterface {
      return $this->connector($key_);
    }
    
    /**
     * Access one of the DB Connectors object
     * @param string $key_ connector object's index, if empty gives back the first connector
     * @return DBConnectorInterface DB Connector object
     * @throws RuntimeException
     */
    public function connector(string $key_ = '0'): DBConnectorInterface {
      if ($key_ === '') {
        $key_ = '0';
      }
      
      if (!array_key_exists($key_, $this->_dbConnectors)) {
        throw new RuntimeException('DB Connector index not exists: ' . $key_);
      }
      
      return $this->_dbConnectors[$key_];
    }
    
    /**
     * Disconnect all database connection
     */
    public function __destruct() {
      foreach ($this->_dbConnectors as $dbConnector) {
        $dbConnector->disconnect();
      }
    }
    
  }