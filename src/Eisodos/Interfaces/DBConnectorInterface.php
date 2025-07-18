<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
  namespace Eisodos\Interfaces;
  
  use Exception;
  use RuntimeException;
  
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
    public function connect(string $databaseConfigSection_ = 'Database', array $connectParameters_ = [], bool $persistent_ = false): void;
    
    /**
     * Disconnect from database
     * @param bool $force_ Close persistent connection also
     */
    public function disconnect(bool $force_ = false): void;
    
    /**
     * Start transaction
     * @param mixed $savePoint_ Transaction savepoint
     * @throws Exception
     */
    public function startTransaction($savePoint_ = NULL);
    
    /**
     * Commit transaction
     */
    public function commit(): void;
    
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
     * @return int|bool Affected rows - false on error in case of exception didn't throw
     */
    public function executeDML(string $SQL_, bool $throwException_ = true);
    
    /**
     * Execute prepared DML
     * @param string $SQL_ SQL sentence
     * @param array $dataTypes_ Data types
     * @param array $data_ Data
     * @param bool $throwException_
     * @return int|bool Affected rows - false on error in case of exception didn't throw
     */
    public function executePreparedDML(string $SQL_, array $dataTypes_ = [], array &$data_ = [], bool $throwException_ = true);
    
    /**
     * Execute prepared DML
     * @param string $SQL_ SQL sentence
     * @param array $boundVariables_ Parameter array reference
     * @param bool $throwException_
     * @return int|bool Affected rows - false on error in case of exception didn't throw
     * @throws RuntimeException
     */
    public function executePreparedDML2(string $SQL_, array $boundVariables_, bool $throwException_ = true);
    
    /**
     * Preparing stored procedure parameter for binding
     * @param array $boundVariables_ Parameter array reference
     * @param string $variableName_ Parameter name
     * @param string $dataType_ Datatype
     * @param string $value_ Value
     * @param string $inOut_ Direction
     */
    public function bind(array &$boundVariables_, string $variableName_, string $dataType_, string $value_, string $inOut_ = 'IN');
    
    /**
     * Preparing stored procedure parameter for binding from Eisodos parameter
     * @param array $boundVariables_ Parameter array reference
     * @param string $parameterName_ Parameter name
     * @param string $dataType_ Datatype
     */
    public function bindParam(array &$boundVariables_, string $parameterName_, string $dataType_);
    
    /**
     * Executes stored procedure
     * @param string $procedureName_ Procedure name
     * @param array $inputVariables_ Parameter array
     * @param array $resultVariables_ Result array
     * @param bool $throwException_ Throw exception in case of error
     * @param int $case_ Result array key transformation
     * @return bool
     */
    public function executeStoredProcedure(string $procedureName_, array $inputVariables_, array &$resultVariables_, bool $throwException_ = true, int $case_ = CASE_UPPER): bool;
    
    /**
     * Run SQL query and get its result
     * @param int $resultTransformation_ Result transformation type constant
     * @param string $SQL_ SQL sentence
     * @param mixed $queryResult_ Result array
     * @param array $getOptions_ =[
     *     'indexFieldName',   // index Field name is used in RT_ALL_ROWS
     *     ] Additional options
     * @param string $exceptionMessage_
     * @return mixed
     * @throws Exception
     */
    public function query(
      int    $resultTransformation_,
      string $SQL_,
      array  &$queryResult_ = NULL,
      array  $getOptions_ = [],
      string $exceptionMessage_ = ''
    );
    
    /**
     * Gives back the last query's column names
     * @return array
     */
    public function getLastQueryColumns(): array;
    
    /**
     * Gives back the last query's total rows
     * @return integer
     */
    public function getLastQueryTotalRows(): int;
    
    /**
     * Get native connection object
     * @return mixed
     */
    public function getConnection();
    
    /**
     * Converts value to SQL keyword if empty
     * @param mixed $value_ Value
     * @param bool $isString_ Value is string
     * @param int $maxLength_ Maximum length of column
     * @param string $exception_ Throw exception in case of error
     * @param bool $withComma_ add comma to end of text
     * @param string $keyword_ SQL keyword
     * @return string
     * @throws RuntimeException
     */
    public function emptySQLField($value_, bool $isString_ = true, int $maxLength_ = 0, string $exception_ = '', bool $withComma_ = false, string $keyword_ = 'NULL'): string;
    
    /**
     * Converts value to NULL if empty
     * @param mixed $value_ Value
     * @param bool $isString_ Value is string
     * @param int $maxLength_ Maximum length of column
     * @param string $exception_ Throw exception in case of error
     * @param bool $withComma_ add comma to end of text
     * @return string
     * @throws RuntimeException
     */
    public function nullStr($value_, bool $isString_ = true, int $maxLength_ = 0, string $exception_ = "", bool $withComma_ = false): string;
    
    /**
     * Converts value to DEFAULT if empty
     * @param mixed $value_ Value
     * @param bool $isString_ Value is string
     * @param int $maxLength_ Maximum length of column
     * @param string $exception_ Throw exception in case of error
     * @param bool $withComma_ add comma to end of text
     * @return string
     * @throws RuntimeException
     */
    public function defaultStr($value_, bool $isString_ = true, int $maxLength_ = 0, string $exception_ = "", bool $withComma_ = false): string;
    
    /**
     * Converts parameter value to NULL if empty
     * @param string $parameterName_ Value
     * @param bool $isString_ Value is string
     * @param int $maxLength_ Maximum length of column
     * @param string $exception_ Throw exception in case of error
     * @param bool $withComma_ add comma to end of text
     * @return string
     * @throws RuntimeException
     */
    public function nullStrParam(string $parameterName_, bool $isString_ = true, int $maxLength_ = 0, string $exception_ = "", bool $withComma_ = false): string;
    
    /**
     * Converts parameter value to DEFAULT if empty
     * @param string $parameterName_ Value
     * @param bool $isString_ Value is string
     * @param int $maxLength_ Maximum length of column
     * @param string $exception_ Throw exception in case of error
     * @param bool $withComma_ add comma to end of text
     * @return string
     * @throws RuntimeException
     */
    public function defaultStrParam(string $parameterName_, bool $isString_ = true, int $maxLength_ = 0, string $exception_ = "", bool $withComma_ = false): string;
    
  }



