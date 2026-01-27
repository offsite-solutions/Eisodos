# Singleton Abstract Class

Base class implementing the Singleton design pattern.

**Namespace:** `Eisodos\Abstracts`
**Source:** `src/Eisodos/Abstracts/Singleton.php`

## Overview

The `Singleton` abstract class provides a base implementation of the Singleton design pattern. All major Eisodos framework components extend this class to ensure only one instance of each component exists throughout the application lifecycle.

## Properties

| Property | Type | Description |
|----------|------|-------------|
| `$instances` | `static array` | Registry of singleton instances by class name |

## Methods

### getInstance(): static

Returns the singleton instance of the class. Creates a new instance if one doesn't exist.

**Returns:** The singleton instance

**Example:**
```php
$logger = Logger::getInstance();
$config = ConfigLoader::getInstance();
```

### __construct()

Protected constructor to prevent direct instantiation. Use `getInstance()` instead.

### __clone()

Protected clone method to prevent cloning of the singleton instance.

### __wakeup()

Throws `RuntimeException` to prevent unserialization of the singleton.

### init(array $options_): void

Abstract method that must be implemented by subclasses. Called after `getInstance()` to initialize the singleton with options.

**Parameters:**
- `$options_` - Initialization options (format depends on subclass)

## Usage

### Creating a Singleton Class

```php
<?php
namespace MyApp;

use Eisodos\Abstracts\Singleton;

class MyService extends Singleton {
    private string $setting = '';

    public function init(array $options_ = []): void {
        $this->setting = $options_['setting'] ?? 'default';
    }

    public function getSetting(): string {
        return $this->setting;
    }
}

// Usage
$service = MyService::getInstance();
$service->init(['setting' => 'custom']);
echo $service->getSetting(); // 'custom'

// Later in code - same instance
$sameService = MyService::getInstance();
echo $sameService->getSetting(); // 'custom'
```

### Framework Component Pattern

```php
<?php
namespace Eisodos;

use Eisodos\Abstracts\Singleton;

final class MyComponent extends Singleton {
    private array $data = [];

    public function init(array $options_ = []): void {
        // Initialize component
        Eisodos::$logger->trace('BEGIN', $this);

        // Load configuration
        $this->data = $options_;

        Eisodos::$logger->trace('END', $this);
    }

    public function doSomething(): void {
        // Component logic
    }
}
```

## Design Pattern

The Singleton pattern ensures:

1. **Single Instance** - Only one instance of the class exists
2. **Global Access** - The instance is globally accessible via `getInstance()`
3. **Lazy Initialization** - The instance is created only when first requested
4. **Prevent Duplication** - Cloning and unserialization are blocked

## Instance Registry

The class maintains a static registry of instances keyed by class name:

```php
protected static array $instances = [];

public static function getInstance() {
    $class = static::class;
    if (!isset(self::$instances[$class])) {
        self::$instances[$class] = new $class();
    }
    return self::$instances[$class];
}
```

This allows multiple different singleton subclasses to coexist independently.

## Best Practices

### Do:

```php
// Use getInstance() to get the singleton
$logger = Logger::getInstance();

// Initialize after getting instance
$logger->init(['level' => 'debug']);
```

### Don't:

```php
// Don't try to instantiate directly
$logger = new Logger(); // Protected constructor - won't work

// Don't try to clone
$copy = clone Logger::getInstance(); // Protected - won't work

// Don't serialize/unserialize
$serialized = serialize(Logger::getInstance());
$restored = unserialize($serialized); // Throws RuntimeException
```

## Framework Singletons

The following Eisodos classes extend `Singleton`:

| Class | Description |
|-------|-------------|
| `Eisodos` | Main framework class |
| `ConfigLoader` | Configuration management |
| `Logger` | Logging system |
| `ParameterHandler` | Parameter management |
| `TemplateEngine` | Template processing |
| `Render` | Page rendering |
| `Translator` | Language translation |
| `Mailer` | Email functionality |
| `DBConnectors` | Database connection registry |
| `Utils` | Utility functions |

## See Also

- [Eisodos](Eisodos.md) - Main framework class
- [Logger](Logger.md) - Example singleton implementation
- [ConfigLoader](ConfigLoader.md) - Example singleton implementation
