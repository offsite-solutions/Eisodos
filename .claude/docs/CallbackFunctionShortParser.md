# CallbackFunctionShortParser Class

Shorthand parser for callback function blocks.

**Namespace:** `Eisodos\Parsers`
**Extends:** `Eisodos\Parsers\CallbackFunctionParser`
**Source:** `src/Eisodos/Parsers/CallbackFunctionShortParser.php`

## Overview

The `CallbackFunctionShortParser` provides a compact syntax for callback function blocks using `[%...%]` tags. It converts the short syntax to the full `<%FUNC%...%FUNC%>` format and delegates to the parent `CallbackFunctionParser`.

## Parser Interface

| Method | Value |
|--------|-------|
| `openTag()` | `[%` |
| `closeTag()` | `%]` |
| `enabled()` | Always `true` (inherited) |

## Syntax

```html
[%param1=value1;param2=value2;param3=value3%]
```

## Real-World Callback Patterns

Based on production usage, the following `funcjob` patterns are commonly implemented:

### Conditional Functions

#### `eq` - Equality Check (Returns Template)

Returns a template based on parameter equality.

```html
[%funcjob=eq;param=APP_SEARCH_URL;value=;false=header.search;true=empty%]
```

**Parameters:**
- `param` - Parameter name to check
- `value` - Value to compare against
- `true` - Template to render when equal
- `false` - Template to render when not equal

#### `eqs` - Equality Check (Returns String)

Returns a string value based on parameter equality.

```html
[%funcjob=eqs;param=prop_value;value=Y;true=text-success;false=text-gray-300%]
```

**Common use cases:**
```html
<!-- CSS class based on boolean -->
<span class="[%funcjob=eqs;param=is_active;value=Y;true=text-success;false=text-danger%]">
    Status
</span>

<!-- Icon selection -->
<i class="fa fa-[%funcjob=eqs;param=approved;value=Y;true=check-circle;false=times-circle%]"></i>

<!-- Yes/No text with translation -->
[%funcjob=eqs;param=prop_value;value=Y;true=[:GRID.YES,Yes:];false=[:GRID.NO,No:]%]
```

### Switch/Case Functions

#### `case` - Case Statement (Returns Template)

Returns a template based on parameter value (like switch/case).

```html
[%funcjob=case;param=page_type;HOME=page.home;ABOUT=page.about;else=page.default%]
```

#### `cases` - Case Statement (Returns String)

Returns a string value based on parameter value.

```html
[%funcjob=cases;param=status;NEW=gray-500;SUSPENDED=darkorange;ACTIVE=success;DELETED=danger;else=primary%]
```

**Common use cases:**
```html
<!-- Status color coding -->
<span class="text-[%funcjob=cases;param=status;ACTIVE=success;INACTIVE=warning;DELETED=danger;else=secondary%]">
    $status
</span>

<!-- Dynamic icon based on status -->
<i class="fa fa-[%funcjob=cases;param=status;NEW=circle-thin;ACTIVE=play-circle;SUSPENDED=pause-circle;DELETED=times-circle;else=question-circle%]"></i>
```

### Date/Time Functions

```html
<!-- Current date/time -->
[%funcjob=now%]           <!-- Full datetime -->
[%funcjob=nowhm%]         <!-- Datetime without seconds -->
[%funcjob=today%]         <!-- Date only -->
[%funcjob=today0%]        <!-- Today at 00:00:00 -->
[%funcjob=todayhm%]       <!-- Today at 00:00 (no seconds) -->

<!-- Date calculations -->
[%funcjob=lastweek%]      <!-- One week ago -->
[%funcjob=lastweek0%]     <!-- One week ago at 00:00 -->
[%funcjob=lasthour%]      <!-- One hour ago -->
[%funcjob=lasthourhm%]    <!-- One hour ago (no seconds) -->
[%funcjob=lastyear%]      <!-- One year ago -->
[%funcjob=currentmonth%]  <!-- First day of current month -->
[%funcjob=0day%]          <!-- 1900-01-01 (null date) -->

<!-- Raw date formats -->
[%funcjob=ymd%]           <!-- YYYYMMDD -->
[%funcjob=ymdhis%]        <!-- YYYYMMDDHHIISS -->
```

### Utility Functions

#### HTML Detection

```html
[%funcjob=ishtml;param=content;true=html-content;false=text-content%]
```

#### Star Rating

```html
[%funcjob=stars;parameter=rating_value%]
<!-- Outputs: ★★★☆☆ (3) -->
```

## Complete Callback Implementation

Here's a production-ready callback function implementing all patterns:

```php
<?php
use Eisodos\Eisodos;

function callback_default(array $LFuncParams = []): mixed {
    $funcjob = Eisodos::$utils->safe_array_value($LFuncParams, 'funcjob');

    // EQUALITY CHECK - returns template
    if ($funcjob === 'eq') {
        if (Eisodos::$parameterHandler->eq(
            Eisodos::$utils->safe_array_value($LFuncParams, 'param'),
            Eisodos::$utils->safe_array_value($LFuncParams, 'value')
        )) {
            return Eisodos::$templateEngine->getTemplate(
                Eisodos::$utils->safe_array_value($LFuncParams, 'true'),
                [],
                false
            );
        }
        return Eisodos::$templateEngine->getTemplate(
            Eisodos::$utils->safe_array_value($LFuncParams, 'false'),
            [],
            false
        );
    }

    // EQUALITY STRING - returns string
    if ($funcjob === 'eqs') {
        if (Eisodos::$parameterHandler->eq(
            Eisodos::$utils->safe_array_value($LFuncParams, 'param'),
            Eisodos::$utils->safe_array_value($LFuncParams, 'value')
        )) {
            return Eisodos::$utils->safe_array_value($LFuncParams, 'true');
        }
        return Eisodos::$utils->safe_array_value($LFuncParams, 'false');
    }

    // CASE/SWITCH - returns template
    if ($funcjob === 'case') {
        $paramValue = Eisodos::$parameterHandler->getParam(
            Eisodos::$utils->safe_array_value($LFuncParams, 'param')
        );
        $template = Eisodos::$utils->safe_array_value(
            $LFuncParams,
            $paramValue,
            Eisodos::$utils->safe_array_value($LFuncParams, 'else')
        );
        return Eisodos::$templateEngine->getTemplate($template, [], false);
    }

    // CASE STRING - returns string value
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

    // DATE/TIME FUNCTIONS
    $phpDateFormat = Eisodos::$parameterHandler->getParam('PHPDateFormat', 'Y-m-d');
    $phpDateTimeFormat = Eisodos::$parameterHandler->getParam('PHPDateTimeFormat', 'Y-m-d H:i:s');
    $phpDateTimeHMFormat = Eisodos::$parameterHandler->getParam('PHPDateTimeHMFormat', 'Y-m-d H:i');

    switch ($funcjob) {
        case 'now':
            return (new DateTime())->format($phpDateTimeFormat);
        case 'nowhm':
            return (new DateTime())->format($phpDateTimeHMFormat);
        case 'today':
            return (new DateTime())->format($phpDateFormat);
        case 'today0':
            $now = new DateTime();
            $now->setTime(0, 0);
            return $now->format($phpDateTimeFormat);
        case 'todayhm':
            $now = new DateTime();
            $now->setTime(0, 0);
            return $now->format($phpDateTimeHMFormat);
        case 'lastweek':
            return (new DateTime())->modify('-1 week')->format($phpDateFormat);
        case 'lastweek0':
            return (new DateTime())->modify('-1 week')->format($phpDateTimeHMFormat);
        case 'lasthour':
            return (new DateTime())->modify('-1 hour')->format($phpDateTimeFormat);
        case 'lasthourhm':
            return (new DateTime())->modify('-1 hour')->format($phpDateTimeHMFormat);
        case 'lastyear':
            return (new DateTime())->modify('-1 year')->format($phpDateFormat);
        case 'currentmonth':
            return (new DateTime('first day of this month'))->format($phpDateFormat);
        case '0day':
            return (new DateTime('1900-01-01'))->format($phpDateFormat);
        case 'ymd':
            return date('Ymd');
        case 'ymdhis':
            return date('YmdHis');
    }

    // HTML DETECTION
    if ($funcjob === 'ishtml') {
        $content = Eisodos::$parameterHandler->getParam(
            Eisodos::$utils->safe_array_value($LFuncParams, 'param')
        );
        if (preg_match('/<[^<]+>/', $content) !== 0) {
            return Eisodos::$utils->safe_array_value($LFuncParams, 'true');
        }
        return Eisodos::$utils->safe_array_value($LFuncParams, 'false');
    }

    // STAR RATING
    if ($funcjob === 'stars') {
        $value = (int)Eisodos::$parameterHandler->getParam(
            Eisodos::$utils->safe_array_value($LFuncParams, 'parameter'),
            '0'
        );
        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            $stars .= $value >= $i
                ? '<i class="fa-solid fa-star"></i>'
                : '<i class="fa-regular fa-star text-gray"></i>';
        }
        return '<span style="font-size: 18pt; color: orange;">' . $stars . '</span> (' . $value . ')';
    }

    return '';
}
```

## Template Examples

### Grid Column - Boolean Display

```html
<!-- grid.column.bool.template -->
<span class="[%funcjob=eqs;param=prop_value;value=Y;true=text-success;false=text-gray-300%]">
    <i class="fa fa-[%funcjob=eqs;param=prop_value;value=Y;true=check-circle;false=minus-circle%]">&nbsp;</i>
    [%funcjob=eqs;param=prop_value;value=Y;true=[:GRID.YES,Yes:];false=[:GRID.NO,No:]%]
</span>
```

### Grid Column - Status with Icons

```html
<!-- grid.column.status.template -->
<span class="text-[%funcjob=cases;param=prop_value;NEW=gray-500;SUSPENDED=darkorange;ACTIVE=success;DELETED=danger;REGISTERED=primary;else=secondary%]">
    <i class="fa fa-[%funcjob=cases;param=prop_value;NEW=circle-thin;SUSPENDED=pause-circle;ACTIVE=play-circle;DELETED=times-circle;REGISTERED=globe;else=question-circle%]"></i>
    $prop_value
</span>
```

### Header with Conditional Search

```html
<!-- header.main.template -->
<div class="app-navbar">
    $templateabs_header_spinner
    [%funcjob=eq;param=APP_SEARCH_URL;value=;false=header.search;true=empty%]
    $templateabs_header_user
    <div class="badge badge-$app_env_color">$app_env</div>
</div>
```

### Date Filter Defaults

```html
<!-- filter.daterange.template -->
<input type="text" name="date_from" value="$date_from~='[%funcjob=lastweek%]';">
<input type="text" name="date_to" value="$date_to~='[%funcjob=today%]';">
```

## Registration

```php
// In __eisodos.php or _init.php
require_once(__DIR__ . '/_callbacks.php');
Eisodos::$templateEngine->setDefaultCallbackFunction('callback_default');
```

## Best Practices

1. **Use `safe_array_value`** for all parameter access to prevent undefined key warnings
2. **Provide fallback values** using the `else` parameter in case/cases
3. **Configure date formats** via parameters for localization support
4. **Keep callback functions simple** - complex logic belongs in modules
5. **Use `eqs` for CSS classes** and `eq` for template switching

## See Also

- [CallbackFunctionParser](CallbackFunctionParser.md) - Full syntax parser
- [TemplateEngine](TemplateEngine.md) - Template processing
- [ParserInterface](ParserInterface.md) - Parser interface
- [ParameterHandler](ParameterHandler.md) - Parameter access patterns
