# Utils Class

Utility functions for common operations.

**Namespace:** `Eisodos`
**Extends:** `Eisodos\Abstracts\Singleton`
**Source:** `src/Eisodos/Utils.php`

## Overview

The `Utils` class provides a collection of utility functions for safe array access, string manipulation, type checking, UUID generation, and HTTP header handling.

## Methods

### safe_array_value(?array $array_, string $key_, string $defaultValue_ = '', bool $caseInsensitive_ = false): string|array

Safely gets a value from an array with a default fallback.

**Parameters:**
- `$array_` - Source array (can be null)
- `$key_` - Key to look up
- `$defaultValue_` - Value to return if key doesn't exist or value is empty/null
- `$caseInsensitive_` - Case-insensitive key lookup

**Returns:** Array value or default

**Example:**
```php
$name = Eisodos::$utils->safe_array_value($_POST, 'username', 'Guest');
$config = Eisodos::$utils->safe_array_value($settings, 'timeout', '30', true);
```

### replace_all(string $InString, string $SearchFor, string $ReplaceTo, bool $All = true, bool $NoCase = true): string

Replaces occurrences in a string with various options.

**Parameters:**
- `$InString` - Source string
- `$SearchFor` - String to find
- `$ReplaceTo` - Replacement string
- `$All` - Replace all occurrences (true) or just first (false)
- `$NoCase` - Case-insensitive search

**Returns:** Modified string

**Example:**
```php
// Replace all, case-insensitive
$result = Eisodos::$utils->replace_all('Hello WORLD', 'world', 'PHP');
// Result: Hello PHP

// Replace first only, case-sensitive
$result = Eisodos::$utils->replace_all('foo foo foo', 'foo', 'bar', false, false);
// Result: bar foo foo
```

### str_replace_count(string $search, string $replace, string $subject, int $times): string

Replaces a specified number of occurrences (case-sensitive).

**Parameters:**
- `$search` - String to find
- `$replace` - Replacement string
- `$subject` - Source string
- `$times` - Number of replacements to make

**Example:**
```php
$result = Eisodos::$utils->str_replace_count('a', 'X', 'abracadabra', 3);
// Result: XbrXcXdabra
```

### str_ireplace_count(string $search, string $replace, string $subject, int $times): string

Replaces a specified number of occurrences (case-insensitive).

### isInteger(mixed $mixed, bool $allowNegative = false): bool

Checks if a value is an integer (string or numeric).

**Parameters:**
- `$mixed` - Value to check
- `$allowNegative` - Allow negative numbers

**Example:**
```php
Eisodos::$utils->isInteger('123');      // true
Eisodos::$utils->isInteger('-123');     // false
Eisodos::$utils->isInteger('-123', true); // true
Eisodos::$utils->isInteger('12.3');     // false
```

### isFloat(mixed $mixed, bool $allowNegative = false): bool

Checks if a value is a float (string or numeric).

**Parameters:**
- `$mixed` - Value to check
- `$allowNegative` - Allow negative numbers

**Example:**
```php
Eisodos::$utils->isFloat('12.34');       // true
Eisodos::$utils->isFloat('-12.34');      // false
Eisodos::$utils->isFloat('-12.34', true); // true
```

### generateUUID(): string

Generates a RFC 4122 compliant UUID v4.

**Returns:** UUID string (e.g., `550e8400-e29b-41d4-a716-446655440000`)

**Example:**
```php
$uuid = Eisodos::$utils->generateUUID();
// e.g., "a1b2c3d4-e5f6-4789-abcd-ef0123456789"
```

### ODecode(array $listOfValuePairs_ = []): mixed

Oracle-like DECODE function for conditional value mapping.

**Parameters:**
- `$listOfValuePairs_` - Array in format: `[value, match1, result1, match2, result2, ..., default]`

**Returns:** Matched result or default

**Example:**
```php
// DECODE(status, 'A', 'Active', 'I', 'Inactive', 'Unknown')
$result = Eisodos::$utils->ODecode(['A', 'A', 'Active', 'I', 'Inactive', 'Unknown']);
// Result: 'Active'

$result = Eisodos::$utils->ODecode(['X', 'A', 'Active', 'I', 'Inactive', 'Unknown']);
// Result: 'Unknown'

// With $0 placeholder in default
$result = Eisodos::$utils->ODecode(['custom', 'A', 'Active', 'Value: $0']);
// Result: 'Value: custom'
```

### _strpos_offset(string $needle, string $haystack, int $occurrence): bool|int

Finds the position of the Nth occurrence of a substring.

**Parameters:**
- `$needle` - String to find
- `$haystack` - String to search in
- `$occurrence` - Which occurrence (1-based)

**Returns:** Position or `false`

**Example:**
```php
$pos = Eisodos::$utils->_strpos_offset('a', 'abracadabra', 3);
// Result: 5 (position of third 'a')
```

### get_request_headers(): array

Gets HTTP request headers (works with Apache and other servers).

**Returns:** Associative array of headers

**Example:**
```php
$headers = Eisodos::$utils->get_request_headers();
// ['Content-Type' => 'application/json', 'Accept-Language' => 'en-US', ...]
```

### removeDuplicatePHPSessionCookies(): void

Removes duplicate PHP session cookies from the response headers.

This is useful to prevent multiple `Set-Cookie` headers for the session cookie, which can cause issues with some browsers.

## Usage Examples

### Safe Array Access

```php
// Configuration with defaults
$timeout = Eisodos::$utils->safe_array_value($config, 'timeout', '30');
$retries = Eisodos::$utils->safe_array_value($config, 'retries', '3');

// POST data with empty check
$email = Eisodos::$utils->safe_array_value($_POST, 'email', '');
if ($email === '') {
    // Handle missing email
}
```

### String Manipulation

```php
// Template variable replacement
$template = 'Hello {{NAME}}, welcome to {{SITE}}!';
$template = Eisodos::$utils->replace_all($template, '{{NAME}}', 'John');
$template = Eisodos::$utils->replace_all($template, '{{SITE}}', 'MyApp');
```

### Type Validation

```php
function processPage($page) {
    if (!Eisodos::$utils->isInteger($page)) {
        $page = 1;
    }
    return (int)$page;
}
```

### Conditional Value Mapping

```php
// Map status codes to labels
$statusLabel = Eisodos::$utils->ODecode([
    $statusCode,
    'P', 'Pending',
    'A', 'Approved',
    'R', 'Rejected',
    'Unknown Status'
]);
```

## See Also

- [Eisodos](Eisodos.md) - Main framework class
- [ParameterHandler](ParameterHandler.md) - Parameter management
