# Render Class

Page generation and HTTP response handling.

**Namespace:** `Eisodos`
**Extends:** `Eisodos\Abstracts\Singleton`
**Source:** `src/Eisodos/Render.php`

## Overview

The `Render` class manages the complete page generation lifecycle, including initialization, response buffering, redirects, caching headers, performance metrics, and final output generation.

## Properties

| Property | Type | Description |
|----------|------|-------------|
| `$Response` | `string` | The generated page content buffer |

## Methods

### start(array $configOptions_, array $cacheOptions_ = [], array $templateEngineOptions_ = [], string $logLevel_ = ''): void

Main entry point that initializes all framework components and starts page rendering.

**Parameters:**
- `$configOptions_` - Configuration options (see ConfigLoader)
- `$cacheOptions_` - Cache options:
  - `disableHTMLCache` - Disable browser caching
- `$templateEngineOptions_` - Template engine options
- `$logLevel_` - Initial debug level

**Example:**
```php
Eisodos::$render->start(
    [
        'configPath' => './config',
        'configType' => ConfigLoader::CONFIG_TYPE_INI
    ],
    ['disableHTMLCache' => true],
    [],
    'debug'
);
```

### finish(): void

Completes page generation and outputs the response.

**Actions performed:**
1. Saves session variables
2. Generates final page output
3. Handles language collection
4. Writes debug request log (if enabled)
5. Closes session

**Example:**
```php
Eisodos::$templateEngine->getTemplate('page');
Eisodos::$render->finish();
```

### finishRaw(bool $saveSessionVariables_ = false, bool $handleLanguages_ = false): void

Finishes page generation with optional session/language handling.

**Parameters:**
- `$saveSessionVariables_` - Save session variables before finishing
- `$handleLanguages_` - Process language collection

### currentPageURL(): string

Returns the complete URL of the current page.

**Returns:** Full URL (e.g., `https://example.com/page?param=value`)

### storeCurrentURL(string $parameterName_): void

Stores the current URL in a session parameter.

**Parameters:**
- `$parameterName_` - Parameter name to store URL in

**Example:**
```php
Eisodos::$render->storeCurrentURL('returnUrl');
// Later: Eisodos::$parameterHandler->getParam('returnUrl')
```

### logout(bool $regenerateSessionId_ = true): void

Logs out the current user by clearing session data.

**Parameters:**
- `$regenerateSessionId_` - Generate new session ID

**Actions:**
1. Cleans parameter handler
2. Destroys current session
3. Starts new session
4. Optionally regenerates session ID

### pageDebugInfo(string $debugMessage_): void

Adds debug information to be included in the page output as HTML comment.

**Parameters:**
- `$debugMessage_` - Debug message to include

## Redirect Handling

### Temporary Redirect

```php
Eisodos::$parameterHandler->setParam('Redirect', '/new-page');
```

### Permanent Redirect (301)

```php
Eisodos::$parameterHandler->setParam('PermaRedirect', '/new-permanent-url');
```

## Page Generation Features

### Title Extraction

The renderer can automatically extract page titles from content:

```html
<h1>My Page Title</h1>
<!-- Will be placed in: -->
<title>%TITLE%</title>
```

**Configuration:**
- `TITLESTRING` - Placeholder for title (default: `%TITLE%`)
- `TITLEREPLACE` - HTML tag to extract title from
- `TITLEREPLACETAG` - Tag name for extraction
- `TITLECONCAT` - Append default title
- `TITLECUT` - Cut at first HTML tag

### Description Extraction

Similar extraction for meta descriptions:

```php
// Configuration
'DESCRIPTIONSTRING' => '%DESC%',
'DESCRIPTIONREPLACE' => '<p class="intro">'
```

### URL Decoding

Embedded URL-encoded text is decoded:

```html
{#a%3Db#}  <!-- Becomes: a=b -->
```

### Performance Metrics

When `INCLUDESTATISTIC` is enabled, performance information is appended:

```html
<!-- Memory usage: 2.5 MB (4 MB), Execution time: 0.0234 sec -->
```

## Configuration Parameters

| Parameter | Description |
|-----------|-------------|
| `ALWAYSNOCACHE` | Always send no-cache headers |
| `DEBUGURLPREFIX` | Prefix for debug URL parameters |
| `__SERVICEMODE` | Enable 503 service mode |
| `INCLUDESTATISTIC` | Include performance stats in output |
| `TITLESTRING` | Title placeholder |
| `TITLEREPLACE` | Title extraction source tag |
| `TITLEREPLACETAG` | Tag name for title extraction |
| `DESCRIPTIONSTRING` | Description placeholder |
| `DESCRIPTIONREPLACE` | Description extraction source |
| `SavePageToDisk` | Save generated page to file |
| `SaveFileName` | Filename for saved page |
| `DISABLECURLYBRACESREPLACE` | Disable `{{`/`}}` replacement |

## AJAX Detection

The renderer automatically detects AJAX requests:

```php
if (Eisodos::$parameterHandler->isOn('IsAJAXRequest')) {
    // Handle AJAX differently
}
```

## Service Mode

Enable service mode for maintenance:

```php
// In configuration
__SERVICEMODE=T
```

Returns HTTP 503 with `Retry-After: 300` header.

## Debug URL Parameters

When `DEBUGURLPREFIX` is configured (e.g., `_dbg_`), URL parameters enable debugging:

```
?_dbg_DebugLevel=trace&_dbg_RequestLog=T
```

## See Also

- [Eisodos](Eisodos.md) - Main framework class
- [TemplateEngine](TemplateEngine.md) - Template processing
- [ParameterHandler](ParameterHandler.md) - Parameter management
- [Logger](Logger.md) - Logging system
