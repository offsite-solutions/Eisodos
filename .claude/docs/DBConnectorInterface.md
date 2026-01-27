# DBConnectorInterface

Interface for database connector implementations.

**Namespace:** `Eisodos\Interfaces`
**Source:** `src/Eisodos/Interfaces/DBConnectorInterface.php`

## Overview

The `DBConnectorInterface` defines the contract for database connectors in the Eisodos framework. Implementations of this interface provide database-agnostic access to various database systems (MySQL, PostgreSQL, Oracle, etc.).

## Result Type Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `RT_RAW` | 0 | Raw result (implementation-specific) |
| `RT_FIRST_ROW` | 1 | First row as associative array |
| `RT_FIRST_ROW_FIRST_COLUMN` | 2 | First column value of first row |
| `RT_ALL_KEY_VALUE_PAIRS` | 3 | Key-value pairs from first two columns |
| `RT_ALL_FIRST_COLUMN_VALUES` | 4 | Array of first column values |
| `RT_ALL_ROWS` | 5 | All rows as indexed array |
| `RT_ALL_ROWS_ASSOC` | 6 | All rows keyed by specified column |
| `RT_NO_ROWS` | 7 | Don't fetch results |

### Result Type Examples

Given this data:
```sql
SELECT a, b, c FROM test;
-- Returns: ('d','e','f'), ('g','h','j')
```

| Type | Result |
|------|--------|
| `RT_FIRST_ROW` | `['a'=>'d', 'b'=>'e', 'c'=>'f']` |
| `RT_FIRST_ROW_FIRST_COLUMN` | `'d'` |
| `RT_ALL_KEY_VALUE_PAIRS` | `['d'=>'e', 'g'=>'h']` |
| `RT_ALL_FIRST_COLUMN_VALUES` | `['d', 'g']` |
| `RT_ALL_ROWS` | `[0=>['a'=>'d',...], 1=>['a'=>'g',...]]` |
| `RT_ALL_ROWS_ASSOC` | `['d'=>['a'=>'d',...], 'g'=>['a'=>'g',...]]` |

## Connection Methods

### connected(): bool

Checks if connection is active.

### connect(string $databaseConfigSection_ = 'Database', array $connectParameters_ = [], bool $persistent_ = false): void

Establishes database connection.

**Parameters:**
- `$databaseConfigSection_` - Config section name for connection parameters
- `$connectParameters_` - Additional connection parameters
- `$persistent_` - Use persistent connection

### disconnect(bool $force_ = false): void

Closes the database connection.

**Parameters:**
- `$force_` - Force close persistent connections

### getConnection(): mixed

Returns the native database connection object.

## Transaction Methods

### startTransaction(string|null $savePoint_ = null): void

Starts a transaction or creates a savepoint.

### commit(): void

Commits the current transaction.

### rollback(string|null $savePoint_ = null): void

Rolls back transaction to savepoint or beginning.

### inTransaction(): bool

Checks if currently in a transaction.

## Query Methods

### query(int $resultTransformation_, string $SQL_, ?array &$queryResult_ = null, ?array $getOptions_ = [], ?string $exceptionMessage_ = ''): mixed

Executes a SELECT query and returns results.

**Parameters:**
- `$resultTransformation_` - Result type constant (RT_*)
- `$SQL_` - SQL query string
- `$queryResult_` - Reference to store results
- `$getOptions_` - Additional options (e.g., `['indexFieldName' => 'id']`)
- `$exceptionMessage_` - Custom exception message

**Returns:** Query results based on transformation type

**Example:**
```php
// Get single value
$count = $db->query(RT_FIRST_ROW_FIRST_COLUMN, 'SELECT COUNT(*) FROM users');

// Get all rows
$users = [];
$db->query(RT_ALL_ROWS, 'SELECT * FROM users WHERE active = 1', $users);

// Get rows keyed by ID
$usersById = [];
$db->query(RT_ALL_ROWS_ASSOC, 'SELECT * FROM users', $usersById, ['indexFieldName' => 'id']);
```

### getLastQueryColumns(): array

Returns column names from the last query.

### getLastQueryTotalRows(): int

Returns total row count from the last query.

## DML Methods

### executeDML(string $SQL_, bool $throwException_ = true): int|bool

Executes INSERT, UPDATE, or DELETE statement.

**Parameters:**
- `$SQL_` - SQL statement
- `$throwException_` - Throw exception on error

**Returns:** Affected row count or `false` on error

**Example:**
```php
$affected = $db->executeDML("UPDATE users SET status = 'active' WHERE id = 123");
```

### executePreparedDML(string $SQL_, array $dataTypes_ = [], array &$data_ = [], bool $throwException_ = true): int|bool

Executes prepared DML statement with typed parameters.

**Example:**
```php
$db->executePreparedDML(
    'INSERT INTO users (name, email) VALUES (?, ?)',
    ['s', 's'],
    ['John', 'john@example.com']
);
```

### executePreparedDML2(string $SQL_, array $boundVariables_, bool $throwException_ = true): int|bool

Executes prepared DML with named bound variables.

## Stored Procedure Methods

### bind(array &$boundVariables_, string $variableName_, string $dataType_, string $value_, string $inOut_ = 'IN'): void

Prepares a parameter for binding.

**Parameters:**
- `$boundVariables_` - Reference to variables array
- `$variableName_` - Parameter name
- `$dataType_` - Data type
- `$value_` - Parameter value
- `$inOut_` - Direction ('IN', 'OUT', 'INOUT')

### bindParam(array &$boundVariables_, string $parameterName_, string $dataType_): void

Binds an Eisodos parameter for stored procedure.

### executeStoredProcedure(string $procedureName_, array $inputVariables_, array &$resultVariables_, bool $throwException_ = true, int $case_ = CASE_UPPER): bool

Executes a stored procedure.

**Example:**
```php
$params = [];
$db->bind($params, ':user_id', 'int', '123', 'IN');
$db->bind($params, ':result', 'string', '', 'OUT');

$results = [];
$db->executeStoredProcedure('get_user_name', $params, $results);
echo $results[':result'];
```

## SQL Helper Methods

### emptySQLField(mixed $value_, bool $isString_ = true, int $maxLength_ = 0, string $exception_ = '', bool $withComma_ = false, string $keyword_ = 'NULL'): string

Converts empty value to SQL keyword.

### nullStr(mixed $value_, bool $isString_ = true, int $maxLength_ = 0, string $exception_ = '', bool $withComma_ = false): string

Converts empty value to NULL.

**Example:**
```php
$sql = "INSERT INTO users (name, bio) VALUES (
    {$db->nullStr($name)},
    {$db->nullStr($bio, true, 1000)}
)";
// If $bio is empty: INSERT INTO users (name, bio) VALUES ('John', NULL)
```

### defaultStr(mixed $value_, bool $isString_ = true, int $maxLength_ = 0, string $exception_ = '', bool $withComma_ = false): string

Converts empty value to DEFAULT.

### toList(mixed $value_, bool $isString_ = true, int $maxLength_ = 0, string $exception_ = '', bool $withComma_ = false): string

Converts comma-separated values to SQL list.

**Example:**
```php
$ids = '1,2,3';
$sql = "SELECT * FROM users WHERE id IN ({$db->toList($ids, false)})";
// Result: SELECT * FROM users WHERE id IN (1, 2, 3)
```

### nullStrParam(string $parameterName_, ...): string

Converts Eisodos parameter value to NULL if empty.

### defaultStrParam(string $parameterName_, ...): string

Converts Eisodos parameter value to DEFAULT if empty.

### DBSyntax(): string

Returns the database syntax identifier (e.g., 'mysql', 'oracle').

## Implementation Example

```php
<?php
namespace MyApp;

use Eisodos\Interfaces\DBConnectorInterface;
use Eisodos\Eisodos;
use PDO;

class MySQLConnector implements DBConnectorInterface {
    private ?PDO $connection = null;

    public function connected(): bool {
        return $this->connection !== null;
    }

    public function connect(string $databaseConfigSection_ = 'Database', array $connectParameters_ = [], bool $persistent_ = false): void {
        $host = Eisodos::$parameterHandler->getParam($databaseConfigSection_ . '.hostname');
        $db = Eisodos::$parameterHandler->getParam($databaseConfigSection_ . '.database');
        $user = Eisodos::$parameterHandler->getParam($databaseConfigSection_ . '.username');
        $pass = Eisodos::$parameterHandler->getParam($databaseConfigSection_ . '.password');

        $this->connection = new PDO(
            "mysql:host=$host;dbname=$db",
            $user,
            $pass,
            [PDO::ATTR_PERSISTENT => $persistent_]
        );
    }

    public function disconnect(bool $force_ = false): void {
        $this->connection = null;
    }

    // ... implement other methods
}
```

## Usage

```php
// Register connector
$db = new MySQLConnector();
Eisodos::$dbConnectors->registerDBConnector($db, 'main');

// Connect
$db->connect('Database');

// Query
$users = $db->query(RT_ALL_ROWS, 'SELECT * FROM users');

// Transaction
$db->startTransaction();
try {
    $db->executeDML('UPDATE accounts SET balance = balance - 100 WHERE id = 1');
    $db->executeDML('UPDATE accounts SET balance = balance + 100 WHERE id = 2');
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    throw $e;
}
```

## See Also

- [DBConnectors](DBConnectors.md) - Connector registry
- [Eisodos](Eisodos.md) - Main framework class
