# Eisodos Framework Documentation

Eisodos is a comprehensive, configuration-driven PHP web application framework emphasizing flexibility, template-based rendering, and multi-language support.

## Table of Contents

### Core Classes

| Class | Description |
|-------|-------------|
| [Eisodos](Eisodos.md) | Main singleton - framework bootstrap and component registry |
| [ConfigLoader](ConfigLoader.md) | Configuration file management (INI/JSON) |
| [Logger](Logger.md) | Logging system with multiple levels and outputs |
| [TemplateEngine](TemplateEngine.md) | Template processing and variable replacement |
| [Render](Render.md) | Page generation and HTTP response handling |
| [ParameterHandler](ParameterHandler.md) | Global parameter/variable management |
| [Translator](Translator.md) | Multi-language translation support |
| [Mailer](Mailer.md) | Email sending functionality |
| [DBConnectors](DBConnectors.md) | Database connector registry |
| [Utils](Utils.md) | Utility functions |

### Abstract Classes

| Class | Description |
|-------|-------------|
| [Singleton](Singleton.md) | Base singleton pattern implementation |

### Interfaces

| Interface | Description |
|-----------|-------------|
| [ParserInterface](ParserInterface.md) | Template block parser contract |
| [DBConnectorInterface](DBConnectorInterface.md) | Database connector contract |

### Parsers

| Class | Description |
|-------|-------------|
| [CallbackFunctionParser](CallbackFunctionParser.md) | Full callback syntax `<%FUNC%...%FUNC%>` |
| [CallbackFunctionShortParser](CallbackFunctionShortParser.md) | Short callback syntax `[%...%]` |

## Quick Start

### Bootstrap File (`__eisodos.php`)

```php
<?php
use Eisodos\Eisodos;
use Eisodos\Connectors\ConnectorOCI8;

require_once '../vendor/autoload.php';

try {
    // Initialize Eisodos with app directory and name
    Eisodos::getInstance()->init([
        __DIR__,           // Application directory
        'myapp'            // Application name (used for config files)
    ]);

    // Start the render system
    Eisodos::$render->start(
        ['configType' => Eisodos::$configLoader::CONFIG_TYPE_INI],
        [],  // Cache options
        [],  // Template engine options
        ''   // Debug level
    );

    // Register callback functions
    require_once(__DIR__ . '/_callbacks.php');
    Eisodos::$templateEngine->setDefaultCallbackFunction('callback_default');

    // Register database connector
    Eisodos::$dbConnectors->registerDBConnector(new ConnectorOCI8());

} catch (Exception $e) {
    if (!isset(Eisodos::$logger)) {
        die($e->getMessage());
    }
    Eisodos::$logger->writeErrorLog($e);
    exit(1);
}
```

### Configuration File (`config/myapp.conf`)

```ini
[Env]

[PreInclude]
1=global.conf:Config
2=i18n.conf:Config

[PostInclude]
1=version.conf:Version
2=custom.conf:Config

[Database]
username=myapp
password=secret
connection=DEV
autoCommit=false
connectSQL=ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD HH24:MI:SS';
caseQuery=lower
caseStoredProcedure=upper

[Config]
MainAddress=https://myapp.example.com/
TemplateDir=/app/dist/src/assets/templates/

# Application environment
APP_HOMEPAGE_URL=/DASHBOARD/index/
APP_LOGOUT_URL=/USERS/logout/
APP_ENV=DEV
APP_ENV_COLOR=purple

# Cookie settings
COOKIE_DOMAIN=myapp.example.com
COOKIE_SECURE=T
COOKIE_PATH=/
COOKIE_HTTPONLY=T

# Error handling
.ErrorLog=/var/log/application/$_applicationName-error.log
ErrorMailTo=admin@example.com
ErrorOutput=File,Mail
```

### Template File (`templates/main.template`)

```html
<!DOCTYPE html>
<html>
<head>
    <title>%TITLE%</title>
    $page_headitems
</head>
<body>
    <header>
        $templateabs_header_main

        <!-- Conditional search based on config -->
        [%funcjob=eq;param=APP_SEARCH_URL;value=;false=header.search;true=empty%]

        <!-- Environment badge -->
        <span class="badge badge-$app_env_color">$app_env</span>
    </header>

    <main>
        <!-- Dynamic content with default -->
        <h1>[:WELCOME_MESSAGE,Welcome:]</h1>
        <p>Hello, $username~='Guest';!</p>

        $content
    </main>

    <footer>
        &copy; $currdate $company_name
        $templateabs_footer_main
    </footer>

    $page_footitems
</body>
</html>
```

### Callback Functions (`_callbacks.php`)

```php
<?php
use Eisodos\Eisodos;

function callback_default(array $LFuncParams = []): mixed {
    $funcjob = Eisodos::$utils->safe_array_value($LFuncParams, 'funcjob');

    // Equality check - returns template
    if ($funcjob === 'eq') {
        if (Eisodos::$parameterHandler->eq(
            Eisodos::$utils->safe_array_value($LFuncParams, 'param'),
            Eisodos::$utils->safe_array_value($LFuncParams, 'value')
        )) {
            return Eisodos::$templateEngine->getTemplate(
                Eisodos::$utils->safe_array_value($LFuncParams, 'true'), [], false
            );
        }
        return Eisodos::$templateEngine->getTemplate(
            Eisodos::$utils->safe_array_value($LFuncParams, 'false'), [], false
        );
    }

    // Equality check - returns string
    if ($funcjob === 'eqs') {
        if (Eisodos::$parameterHandler->eq(
            Eisodos::$utils->safe_array_value($LFuncParams, 'param'),
            Eisodos::$utils->safe_array_value($LFuncParams, 'value')
        )) {
            return Eisodos::$utils->safe_array_value($LFuncParams, 'true');
        }
        return Eisodos::$utils->safe_array_value($LFuncParams, 'false');
    }

    // Case/switch - returns string
    if ($funcjob === 'cases') {
        $paramValue = Eisodos::$parameterHandler->getParam(
            Eisodos::$utils->safe_array_value($LFuncParams, 'param')
        );
        return Eisodos::$utils->safe_array_value(
            $LFuncParams,
            $paramValue,
            Eisodos::$utils->safe_array_value($LFuncParams, 'else')
        );
    }

    // Date functions
    $phpDateFormat = Eisodos::$parameterHandler->getParam('PHPDateFormat', 'Y-m-d');
    switch ($funcjob) {
        case 'today':
            return (new DateTime())->format($phpDateFormat);
        case 'now':
            return (new DateTime())->format('Y-m-d H:i:s');
        case 'lastweek':
            return (new DateTime())->modify('-1 week')->format($phpDateFormat);
    }

    return '';
}
```

## Architecture Overview

```
Eisodos (Main Singleton)
├── ConfigLoader      - Modular INI/JSON configuration with PreInclude/PostInclude
├── ParameterHandler  - Parameter/variable storage with session/cookie persistence
├── TemplateEngine    - Template processing with variable replacement
│   └── Parsers
│       ├── Translator              - [:LANGID,default:] syntax
│       ├── CallbackFunctionParser  - <%FUNC%...%FUNC%> full syntax
│       └── CallbackFunctionShortParser - [%funcjob=...%] short syntax
├── Render            - Page output with response buffering
├── Logger            - Logging to file/email/screen/URL
├── Translator        - Multi-language with master/user files
├── Mailer            - PHPMailer integration
├── DBConnectors      - Database connector registry (OCI8, MySQL, PostgreSQL)
│   └── DBConnectorInterface implementations
└── Utils             - safe_array_value and utilities
```

## Key Features

### Configuration System
- **Modular configuration** - PreInclude/PostInclude for shared settings
- **Environment-based** - DEV/PROD/TEST configurations
- **Hierarchical namespacing** - `SMTP.host`, `FileHandler.Object.BasePath`
- **Readonly parameters** - `.ErrorLog` prefix prevents override
- **Parameter filters** - `.params` file for input validation

### Template System
- **Variable replacement** - `$paramName` with `$param~='default';` defaults
- **Template includes** - `$templateabs_component_name`
- **Callback blocks** - `[%funcjob=eqs;param=status;value=Y;true=active;false=inactive%]`
- **Translations** - `[:LANGID,default:]` with master/user language files
- **Sequences** - `$seq`, `$seqbit`, `$seq0` for alternating rows
- **Dynamic variables** - `$currdate`, `$random`, `$_sessionid`, `$https`

### Parameter Management
- **Session/cookie persistence** - `setParam('key', 'value', true, false)`
- **Comparison methods** - `eq()`, `neq()`, `isOn()`, `isOff()`
- **Input filtering** - Regex validation, exclude/protect commands
- **Encryption** - `udSCode()`/`udSDecode()` for secure transmission
- **Re-post detection** - Automatic duplicate submission handling

### Database Integration
- **Oracle OCI8** - With stored procedure support
- **MySQL/PostgreSQL** - Standard connectors
- **Query modes** - `RT_ALL_ROWS`, `RT_FIRST_ROW`, `RT_NO_DATA`
- **Auto-formatting** - Date/number format conversion per database type

### Common Template Patterns

**Boolean Display:**
```html
<span class="[%funcjob=eqs;param=is_active;value=Y;true=text-success;false=text-danger%]">
    [%funcjob=eqs;param=is_active;value=Y;true=[:YES,Yes:];false=[:NO,No:]%]
</span>
```

**Status with Color Coding:**
```html
<span class="text-[%funcjob=cases;param=status;ACTIVE=success;PENDING=warning;DELETED=danger;else=secondary%]">
    $status
</span>
```

**Date Filter Defaults:**
```html
<input type="text" name="date_from" value="$date_from~='[%funcjob=lastweek%]';">
<input type="text" name="date_to" value="$date_to~='[%funcjob=today%]';">
```

## Directory Structure

```
project/
├── config/
│   ├── myapp.conf          # Main configuration
│   ├── myapp.params        # Parameter filters
│   ├── global.conf         # Shared settings
│   ├── i18n.conf           # Internationalization
│   ├── filehandler.conf    # File storage settings
│   └── environment         # DEV/PROD indicator
├── src/
│   ├── __eisodos.php       # Bootstrap file
│   ├── _init.php           # Include bootstrap
│   ├── _callbacks.php      # Callback functions
│   ├── _init_parameters.php # Parameter initialization
│   ├── _run.php            # Tholos MVC runner
│   └── index.php           # Entry point
├── assets/
│   └── templates/
│       ├── main.template
│       ├── header.main.template
│       ├── footer.main.template
│       ├── empty.template
│       ├── grid.column.bool.template
│       ├── grid.column.status.template
│       └── EN/             # Language-specific templates
├── i18n/
│   ├── langids.txt         # Generated language strings
│   └── user_langids.txt    # User translations
└── vendor/                 # Composer dependencies
```

## Component Access

After initialization, all components are accessible via static properties:

```php
// Configuration
Eisodos::$configLoader->importConfigSection('FileHandler', 'filehandler.conf');

// Parameters
Eisodos::$parameterHandler->setParam('user_id', '123', true);  // Session stored
$userId = Eisodos::$parameterHandler->getParam('user_id');
if (Eisodos::$parameterHandler->isOn('DEBUG')) { /* ... */ }

// Templates
$html = Eisodos::$templateEngine->getTemplate('email.body', [], false);
Eisodos::$templateEngine->addToResponse(json_encode($data));

// Database
$db = Eisodos::$dbConnectors->connector();
$db->connect('Database');
$results = $db->query(RT_ALL_ROWS, 'SELECT * FROM users');

// Utilities
$value = Eisodos::$utils->safe_array_value($array, 'key', 'default');

// Logging
Eisodos::$logger->debug('Processing started', $this);
Eisodos::$logger->error('Connection failed');

// Email
Eisodos::$mailer->sendMail($to, $subject, $body, $from);

// Rendering
Eisodos::$render->finish();      // HTML output
Eisodos::$render->finishRaw();   // JSON/raw output
```

## See Also

- Individual class documentation for detailed API reference
- Source code in `src/Eisodos/` directory
