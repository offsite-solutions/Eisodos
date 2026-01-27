# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Eisodos is a PHP 8.4+ page generation framework with singleton-based architecture. It provides configuration management, template processing, internationalization, logging, and database abstraction.

## Running Tests

Tests are located in `tests/` and are standalone PHP scripts (no PHPUnit):

```bash
php tests/test_configLoader_INI.php
php tests/test_configLoader_JSON.php
php tests/test_configLoader_INI_environment.php
php tests/test_templateEngine_1.php
php tests/test_templateEngine_2_callbacks.php
```

## Architecture

### Singleton Pattern

All framework modules extend `Abstracts/Singleton.php` and are accessed as static properties on the main `Eisodos` class:

```php
Eisodos::$parameterHandler   // Parameter management (GET/POST/SESSION/cookies merged)
Eisodos::$configLoader       // INI/JSON config file loading with sections
Eisodos::$templateEngine     // Template loading, parsing, callback execution
Eisodos::$translator         // Multi-language support with language IDs
Eisodos::$logger             // PSR-3 style logging with multiple outputs
Eisodos::$mailer             // PHPMailer-based email sending
Eisodos::$render             // Page generation orchestration
Eisodos::$dbConnectors       // Database connector registry
Eisodos::$utils              // Utility functions
```

### Bootstrap Example

```php
<?php
use Eisodos\Eisodos;

require_once '../vendor/autoload.php';

Eisodos::getInstance()->init([__DIR__, 'myapp']);

Eisodos::$render->start(
    ['configType' => Eisodos::$configLoader::CONFIG_TYPE_INI],
    [],  // Cache options
    [],  // Template engine options
    ''   // Debug level (trace/debug/info/warning/error/critical)
);

require_once(__DIR__ . '/_callbacks.php');
Eisodos::$templateEngine->setDefaultCallbackFunction('callback_default');

// ... application code ...

Eisodos::$render->finish();     // HTML output
// or Eisodos::$render->finishRaw();  // JSON/raw output
```

### Directory Structure

```
src/Eisodos/
├── Abstracts/Singleton.php      # Base singleton implementation
├── Interfaces/                   # DBConnectorInterface, ParserInterface
├── Parsers/                      # CallbackFunction parsers
├── config/                       # Default config templates (common_pre_*.conf)
├── Eisodos.php                   # Main entry point
├── ParameterHandler.php          # Parameter merging
├── ConfigLoader.php              # Config file loading
├── TemplateEngine.php            # Template processing
├── Render.php                    # Page generation
├── Logger.php                    # Logging system
├── Translator.php                # i18n support
├── Mailer.php                    # Email via PHPMailer
├── DBConnectors.php              # DB registry
└── Utils.php                     # Utilities
```

## Configuration

Config files are INI or JSON format with sections. Environment-specific files are loaded first (`{env}-{appname}.conf`).

```ini
[PreInclude]
1=global.conf:Config

[PostInclude]
1=version.conf:Version

[Config]
TemplateDir=/app/templates/
.ErrorLog=$_applicationDir/logs/$_applicationName-error.log
ErrorOutput=File,Mail
```

Key sections:
- `[Config]` - Main parameters (loaded into ParameterHandler)
- `[PreInclude]` / `[PostInclude]` - Config file includes with format `file:Section`
- `[Env]` - Environment variables to set

Readonly parameters: prefix with `.` to prevent override (e.g., `.ErrorLog`)

## Parameter Handling

```php
// Get/set parameters
$value = Eisodos::$parameterHandler->getParam('key', 'default');
Eisodos::$parameterHandler->setParam('key', 'value', $sessionStored, $cookieStored);

// Comparison methods
Eisodos::$parameterHandler->eq('status', 'active')   // equals
Eisodos::$parameterHandler->neq('error', '')         // not equals (common for empty check)
Eisodos::$parameterHandler->isOn('DEBUG')            // T, ON, 1, TRUE, YES, Y
Eisodos::$parameterHandler->isOff('CACHE')           // F, OFF, 0, FALSE, NO, N

// Reference another parameter with ^ prefix
Eisodos::$parameterHandler->setParam('current', '^default_lang');
```

### Built-in Dynamic Variables

| Variable | Description |
|----------|-------------|
| `$seq`, `$seq0`, `$seql`, `$seqbit` | Sequence counter, reset, last value, modulo 2 |
| `$currdate` | Current year |
| `$random` | Random 8-char string |
| `$_sessionid` | Session ID |
| `$https` | Protocol (http/https) |
| `$lnbr` | Line break (PHP_EOL) |
| `$env_VARNAME` | Environment variable |

### Parameter Filtering (.params file)

```
input;username;text;;
input;page;numeric;;
input;email;/^[a-z0-9@.]+$/i;/error;;
session;user_id;;;
protected;admin_action;;;
permanent;remember_me=30;;;
```

## Template Syntax

```html
$parameterName              <!-- Parameter reference -->
$param~='default';          <!-- With default value -->
$templateabs_header_main    <!-- Embed header.main.template -->
[:LANG_ID,default:]         <!-- Language translation -->
##comment line              <!-- Removed in output -->
```

### Callback Functions

Short syntax `[%...%]`:
```html
<!-- Equality check - returns string -->
[%funcjob=eqs;param=is_active;value=Y;true=active;false=inactive%]

<!-- Equality check - returns template -->
[%funcjob=eq;param=show_search;value=T;true=search.box;false=empty%]

<!-- Case/switch -->
[%funcjob=cases;param=status;ACTIVE=green;PENDING=yellow;else=gray%]

<!-- Date functions -->
[%funcjob=today%]
[%funcjob=now%]
[%funcjob=lastweek%]
```

Full syntax `<%FUNC%...%FUNC%>`:
```html
<%FUNC%
_function_name=renderUserCard
@name=$current_user_name
role=admin
%FUNC%>
```

### Common Template Patterns

**Boolean display:**
```html
<span class="[%funcjob=eqs;param=prop_value;value=Y;true=text-success;false=text-danger%]">
    [%funcjob=eqs;param=prop_value;value=Y;true=[:YES,Yes:];false=[:NO,No:]%]
</span>
```

**Status with color coding:**
```html
<span class="text-[%funcjob=cases;param=status;ACTIVE=success;PENDING=warning;DELETED=danger;else=secondary%]">
    $status
</span>
```

**Form select with selected state:**
```html
<select name="status">
    <option value="ACTIVE" [%funcjob=eqs;param=status;value=ACTIVE;true=selected;false=%]>Active</option>
    <option value="INACTIVE" [%funcjob=eqs;param=status;value=INACTIVE;true=selected;false=%]>Inactive</option>
</select>
```

**Date filter defaults:**
```html
<input type="text" name="date_from" value="$date_from~='[%funcjob=lastweek%]';">
<input type="text" name="date_to" value="$date_to~='[%funcjob=today%]';">
```

## Callback Function Implementation

```php
function callback_default(array $LFuncParams = []): mixed {
    $funcjob = Eisodos::$utils->safe_array_value($LFuncParams, 'funcjob');

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
            $LFuncParams, $paramValue,
            Eisodos::$utils->safe_array_value($LFuncParams, 'else')
        );
    }

    return '';
}
```

## Code Conventions

- Parameter names are always lowercase internally
- Config values support variable substitution: `$_applicationDir`, `$_applicationName`
- Template files use `.template` extension
- Template naming: `header.main.template` → `$templateabs_header_main`
- Language files are key=value pairs with `#` comments
- Always use `Eisodos::$utils->safe_array_value()` for array access
- Use `neq('param', '')` for empty checks instead of direct comparison
