# TemplateEngine Class

Template processing engine with variable replacement and parser plugin system.

**Namespace:** `Eisodos`
**Extends:** `Eisodos\Abstracts\Singleton`
**Source:** `src/Eisodos/TemplateEngine.php`

## Overview

The `TemplateEngine` class handles loading, parsing, and processing of template files. It supports variable replacement, template caching, version-aware template loading, and a plugin system for custom block parsers.

## Properties

| Property | Type | Description |
|----------|------|-------------|
| `$defaultCallbackFunctionName` | `string` | Default callback function for `$callback_` parameters |

## Core Methods

### getTemplate()

Reads and parses a template file.

```php
// Load template and add to response
Eisodos::$templateEngine->getTemplate('page.main');

// Load template and return content (don't add to response)
$html = Eisodos::$templateEngine->getTemplate('email.body', [], false);

// Load with parameters
$html = Eisodos::$templateEngine->getTemplate('user.card', [
    'name' => 'John',
    'email' => 'john@example.com'
], false);
```

### addToResponse()

Add content directly to response buffer:

```php
Eisodos::$templateEngine->addToResponse('<div>Custom content</div>');
Eisodos::$templateEngine->addToResponse(json_encode($data));
```

### setDefaultCallbackFunction()

Register the default callback for `[%...%]` blocks:

```php
Eisodos::$templateEngine->setDefaultCallbackFunction('callback_default');
```

### registerParser()

Register a custom block parser:

```php
Eisodos::$templateEngine->registerParser(new MyCustomParser());
```

## Template Syntax

### Variable Replacement

```html
<!-- Simple variable -->
<p>Hello, $username!</p>

<!-- Variable with default value -->
<p>Welcome, $username~='Guest';!</p>

<!-- Variable assignment (sets parameter) -->
$counter:='0';
$status:='active';

<!-- Environment variable -->
<p>Timezone: $env_TZ</p>
```

### Absolute Template Include

Embed templates from the template directory using the `$templateabs_template_name` where from second underscore is replaced with a dot:

Example: `header.main.template` becomes `$templateabs_header_main`

```html
<!-- Include header template -->
$templateabs_header_main

<!-- Include sidebar -->
$templateabs_sidebar_main

<!-- Dynamic template based on parameter -->
$templateabs_page_$page_type
```

### Built-in Dynamic Variables

```html
<!-- Sequences for alternating rows -->
<tr class="row-$seqbit">  <!-- Alternates 0, 1, 0, 1... -->

<!-- Reset sequence -->
$seq0

<!-- Current values -->
<span>Item $seq of $seql</span>

<!-- Date/Time -->
<footer>&copy; $currdate All rights reserved</footer>

<!-- Session ID -->
<input type="hidden" name="sid" value="$_sessionid">

<!-- Protocol detection -->
<a href="$https://example.com">Link</a>

<!-- Random string -->
<input type="hidden" name="token" value="$random">

<!-- Line break -->
<pre>Line 1$lnbrLine 2</pre>
```

## Real-World Template Patterns

### Main Page Layout

```html
<!-- main.template -->
<!DOCTYPE html>
<html>
<head>
    <title>%TITLE%</title>
    $page_headitems
</head>
<body>
    <header>
        $templateabs_header_main
    </header>

    <nav>
        $templateabs_sidebar_main
    </nav>

    <main>
        $content
    </main>

    <footer>
        $templateabs_footer_main
    </footer>

    $page_footitems
</body>
</html>
```

### Header with Conditionals

```html
<!-- header.main.template -->
<div class="app-navbar flex-shrink-0">
    $templateabs_header_spinner

    <!-- Conditionally show search -->
    [%funcjob=eq;param=APP_SEARCH_URL;value=;false=header.search;true=empty%]

    $templateabs_header_user

    <!-- Environment badge -->
    <div class="app-navbar-item ms-1 ms-md-4 me-3">
        <span class="badge badge-lg badge-$app_env_color">$app_env</span>
    </div>
</div>
```

### Grid Column Templates

**Boolean Display:**
```html
<!-- grid.column.bool.template -->
<span class="[%funcjob=eqs;param=prop_value;value=Y;true=text-success;false=text-gray-300%]">
    <i class="fa fa-[%funcjob=eqs;param=prop_value;value=Y;true=check-circle;false=minus-circle%]">&nbsp;</i>
    [%funcjob=eqs;param=prop_value;value=Y;true=[:GRID.YES,Yes:];false=[:GRID.NO,No:]%]
</span>
```

**Status with Color Coding:**
```html
<!-- grid.column.status.template -->
<span class="text-[%funcjob=cases;param=prop_value;NEW=gray-500;SUSPENDED=darkorange;ACTIVE=success;DELETED=danger;REGISTERED=primary;WAITING_FOR_APPROVAL=black;else=primary%]">
    <i class="fa fa-[%funcjob=cases;param=prop_value;NEW=circle-thin;SUSPENDED=pause-circle;ACTIVE=play-circle;DELETED=times-circle;REGISTERED=globe;WAITING_FOR_APPROVAL=hourglass-half;else=question-circle%]"></i>
</span>
```

**Color Indicator:**
```html
<!-- grid.column.color.template -->
<span class="text-$prop_value">
    <i class="fa fa-circle"></i>
</span>
```

**Email Link:**
```html
<!-- grid.column.email.template -->
<a href="mailto:$prop_value">$prop_value</a>
```

### Form with Defaults

```html
<!-- filter.form.template -->
<form method="get" action="$CGI">
    <input type="text"
           name="date_from"
           value="$date_from~='[%funcjob=lastweek%]';">

    <input type="text"
           name="date_to"
           value="$date_to~='[%funcjob=today%]';">

    <select name="status">
        <option value="">All</option>
        <option value="ACTIVE" [%funcjob=eqs;param=status;value=ACTIVE;true=selected;false=%]>Active</option>
        <option value="INACTIVE" [%funcjob=eqs;param=status;value=INACTIVE;true=selected;false=%]>Inactive</option>
    </select>

    <button type="submit">Filter</button>
</form>
```

### Language Domain Links

```html
<!-- userlang.edit.domain.template -->
<a href="$CGI?langdomain=$value" class="langdomain">
    $value $langs
</a>
```

## Callback Function Syntax

### Short Syntax `[%...%]`

```html
<!-- Equality check returning string -->
[%funcjob=eqs;param=is_active;value=Y;true=active;false=inactive%]

<!-- Equality check returning template -->
[%funcjob=eq;param=show_search;value=T;true=search.box;false=empty%]

<!-- Case/switch returning string -->
[%funcjob=cases;param=status;ACTIVE=green;PENDING=yellow;else=gray%]

<!-- Case/switch returning template -->
[%funcjob=case;param=page;home=page.home;about=page.about;else=page.404%]

<!-- Date functions -->
[%funcjob=today%]
[%funcjob=now%]
[%funcjob=lastweek%]
```

### Full Syntax `<%FUNC%...%FUNC%>`

```html
<%FUNC%
_function_name=renderUserCard
_parameter_prefix=user
@name=$current_user_name
@email=$current_user_email
role=admin
%FUNC%>
```

## Translation Syntax

```html
<!-- Simple translation -->
[:WELCOME_MESSAGE:]

<!-- Translation with default -->
[:GREETING,Hello:]

<!-- Translation with parameters -->
[:USER_COUNT:%d users:5:]

<!-- Combined with callbacks -->
[%funcjob=eqs;param=active;value=Y;true=[:YES,Yes:];false=[:NO,No:]%]
```

## Comment Marks

Lines containing the comment mark (default: `##`) are stripped:

```html
<div>Visible content</div> ## This comment is removed
## This entire line is removed
```

## Escape Sequences

| Sequence | Result | Usage |
|----------|--------|-------|
| `\{` | `{` | Escape curly braces |
| `\}` | `}` | Escape curly braces |
| `_dollar_` | `$` | Escape dollar sign |
| `{{` | `[` | Left bracket |
| `}}` | `]` | Right bracket |

## Configuration Parameters

| Parameter | Description |
|-----------|-------------|
| `TEMPLATEDIR` | Template files directory |
| `MULTILANG` | Enable multi-language support |
| `DEFLANG` | Default language |
| `DEFTEMPLATELANG` | Default template language |
| `COMMENTMARK` | Comment marker (default: `##`) |
| `LOOPCOUNT` | Maximum parse iterations (default: 1000) |
| `SHOWMISSINGTEMPLATE` | Show debug info for missing templates |
| `ENABLETEMPLATEABS` | Enable absolute template paths |
| `ENABLEPARAMCALLBACK` | Enable parameter callbacks |

## Template File Organization

```
assets/templates/
├── main.template           # Main page wrapper
├── header.main.template    # Header component
├── header.search.template  # Search box
├── header.user.template    # User menu
├── sidebar.main.template   # Navigation sidebar
├── footer.main.template    # Footer
├── empty.template          # Empty template for conditionals
├── grid.column.bool.template
├── grid.column.status.template
├── grid.column.email.template
├── print.invoice.main.template
└── EN/                     # English-specific templates
    └── email.welcome.template
```

## Dynamic Template Loading

```php
// Load template based on parameter
Eisodos::$templateEngine->getTemplate(
    "print." . Eisodos::$parameterHandler->getParam("document_id") . ".main"
);

// Load versioned template (v3.template, v2.template, template)
// Framework automatically tries version prefixes
Eisodos::$templateEngine->getTemplate('page.main');
```

## Best Practices

1. **Use `$templateabs_` for component includes** - Clearer than nested paths
2. **Create an `empty.template`** - For conditional rendering that outputs nothing
3. **Use `[%funcjob=eqs%]` for CSS classes** - Cleaner than inline PHP
4. **Use `[%funcjob=eq%]` for template switching** - Component-level conditionals
5. **Organize templates by feature** - `print.`, `grid.column.`, `email.`
6. **Use default values** - `$param~='default';` prevents empty output

## See Also

- [Eisodos](Eisodos.md) - Main framework class
- [ParameterHandler](ParameterHandler.md) - Parameter management
- [Translator](Translator.md) - Language translation
- [CallbackFunctionShortParser](CallbackFunctionShortParser.md) - Callback patterns
- [ParserInterface](ParserInterface.md) - Custom parsers
