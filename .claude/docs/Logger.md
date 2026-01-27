# Logger Class

Comprehensive logging system with multiple debug levels and output destinations.

**Namespace:** `Eisodos`
**Extends:** `Eisodos\Abstracts\Singleton`
**Source:** `src/Eisodos/Logger.php`

## Overview

The `Logger` class provides a flexible logging system supporting multiple debug levels, various output destinations (file, mail, screen, console, URL), and structured logging with timestamps and call stack tracking.

## Properties

| Property | Type | Description |
|----------|------|-------------|
| `$cliMode` | `bool` | Indicates if script is running in CLI mode |

## Debug Levels

The following debug levels are supported (in order of severity):

| Level | Description |
|-------|-------------|
| `trace` | Detailed execution tracing |
| `debug` | Debug information |
| `info` | Informational messages |
| `notice` | Normal but significant events |
| `alert` | Action must be taken immediately |
| `warning` | Warning conditions |
| `error` | Error conditions |
| `emergency` | System is unusable |
| `critical` | Critical conditions |

## Methods

### init(array $options_): void

Initializes the logger.

**Parameters:**
- `$options_` - Array of debug levels to enable

### setDebugLevels(string|null $debugLevels_): void

Sets the active debug levels.

**Parameters:**
- `$debugLevels_` - Comma-separated list of levels, or a single level (all levels >= that level will be enabled)

**Example:**
```php
// Enable specific levels
Eisodos::$logger->setDebugLevels('error,warning,critical');

// Enable all levels from 'info' and above
Eisodos::$logger->setDebugLevels('info');
```

### getDebugLevels(): array

Returns the currently configured debug levels.

### setDebugOutputs(array $options): void

Sets output destinations for debug messages.

**Parameters:**
- `$options` - Array containing:
  - `debugToFile` - File path for log output
  - `debugToUrl` - URL endpoint for log posting

### log(string $text_, string $debugLevel_ = 'debug', object|null $sender_ = null): void

Adds a debug message to the log.

**Parameters:**
- `$text_` - Log message
- `$debugLevel_` - Debug level
- `$sender_` - Sender object (for class name in log)

### Convenience Methods

```php
Eisodos::$logger->trace('BEGIN', $this);
Eisodos::$logger->debug('Processing item');
Eisodos::$logger->info('User logged in');
Eisodos::$logger->notice('Cache cleared');
Eisodos::$logger->alert('Disk space low');
Eisodos::$logger->warning('Deprecated function used');
Eisodos::$logger->error('Database connection failed');
Eisodos::$logger->emergency('System shutting down');
Eisodos::$logger->critical('Security breach detected');
```

### writeErrorLog(Throwable|null $throwable_, string $debugInformation_ = '', array $extraMails_ = []): void

Writes comprehensive error log to configured outputs.

**Parameters:**
- `$throwable_` - Exception/Error object
- `$debugInformation_` - Additional debug information
- `$extraMails_` - Additional email addresses to notify

### getDebugLog(): array

Returns the accumulated debug log entries.

## Configuration Parameters

| Parameter | Description |
|-----------|-------------|
| `DebugLevel` | Default debug level(s) |
| `DebugToFile` | File path for debug output |
| `DebugToUrl` | URL for posting debug logs |
| `ERROROUTPUT` | Output destinations (`File`, `Mail`, `Screen`, `Console`, `@callback`) |
| `ERRORLOG` | Path to error log file |
| `ERRORMAILTO` | Email address for error notifications |
| `ERRORMAILFROM` | From address for error emails |

## Output Formats

### Log Line Format

```
[2024-01-15 10:30:45.123456] [DEBUG] [ClassName] [methodName]    |  Log message here
```

### Error Log Format

```
---------- ApplicationName ----------
2024.01.15. 10:30:45
Error message here
/path/to/file.php at line 123
Stack trace...
----- Extended Error -----
...
----- URL -----
/current/request/uri
----- Parameters -----
param1=value1
param2=value2
----- Headers -----
User-Agent=Mozilla/5.0...
```

## Usage Examples

### Basic Logging

```php
Eisodos::$logger->info('Application started');
Eisodos::$logger->debug('Processing request: ' . $requestId);
```

### Method Tracing

```php
public function processOrder($orderId) {
    Eisodos::$logger->trace('BEGIN', $this);

    // ... processing logic ...

    Eisodos::$logger->trace('END', $this);
}
```

### Error Handling

```php
try {
    // ... code that may throw ...
} catch (Exception $e) {
    Eisodos::$logger->writeErrorLog($e, 'Additional context info');
}
```

### URL-based Log Posting

Debug logs can be automatically posted to a URL endpoint at the end of request:

```php
Eisodos::$logger->setDebugOutputs([
    'debugToUrl' => 'https://logs.example.com/collect'
]);
```

## See Also

- [Eisodos](Eisodos.md) - Main framework class
- [Mailer](Mailer.md) - Email functionality for error notifications
