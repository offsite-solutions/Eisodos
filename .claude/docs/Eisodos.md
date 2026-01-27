# Eisodos Class

The main singleton class that serves as the entry point and central registry for the Eisodos framework.

**Namespace:** `Eisodos`
**Extends:** `Eisodos\Abstracts\Singleton`
**Source:** `src/Eisodos/Eisodos.php`

## Overview

The `Eisodos` class is the bootstrap singleton that initializes and holds references to all framework components. It provides static access to all major subsystems through public static properties.

## Static Properties

| Property | Type | Description |
|----------|------|-------------|
| `$parameterHandler` | `ParameterHandler` | Global parameter/variable management |
| `$configLoader` | `ConfigLoader` | Configuration file loader |
| `$templateEngine` | `TemplateEngine` | Template processing engine |
| `$translator` | `Translator` | Multi-language translation support |
| `$mailer` | `Mailer` | Email sending functionality |
| `$render` | `Render` | Page generation and response handling |
| `$logger` | `Logger` | Logging system |
| `$dbConnectors` | `DBConnectors` | Database connector registry |
| `$utils` | `Utils` | Utility functions |
| `$applicationName` | `string` | Application's name (set during init) |
| `$applicationDir` | `string` | Application's directory (set during init) |

## Methods

### init(array $options_): Eisodos

Initializes the framework with all its components.

**Parameters:**
- `$options_` - Array containing:
  - `[0]` - Application directory path
  - `[1]` - Application name

**Returns:** `Eisodos` instance

## Real-World Initialization Pattern

Based on production applications, the recommended initialization pattern:

```php
<?php
// __eisodos.php - Bootstrap file

use Eisodos\Eisodos;
use Eisodos\Connectors\ConnectorOCI8;  // or ConnectorMySQL, etc.
use Eisodos\Parsers\CallbackFunctionParser;
use Eisodos\Parsers\CallbackFunctionShortParser;

require_once '../vendor/autoload.php';

try {
    // 1. Initialize Eisodos with app directory and name
    Eisodos::getInstance()->init([
        __DIR__,           // Application directory
        'myapp'            // Application name (used for config files)
    ]);

    // 2. Start the render system with configuration
    Eisodos::$render->start(
        ['configType' => Eisodos::$configLoader::CONFIG_TYPE_INI],
        [],  // Cache options
        [],  // Template engine options
        ''   // Debug level (empty = from config)
    );

    // 3. Register callback functions from external file
    require_once(__DIR__ . '/_callbacks.php');
    Eisodos::$templateEngine->setDefaultCallbackFunction('callback_default');

    // 4. Register database connector (not connected yet)
    Eisodos::$dbConnectors->registerDBConnector(new ConnectorOCI8());

} catch (Exception $e) {
    // Fallback error handling if logger not initialized
    if (!isset(Eisodos::$logger)) {
        die($e->getMessage());
    }
    Eisodos::$logger->writeErrorLog($e);
    exit(1);
}
```

## Application Entry Points

### Simple Page Rendering

```php
<?php
// index.php
include("_init.php");  // Includes __eisodos.php

// Load and render template
Eisodos::$templateEngine->getTemplate('page.main');

// Finish and output
Eisodos::$render->finish();
```

### Print Document Handler

```php
<?php
// bo_printDocument.php
include("_init.php");

// Dynamic template based on parameter
Eisodos::$templateEngine->getTemplate(
    "print." . Eisodos::$parameterHandler->getParam("document_id") . ".main"
);

Eisodos::$render->finish();
```

### JSON API Endpoint

```php
<?php
// api_endpoint.php
require_once __DIR__ . '/__eisodos.php';

// Disable statistics for API responses
Eisodos::$parameterHandler->setParam('CollectLangIDs', 'F');
Eisodos::$parameterHandler->setParam('IncludeStatistic', 'F');

try {
    $data = json_decode(
        Eisodos::$parameterHandler->getParam('data'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    // Process request...
    $result = processData($data);

    Eisodos::$templateEngine->addToResponse(
        json_encode(['errorcode' => '0', 'data' => $result], JSON_THROW_ON_ERROR)
    );

} catch (Exception $e) {
    Eisodos::$templateEngine->addToResponse(
        json_encode(['errorcode' => '1', 'errormsg' => $e->getMessage()], JSON_THROW_ON_ERROR)
    );
    Eisodos::$logger->writeErrorLog($e);
    http_response_code(500);
}

// Use finishRaw for JSON responses
Eisodos::$render->finishRaw();
```

## Accessing Components

After initialization, all components are accessible via static properties:

```php
// Parameter management
Eisodos::$parameterHandler->setParam('user_id', '123', true);  // Session stored
$userId = Eisodos::$parameterHandler->getParam('user_id');

// Safe parameter comparison
if (Eisodos::$parameterHandler->eq('status', 'active')) {
    // Handle active status
}
if (Eisodos::$parameterHandler->neq('error', '')) {
    // Handle error
}

// Template rendering
$html = Eisodos::$templateEngine->getTemplate('email.template', [], false);

// Logging
Eisodos::$logger->debug('Processing started', $this);
Eisodos::$logger->error('Connection failed');

// Database access
$db = Eisodos::$dbConnectors->connector();
$db->connect('Database');
$results = $db->query(RT_ALL_ROWS, 'SELECT * FROM users');

// Utility functions (safe array access)
$value = Eisodos::$utils->safe_array_value($array, 'key', 'default');

// Email sending
Eisodos::$mailer->sendMail($to, $subject, $body, $from);
```

## Component Initialization Order

1. `Utils` - Utility functions
2. `Logger` - Logging system (initial setup)
3. `ConfigLoader` - Configuration loading
4. `ParameterHandler` - Parameter management
5. `Render` - Page rendering
6. `TemplateEngine` - Template processing
7. `Translator` - Language translation
8. `Mailer` - Email functionality
9. `DBConnectors` - Database connections

## Integration with Tholos Framework

Eisodos integrates with the Tholos MVC framework:

```php
<?php
// _run.php - Tholos application runner
use Eisodos\Eisodos;
use Tholos\Tholos;

include("_init.php");

try {
    Tholos::getInstance()->init([]);
    Tholos::$app->run();
} catch (Throwable $e) {
    Eisodos::$logger->writeErrorLog($e);
    Eisodos::$render->Response = '';
    Eisodos::$templateEngine->addToResponse($e->getMessage());
    Eisodos::$render->finish();
}
```

## Directory Structure

Recommended project structure:

```
project/
├── config/
│   ├── myapp.conf          # Main configuration
│   ├── myapp.params        # Parameter filters
│   └── environment         # Environment indicator
├── src/
│   ├── __eisodos.php       # Bootstrap file
│   ├── _init.php           # Include bootstrap + autoloader
│   ├── _callbacks.php      # Callback function definitions
│   ├── _init_parameters.php # Parameter initialization
│   └── index.php           # Entry point
├── assets/
│   └── templates/          # Template files
├── i18n/
│   ├── generated.txt       # Generated language strings
│   └── translated.txt      # Translated strings
└── vendor/                 # Composer dependencies
```

## See Also

- [ConfigLoader](ConfigLoader.md) - Configuration management
- [ParameterHandler](ParameterHandler.md) - Parameter handling
- [Render](Render.md) - Page rendering
- [TemplateEngine](TemplateEngine.md) - Template processing
- [CallbackFunctionShortParser](CallbackFunctionShortParser.md) - Callback syntax
