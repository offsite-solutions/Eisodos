<?php
  
  
  namespace Eisodos\Interfaces;
  
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
  const RT_RAW = 0;
  const RT_FIRST_ROW = 1;
  const RT_FIRST_ROW_FIRST_COLUMN = 2;
  const RT_ALL_KEY_VALUE_PAIRS = 3;
  const RT_ALL_FIRST_COLUMN_VALUES = 4;
  const RT_ALL_ROWS = 5;
  const RT_ALL_ROWS_ASSOC = 6;
  
  /**
   * Eisodos DB Connector Interface
   * @package Eisodos
   */
  interface DBConnectorInterface {
    
    /**
     * Connect to a database
     * @param array $connectParameters_ Connect parameters
     * @param bool $persistent_ Persistent flag
     * @return void
     */
    public function connect($connectParameters_ = [], $persistent_ = false): void;
    
    /**
     * Disconnect from database
     */
    public function disconnect(): void;
    
    /**
     * Start transaction
     */
    public function startTransaction();
    
    /**
     * Commit transaction
     */
    public function commit(): void;
    
    /**
     * Rollback transaction
     */
    public function rollback(): void;
    
    /**
     * Is session in transaction mode?
     * @return bool
     */
    public function inTransaction(): bool;
    
    /**
     * Executes simple DML sentence
     * @param string $SQL_ SQL sentence
     * @return mixed
     */
    public function executeDML($SQL_);
    
    /**
     * Execute prepared DML
     * @param string $SQL_ SQL sentence
     * @param array $bindVariables_ Variable names
     * @param array $variableTypes_ Variable types
     * @return mixed
     */
    public function executePreparedDML($SQL_, $bindVariables_ = [], $variableTypes_ = []);
    
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
     * @param mixed $resultSet Result array
     * @param array $getOptions =[
     *     'indexFieldName',   // index Field name is used in RT_ALL_ROWS
     *     ] Additional options
     * @param string $exceptionMessage_
     * @return mixed
     */
    public function query(
      $SQL_,
      $resultTransformation_,
      &$resultSet = NULL,
      $getOptions = [],
      $exceptionMessage_ = ''
    );
    
  }



