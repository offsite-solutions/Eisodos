# CallbackFunctionParser Class

Parser for callback function blocks in templates.

**Namespace:** `Eisodos\Parsers`
**Implements:** `Eisodos\Interfaces\ParserInterface`
**Source:** `src/Eisodos/Parsers/CallbackFunctionParser.php`

## Overview

The `CallbackFunctionParser` processes `<%FUNC%...%FUNC%>` blocks in templates, allowing execution of PHP callback functions with structured parameters. It supports includes, parameter prefixes, nested function calls, and multi-line parameter values.

## Parser Interface

| Method | Value |
|--------|-------|
| `openTag()` | `<%FUNC%` |
| `closeTag()` | `%FUNC%>` |
| `enabled()` | Always `true` |

## Basic Syntax

```html
<%FUNC%
_function_name=myCallback
param1=value1
param2=value2
%FUNC%>
```

## Special Directives

| Directive | Description |
|-----------|-------------|
| `_include` | PHP file to include before executing |
| `_function_name` | Function to call (uses default if empty) |
| `_parameter_prefix` | Prefix for parameter names in global scope |
| `_real_parameters` | Use array values as function arguments (`T`/`Y`/`1`) |

## Parameter Syntax

### Simple Parameters

```html
<%FUNC%
_function_name=processUser
name=John Doe
email=john@example.com
age=30
%FUNC%>
```

### Parsed Parameters

Prefix with `@` to evaluate parameter variables:

```html
<%FUNC%
_function_name=showUser
@username=$currentUser
@count=$totalItems
%FUNC%>
```

### Multi-line Parameters

Use `delimiter>>=` and `<<` for multi-line values:

```html
<%FUNC%
_function_name=sendEmail
subject=Welcome
HTML>>=
<html>
<body>
<h1>Hello World</h1>
<p>Welcome to our site!</p>
</body>
</html>
<<
%FUNC%>
```

### Parsed Multi-line Parameters

```html
<%FUNC%
_function_name=renderTemplate
@CONTENT>>=
Hello $username,
Your balance is $balance.
<<
%FUNC%>
```

## Function Calling Modes

### Default Callback

When `_function_name` is not specified, uses `Eisodos::$templateEngine->defaultCallbackFunctionName`:

```php
// Set default callback
Eisodos::$templateEngine->setDefaultCallbackFunction('myDefaultHandler');
```

```html
<%FUNC%
action=list
type=users
%FUNC%>
```

Called as:
```php
myDefaultHandler(['action' => 'list', 'type' => 'users'], '');
```

### Named Function

```html
<%FUNC%
_function_name=processOrder
orderId=12345
%FUNC%>
```

Called as:
```php
processOrder(['orderId' => '12345'], '');
```

### Static Method

```html
<%FUNC%
_function_name=MyClass::staticMethod
param=value
%FUNC%>
```

### Real Parameters Mode

With `_real_parameters=T`, array values become function arguments:

```html
<%FUNC%
_function_name=customFunc
_real_parameters=T
arg1=first
arg2=second
arg3=third
%FUNC%>
```

Called as:
```php
customFunc('first', 'second', 'third', '');  // Last arg is prefix
```

## Include Files

```html
<%FUNC%
_include=/path/to/functions.php
_function_name=customFunction
param=value
%FUNC%>
```

## Parameter Prefix

Store parameters globally with a prefix:

```html
<%FUNC%
_function_name=processForm
_parameter_prefix=form1
username=john
email=john@example.com
%FUNC%>
```

Creates global parameters:
- `form1_username` = `john`
- `form1_email` = `john@example.com`

## Nested Blocks

Blocks can be nested (with proper tag matching):

```html
<%FUNC%
_function_name=outer
content=<%FUNC%
_function_name=inner
value=nested
%FUNC%>
%FUNC%>
```

## Callback Function Signature

### Standard Mode

```php
function myCallback(array $params, string $prefix): string {
    // $params = ['param1' => 'value1', 'param2' => 'value2']
    // $prefix = '' or value of _parameter_prefix
    return 'Output HTML';
}
```

### Real Parameters Mode

```php
function myCallback($arg1, $arg2, $arg3, string $prefix): string {
    return "Args: $arg1, $arg2, $arg3";
}
```

## Complete Example

### Template

```html
<div class="user-list">
<%FUNC%
_function_name=UserController::renderList
_parameter_prefix=userlist
@filter=$currentFilter
pageSize=10
template>>=
<div class="user">
    <span class="name">$name</span>
    <span class="email">$email</span>
</div>
<<
%FUNC%>
</div>
```

### PHP Handler

```php
class UserController {
    public static function renderList(array $params, string $prefix): string {
        $filter = $params['filter'] ?? '';
        $pageSize = (int)($params['pageSize'] ?? 20);
        $template = $params['template'] ?? '';

        $users = self::getUsers($filter, $pageSize);
        $html = '';

        foreach ($users as $user) {
            Eisodos::$parameterHandler->setParam('name', $user['name']);
            Eisodos::$parameterHandler->setParam('email', $user['email']);
            $html .= Eisodos::$templateEngine->parseTemplateText($template, [], false);
        }

        return $html;
    }
}
```

## Error Handling

If an error occurs during parsing or execution, an HTML comment is returned:

```html
<!-- Error in include: Error message here -->
```

## Configuration

Set the default callback function:

```php
Eisodos::$templateEngine->setDefaultCallbackFunction('myAppCallback');
```

## See Also

- [CallbackFunctionShortParser](CallbackFunctionShortParser.md) - Short syntax version
- [TemplateEngine](TemplateEngine.md) - Template processing
- [ParserInterface](ParserInterface.md) - Parser interface
