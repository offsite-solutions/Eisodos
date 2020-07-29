<?php
  
  
  namespace Eisodos\Interfaces;
  
  use Exception;

  /**
   * Result types
   *   insert into test (a,b,c) values ('d','e','f'),('g','h','j')
   * select a,b,c,d
   * RT_FIRST_ROW gives back the first row in an associative array, where keys are the column names,
   *              values are the columns' values: ['a'=>'d','b'=>'e','c'='f']
   *              deprecated function: getSQL
   * RT_FIRST_ROW_FIRST_COLUMN gives back first row, first column's values as a string: 'd'
   *                           deprecated function: getSQLback
   * RT_ALL_KEY_VALUE_PAIRS gives back an associative array where key is the first column's value,
   *                        value is the second column's value: ['d'=>'e','g'=>'h']
   *                        deprecated function: getSQLtoArray
   * RT_ALL_FIRST_COLUMN_VALUES gives back an indexed array with the first columns' values: ['d','g']
   *                            deprecated function: getSQLtoArray0
   * RT_ALL_ROWS gives back the whole result set in an indexed array [0=>['a'=>'d','b'=>'e','c'='f'],1=>['a'=>'g','b'=>'h','c'='j']]
   *             deprecated function: getSQLtoArrayFull
   * RT_ALL_ROWS_ASSOC gives back the whole result set in an associative array, where key field is one the column's value:
   *                   ['d'=>['a'=>'d','b'=>'e','c'='f'],'e'=>['a'=>'g','b'=>'h','c'='j']]
   *                   deprecated function: getSQLtoArrayFull0
   */
  
  define('RT_RAW', 0);
  define('RT_FIRST_ROW', 1);
  define('RT_FIRST_ROW_FIRST_COLUMN', 2);
  define('RT_ALL_KEY_VALUE_PAIRS', 3);
  define('RT_ALL_FIRST_COLUMN_VALUES', 4);
  define('RT_ALL_ROWS', 5);
  define('RT_ALL_ROWS_ASSOC', 6);
  
  /**
   * Eisodos DB Connector Interface
   * @package Eisodos
   */
  interface DBConnectorInterface {
  
    /**
     * Connection is active and connected
     */
    public function connected(): bool;
    
    /**
     * Connect to a database
     * @param string $databaseConfigSection_ Database connection config section
     * @param array $connectParameters_ Connect parameters
     * @param bool $persistent_ Persistent flag
     * @return void
     */
    public function connect($databaseConfigSection_ = 'Database', $connectParameters_ = [], $persistent_ = false): void;
    
    /**
     * Disconnect from database
     * @param bool $force_ Close persistent connection also
     */
    public function disconnect($force_ = false): void;
    
    /**
     * Start transaction
     * @param mixed $savePoint_ Transaction savepoint
     * @throws Exception
     */
    public function startTransaction($savePoint_ = NULL);
    
    /**
     * Commit transaction
     * @param mixed $savePoint_ Transaction savepoint
     */
    public function commit($savePoint_ = NULL): void;
    
    /**
     * Rollback transaction
     * @param mixed $savePoint_ Transaction savepoint
     */
    public function rollback($savePoint_ = NULL): void;
    
    /**
     * Is session in transaction mode?
     * @return bool
     */
    public function inTransaction(): bool;
    
    /**
     * Executes simple DML sentence
     * @param string $SQL_ SQL sentence
     * @param bool $throwException_
     * @return mixed
     */
    public function executeDML($SQL_, $throwException_ = true);
    
    /**
     * Execute prepared DML
     * @param string $SQL_ SQL sentence
     * @param array $dataTypes_ Data types
     * @param array $data_ Data
     * @param bool $throwException_
     * @return mixed
     */
    public function executePreparedDML($SQL_, $dataTypes_ = [], $data_ = [], $throwException_ = true);
    
    /**
     * Execute stored procedure
     * @param string $procedureName_ Stored procedure name
     * @param array $bindVariables_ Variable names
     * @param array $variableTypes_ Variable Types
     * @return mixed
     */
    public function executeStoredProcedure($procedureName_, $bindVariables_ = [], $variableTypes_ = []);
    
    /**
     * Run SQL query and get its result
     * @param string $SQL_ SQL sentence
     * @param int $resultTransformation_ Result transformation type constant
     * @param mixed $queryResult_ Result array
     * @param array $getOptions_ =[
     *     'indexFieldName',   // index Field name is used in RT_ALL_ROWS
     *     ] Additional options
     * @param string $exceptionMessage_
     * @return mixed
     * @throws Exception
     */
    public function query(
      $SQL_,
      $resultTransformation_,
      &$queryResult_ = NULL,
      $getOptions_ = [],
      $exceptionMessage_ = ''
    );
    
    /**
     * Get native connection object
     * @return mixed
     */
    public function getConnection();
    
  }



