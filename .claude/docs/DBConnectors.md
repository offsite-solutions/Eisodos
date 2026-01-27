# DBConnectors Class

Database connector registry for managing multiple database connections.

**Namespace:** `Eisodos`
**Extends:** `Eisodos\Abstracts\Singleton`
**Source:** `src/Eisodos/DBConnectors.php`

## Overview

The `DBConnectors` class serves as a registry for database connections. It allows registering, accessing, and managing multiple database connector instances that implement the `DBConnectorInterface`.

## Methods

### init(mixed $options_ = null): DBConnectors

Initializes the DB connectors registry.

**Returns:** The `DBConnectors` instance

### registerDBConnector(DBConnectorInterface $connector_, string $key_ = '0'): DBConnectorInterface

Registers a database connector.

**Parameters:**
- `$connector_` - A database connector implementing `DBConnectorInterface`
- `$key_` - Unique identifier for this connector (default: `'0'`)

**Returns:** The registered connector

**Throws:** `RuntimeException` if key already exists

**Example:**
```php
$mysqlConnector = new MySQLConnector();
Eisodos::$dbConnectors->registerDBConnector($mysqlConnector, 'main');

$oracleConnector = new OracleConnector();
Eisodos::$dbConnectors->registerDBConnector($oracleConnector, 'oracle');
```

### connector(string $key_ = '0'): DBConnectorInterface

Retrieves a registered connector by key.

**Parameters:**
- `$key_` - Connector identifier (default: `'0'`)

**Returns:** The requested `DBConnectorInterface` implementation

**Throws:** `RuntimeException` if key doesn't exist

**Example:**
```php
$db = Eisodos::$dbConnectors->connector('main');
$result = $db->query(RT_ALL_ROWS, 'SELECT * FROM users');
```

### db(string $key_ = '0'): DBConnectorInterface

Alias for `connector()` - kept for backward compatibility.

### __destruct()

Automatically disconnects all registered database connections when the object is destroyed.

## Usage Examples

### Basic Setup

```php
// Create and register a connector
$connector = new MySQLConnector();
Eisodos::$dbConnectors->registerDBConnector($connector, 'main');

// Connect to database
Eisodos::$dbConnectors->connector('main')->connect('Database');
```

### Multiple Databases

```php
// Register main database
$mainDb = new MySQLConnector();
Eisodos::$dbConnectors->registerDBConnector($mainDb, 'main');
$mainDb->connect('MainDatabase');

// Register reporting database
$reportDb = new MySQLConnector();
Eisodos::$dbConnectors->registerDBConnector($reportDb, 'reports');
$reportDb->connect('ReportDatabase');

// Use main database
$users = Eisodos::$dbConnectors->connector('main')
    ->query(RT_ALL_ROWS, 'SELECT * FROM users');

// Use reports database
$stats = Eisodos::$dbConnectors->connector('reports')
    ->query(RT_FIRST_ROW, 'SELECT COUNT(*) as total FROM daily_stats');
```

### Default Connector

```php
// Using default key '0'
$connector = new MySQLConnector();
Eisodos::$dbConnectors->registerDBConnector($connector);  // Uses '0' as key

// Access default connector
$db = Eisodos::$dbConnectors->connector();  // Gets connector with key '0'
```

### Complete Example

```php
<?php
use Eisodos\Eisodos;

// Initialize Eisodos
Eisodos::getInstance()->init([__DIR__, 'myapp']);

// Create and register database connector
$db = new MySQLConnector();
Eisodos::$dbConnectors->registerDBConnector($db, 'main');

// Start rendering (loads config)
Eisodos::$render->start(['configType' => 0]);

// Connect using config section
Eisodos::$dbConnectors->connector('main')->connect('Database');

// Use the database
$users = Eisodos::$dbConnectors->connector('main')->query(
    RT_ALL_ROWS,
    'SELECT id, name, email FROM users WHERE active = 1'
);

foreach ($users as $user) {
    echo "User: {$user['name']}\n";
}

// Connections are automatically closed on script end
```

## Configuration Example

```ini
[Database]
hostname=localhost
database=myapp
username=dbuser
password=secret
port=3306
charset=utf8mb4
```

## Error Handling

```php
try {
    $db = Eisodos::$dbConnectors->connector('nonexistent');
} catch (RuntimeException $e) {
    // Handle: DB Connector index not exists: nonexistent
}

try {
    Eisodos::$dbConnectors->registerDBConnector($newConnector, 'main');
} catch (RuntimeException $e) {
    // Handle: DB Connector index already exists: main
}
```

## See Also

- [Eisodos](Eisodos.md) - Main framework class
- [DBConnectorInterface](DBConnectorInterface.md) - Database connector interface
