# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Eisodos is a PHP 8.4+ page generation framework with singleton-based architecture. It provides configuration management, template processing, internationalization, logging, and database abstraction.

## Running Tests

Tests are located in `tests/` and are standalone PHP scripts (no PHPUnit):

```bash
# Run a specific test
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

### Initialization Flow

```php
Eisodos::getInstance()->init([__DIR__, 'applicationName']);
Eisodos::$render->start($configOptions, $cacheOptions, $templateOptions, $logLevel);
// ... application code ...
Eisodos::$render->finish();
```

### Key Modules

- **ParameterHandler** (882 LOC): Merges config, session, cookies, GET/POST into unified parameter store. All parameter names are lowercase. Supports readonly params from config, re-post detection, and dynamic params (`$seq`, `$random`, `$date`).

- **ConfigLoader** (393 LOC): Loads INI or JSON configs. Supports environment-specific files (`{env}-{appname}.conf`), sections (`[Config]`, `[PreInclude]`, `[PostInclude]`, `[Env]`), and caching.

- **TemplateEngine** (569 LOC): Loads `.template` files with language fallback. Supports parameter embedding (`$paramName`), defaults (`$param~='default'`), template nesting (`$template_id`), and callback parsers.

- **Parser System**: Extensible via `ParserInterface`. Built-in parsers:
  - `CallbackFunctionParser`: `<%FUNC%...%FUNC%>` blocks
  - `CallbackFunctionShortParser`: `[%function;param=value%]` syntax

- **DBConnectors**: Registry for database connectors implementing `DBConnectorInterface`. Multiple connections supported via keys.

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
tests/
├── config/                       # Test configuration files
├── templates/                    # Test template files
└── test_*.php                    # Test scripts
```

## Configuration

Config files are INI or JSON format with sections. Environment-specific files are loaded first if they exist (`{env}-{appname}.conf`).

Key sections:
- `[Config]` - Main parameters (loaded into ParameterHandler)
- `[PreInclude]` / `[PostInclude]` - Config file includes
- `[Env]` - Environment variables to set

Key parameters:
- `TemplateDir` - Root template directory
- `DebugLevel` - trace/debug/info/warning/error/critical
- `ErrorOutput` - Screen, File, Mail, or @callback
- `Langs`, `DefLang`, `LangIDFile` - Translation settings

## Template Syntax

```
$parameterName              # Parameter reference
$param~='default'           # With default value
$template_id                # Embed another template
$templateabs_path_file      # Absolute path template, example $templateabs_content_404 embed content.404.template from templates directory
<%FUNC%name=value%FUNC%>    # Callback function block
[%func;p1=v1;p2=v2%]        # Short callback syntax
[:LANG_ID,default:]         # Language ID translation
##comment line              # Template comment (removed in generation step)
```

## Code Conventions

- Parameter names are always lowercase internally
- Config values support variable substitution: `$_applicationDir`, `$_applicationName`
- Template files use `.template` extension
- Language files are key=value pairs with `#` comments
