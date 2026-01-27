# ParameterHandler Class

Centralized parameter and variable management with session, cookie, and input handling.

**Namespace:** `Eisodos`
**Extends:** `Eisodos\Abstracts\Singleton`
**Source:** `src/Eisodos/ParameterHandler.php`

## Overview

The `ParameterHandler` class manages all application parameters including input variables (GET/POST), session variables, cookies, and configuration values. It provides parameter filtering, validation, encryption/decryption, and re-post detection.

## Core Methods

### getParam(string $parameterName_, string $defaultValue_ = ''): mixed

Gets a parameter value.

```php
// Simple access
$username = Eisodos::$parameterHandler->getParam('username');

// With default value
$page = Eisodos::$parameterHandler->getParam('page', '1');

// Access environment variables
$tz = Eisodos::$parameterHandler->getParam('env_TZ');
```

### setParam(string $parameterName_, mixed $value_, bool $sessionStored_ = false, bool $cookieStored_ = false, string $source_ = ''): void

Sets a parameter value.

```php
// Simple parameter
Eisodos::$parameterHandler->setParam('status', 'active');

// Session-stored parameter
Eisodos::$parameterHandler->setParam('user_id', '123', true);

// Cookie-stored parameter
Eisodos::$parameterHandler->setParam('theme', 'dark', false, true);

// Readonly parameter (prefix with .)
Eisodos::$parameterHandler->setParam('.app_version', '1.0.0');

// Reference to another parameter (prefix with ^)
Eisodos::$parameterHandler->setParam('current_lang', '^default_lang');

// With source tracking
Eisodos::$parameterHandler->setParam('debug', 'T', false, false, 'eisodos::render');
```

## Real-World Usage Patterns

### Language and Locale Configuration

```php
<?php
// _init_parameters.php
use Eisodos\Eisodos;

// Set language to uppercase and store in session
if (Eisodos::$parameterHandler->neq("LANG", "")) {
    Eisodos::$parameterHandler->setParam(
        "LANG",
        strtoupper(Eisodos::$parameterHandler->getParam('LANG')),
        true  // Session stored
    );
}

// Map language-specific formats from configuration
Eisodos::$parameterHandler->setParam(
    "DateFormat",
    Eisodos::$parameterHandler->getParam(
        Eisodos::$parameterHandler->getParam("LANG") . ".DATEFORMAT"
    )
);

Eisodos::$parameterHandler->setParam(
    "JSDateFormat",
    Eisodos::$parameterHandler->getParam(
        Eisodos::$parameterHandler->getParam("LANG") . ".JSDATEFORMAT"
    )
);

Eisodos::$parameterHandler->setParam(
    "PHPDateFormat",
    Eisodos::$parameterHandler->getParam(
        Eisodos::$parameterHandler->getParam("LANG") . ".PHPDATEFORMAT"
    )
);

// Create lowercase variant
Eisodos::$parameterHandler->setParam(
    "LANG_SMALL",
    strtolower(Eisodos::$parameterHandler->getParam("LANG"))
);
```

### Hierarchical Configuration Access

Configuration often uses dot notation for namespacing:

```php
// Database configuration
$host = Eisodos::$parameterHandler->getParam('Database.hostname');
$user = Eisodos::$parameterHandler->getParam('Database.username');

// SMTP configuration
$smtpHost = Eisodos::$parameterHandler->getParam('SMTP.Host');
$smtpPort = Eisodos::$parameterHandler->getParam('SMTP.Port', '587');

// API configuration with fallback
$apiKey = Eisodos::$parameterHandler->getParam('onfido.api_key');
$profileId = Eisodos::$parameterHandler->getParam(
    'eszigno_profileId',
    Eisodos::$parameterHandler->getParam('eSzigno_Default_ProfileId')
);

// File handler configuration
$uploadPath = Eisodos::$parameterHandler->getParam('FileUploadPath');
$handlerUrl = Eisodos::$parameterHandler->getParam('FileHandler.URL');
$basePath = Eisodos::$parameterHandler->getParam('FileHandler.FILE.BasePath');
```

### JSON Data Handling

```php
// Parse JSON input parameter
$dataRaw = Eisodos::$parameterHandler->getParam('data');
$data = json_decode($dataRaw, true, 512, JSON_THROW_ON_ERROR);

// Validate required fields using safe_array_value
if (Eisodos::$utils->safe_array_value($data, 'from') === '') {
    throw new RuntimeException('Empty FROM field');
}

// Array parameters (file uploads, etc.)
$orderFiles = json_decode(
    Eisodos::$parameterHandler->getParam('ORDER_FILE', '[]'),
    true,
    512,
    JSON_THROW_ON_ERROR
);

// Store processed data back
Eisodos::$parameterHandler->setParam(
    'DATA',
    json_encode($processedData, JSON_THROW_ON_ERROR)
);
```

## Comparison Methods

### eq() and neq()

```php
// Simple equality check
if (Eisodos::$parameterHandler->eq('status', 'active')) {
    // Handle active status
}

// Not equal check (commonly used for empty check)
if (Eisodos::$parameterHandler->neq('error', '')) {
    // Handle error present
}

// Compare with another parameter using ^ prefix
if (Eisodos::$parameterHandler->eq('user_role', '^required_role')) {
    // user_role equals the value of required_role
}
```

### isOn() and isOff()

Check boolean-like values (`T`, `ON`, `1`, `TRUE`, `YES`, `Y` for on):

```php
// Check debug mode
if (Eisodos::$parameterHandler->isOn('DEBUG')) {
    // Debug mode enabled
}

// Check feature flag with default
if (Eisodos::$parameterHandler->isOn('NEW_FEATURE', 'F')) {
    // Feature enabled (defaults to off)
}

// Check if disabled
if (Eisodos::$parameterHandler->isOff('CACHE_ENABLED')) {
    // Cache is disabled
}

// Indirect parameter check (^ prefix)
if (Eisodos::$parameterHandler->isOn('^featureFlagName')) {
    // Check parameter whose name is in featureFlagName
}
```

## Built-in Dynamic Parameters

| Parameter | Description |
|-----------|-------------|
| `$seq` | Auto-incrementing sequence |
| `$seq0` | Reset sequence to 0 |
| `$seql` | Last sequence value |
| `$seqbit` | Sequence modulo 2 (0/1) |
| `$seq2`, `$seq20`, `$seq2l`, `$seq2bit` | Second sequence counter |
| `$currdate` | Current year |
| `$lnbr` | Line break (PHP_EOL) |
| `$_` | Underscore character |
| `$_sessionid` | Session ID |
| `$https` | Protocol (http/https) |
| `$random` | Random 8-char string |
| `$env_VARNAME` | Environment variable |

## Encryption Methods

### udSCode / udSDecode

For secure URL parameter transmission:

```php
// Encode sensitive data for URL
$encoded = Eisodos::$parameterHandler->udSCode('sensitive_data');
// Use in URL: ?token=$encoded

// Decode on receiving end
$decoded = Eisodos::$parameterHandler->udSDecode($encoded);
```

## Parameter Filtering

The `.params` file defines input parameter handling rules:

```
# Format: command;parametername;type;typeerror;errorlog

# Basic input parameters
input;username;text;;
input;page;numeric;;

# Regex validation with redirect on error
input;email;/^[a-z0-9@.]+$/i;/error;;

# Session-stored parameters
session;user_id;;;
session;logged_in_user_name;;;

# Protected parameters (require CSID validation)
protected;admin_action;;;

# Cookie with 30-day expiration
permanent;remember_me=30;;;

# Skip from processing (wildcards supported)
skip;temp_*;;;

# Excluded parameters (rejected)
exclude;dangerous_param;;;
```

**Commands:**
- `input` - Accept and validate
- `session` - Store in session
- `cookie` - Store as cookie
- `permanent` - Cookie with expiration (days)
- `protected` - Requires CSID verification
- `encoded` - Base64 decode on input
- `exclude` - Reject parameter
- `skip` - Don't load from session

## File Handler Parameters

Common pattern for file operations:

```php
// Set file operation parameters
Eisodos::$parameterHandler->setParam('SourceType', 'FILE');  // FILE, STDIN, PARAMETER, URL, OBJECT
Eisodos::$parameterHandler->setParam('SourceFileName', '/path/to/file.pdf');
Eisodos::$parameterHandler->setParam('SourceFormat', 'RAW');  // RAW, BASE64

Eisodos::$parameterHandler->setParam('TargetType', 'OBJECT');
Eisodos::$parameterHandler->setParam('TargetObject', 'SAL_INVOICES');
Eisodos::$parameterHandler->setParam('FileOperation', 'create');  // create, read, move, copy, delete

Eisodos::$parameterHandler->setParam('ResponseType', 'JSON');  // JSON, RAW
```

## Re-post Detection

The handler automatically detects form re-submissions:

```php
// Check for duplicate form submission
if (Eisodos::$parameterHandler->isOn('RePost')) {
    // This is a repeated form submission - handle accordingly
    return;
}

// Check for page reload with same parameters
if (Eisodos::$parameterHandler->isOn('Reload')) {
    // Page was reloaded with same parameters
}
```

## Session Management

```php
// Store user data in session
Eisodos::$parameterHandler->setParam('logged_in_user_name', $userName, true);
Eisodos::$parameterHandler->setParam('logged_in_user_code', $userCode, true);
Eisodos::$parameterHandler->setParam('logged_in_contract_name', $contractName, true);

// Access session data
$userName = Eisodos::$parameterHandler->getParam('logged_in_user_name');

// Clear session (via Render)
Eisodos::$render->logout(false);  // false = don't regenerate session ID

// Redirect after logout
Eisodos::$parameterHandler->setParam('REDIRECT', '/login/');
```

## Cookie Configuration

| Parameter | Description |
|-----------|-------------|
| `COOKIE_DOMAIN` | Cookie domain (comma-separated for multiple) |
| `COOKIE_PATH` | Cookie path |
| `COOKIE_SECURE` | HTTPS only (`T`/`F`) |
| `COOKIE_HTTPONLY` | HTTP only - no JS access (`T`/`F`) |
| `COOKIE_SAMESITE` | SameSite policy (`None`/`Lax`/`Strict`) |
| `RAWCOOKIES` | Comma-separated list of raw (not URL-encoded) cookies |

## Utility Methods

### getParamNames()

Get parameter names matching a pattern:

```php
// Find all user-related parameters
$userParams = Eisodos::$parameterHandler->getParamNames('/^user_/');

// Find all configuration parameters
$configParams = Eisodos::$parameterHandler->getParamNames('/^Config\./');
```

### params2log()

Generate log-friendly parameter dump:

```php
$logOutput = Eisodos::$parameterHandler->params2log();
// Output: [source] (flag) param=value for each parameter
```

### getParameterArray() / mergeParameterArray()

Direct access to parameter storage:

```php
// Get all parameters
$allParams = Eisodos::$parameterHandler->getParameterArray();

// Merge external parameters
Eisodos::$parameterHandler->mergeParameterArray($externalParams);
```

## Security Features

1. **Null byte injection protection** - Removes chr(0) from all input
2. **Type validation** - Numeric and regex validation via `.params`
3. **Protected parameters** - CSID verification for sensitive params
4. **Encryption** - udSCode/udSDecode for secure transmission
5. **Parameter filtering** - Whitelist/blacklist approach
6. **Readonly parameters** - Prefix with `.` to prevent overwrite

## Best Practices

1. **Always use `neq('param', '')` for empty checks** - More reliable than direct comparison
2. **Use `safe_array_value` for array access** - Prevents undefined key warnings
3. **Store user session data with source tracking** - Aids debugging
4. **Use hierarchical naming** - e.g., `SMTP.Host`, `Database.username`
5. **Define all input parameters in `.params`** - Ensures validation and documentation

## See Also

- [Eisodos](Eisodos.md) - Main framework class
- [ConfigLoader](ConfigLoader.md) - Configuration loading
- [Utils](Utils.md) - safe_array_value and other utilities
