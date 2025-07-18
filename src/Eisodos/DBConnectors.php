<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
  namespace Eisodos;
  
  use Eisodos\Abstracts\Singleton;
  use Eisodos\Interfaces\DBConnectorInterface;

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
     * @param int|string|null $index_ Connector's index if needed
     * @return DBConnectorInterface
     */
    public function registerDBConnector(DBConnectorInterface $connector_, $index_ = NULL): DBConnectorInterface {
      if ($index_ === NULL || $index_ === '') {
        $this->_dbConnectors[] =& $connector_;
      } else {
        $this->_dbConnectors[(int)$index_] =& $connector_;
      }
      
      return $connector_;
    }
    
    /**
     * Access one of the DB Connectors object - kept for backward compatibility
     * @param int|string|null $index_ connector object's index
     * @return DBConnectorInterface DB Connector object
     */
    public function db($index_ = NULL): DBConnectorInterface {
      return $this->connector((int)$index_);
    }
    
    /**
     * Access one of the DB Connectors object
     * @param int|string|null $index_ connector object's index, if empty gives back the first connector
     * @return DBConnectorInterface DB Connector object
     */
    public function connector($index_ = NULL): DBConnectorInterface {
      if ($index_ === NULL || $index_ === '') {
        return $this->_dbConnectors[0];
      }
  
      return $this->_dbConnectors[(int)$index_ - 1];
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