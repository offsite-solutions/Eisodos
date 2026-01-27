# ParserInterface

Interface for template block parsers.

**Namespace:** `Eisodos\Interfaces`
**Source:** `src/Eisodos/Interfaces/ParserInterface.php`

## Overview

The `ParserInterface` defines the contract for template block parsers in the Eisodos framework. Parsers are used to process special blocks within templates, identified by opening and closing tags.

## Methods

### openTag(): string

Defines the opening tag that identifies blocks handled by this parser.

**Returns:** Opening tag string

**Example:**
```php
public function openTag(): string {
    return '<%CUSTOM%';
}
```

### closeTag(): string

Defines the closing tag that identifies blocks handled by this parser.

**Returns:** Closing tag string

**Example:**
```php
public function closeTag(): string {
    return '%CUSTOM%>';
}
```

### parse(string $text_, bool|int $blockPosition_ = false): string

Parses the page content and processes blocks.

**Parameters:**
- `$text_` - The currently generated page content
- `$blockPosition_` - Position of the first occurrence of `openTag()` in the text

**Returns:** Modified page content after parsing

**Example:**
```php
public function parse(string $text_, bool|int $blockPosition_ = false): string {
    // Find the closing tag
    $closePos = strpos($text_, $this->closeTag(), $blockPosition_);

    // Extract block content
    $blockStart = $blockPosition_ + strlen($this->openTag());
    $blockContent = substr($text_, $blockStart, $closePos - $blockStart);

    // Process the content
    $result = $this->processContent($blockContent);

    // Replace the entire block with the result
    $fullBlock = substr($text_, $blockPosition_, $closePos - $blockPosition_ + strlen($this->closeTag()));
    return str_replace($fullBlock, $result, $text_);
}
```

### enabled(): bool

Determines if the parser is currently enabled.

**Returns:** `true` if parser should process blocks, `false` otherwise

**Example:**
```php
public function enabled(): bool {
    return Eisodos::$parameterHandler->isOn('ENABLE_CUSTOM_PARSER');
}
```

## Implementing a Custom Parser

### Basic Implementation

```php
<?php
namespace MyApp\Parsers;

use Eisodos\Eisodos;
use Eisodos\Interfaces\ParserInterface;

class UppercaseParser implements ParserInterface {
    public function openTag(): string {
        return '{{UPPER:';
    }

    public function closeTag(): string {
        return ':UPPER}}';
    }

    public function enabled(): bool {
        return true;
    }

    public function parse(string $text_, bool|int $blockPosition_ = false): string {
        $openTagLen = strlen($this->openTag());
        $closeTagLen = strlen($this->closeTag());

        // Find closing tag
        $closePos = strpos($text_, $this->closeTag(), $blockPosition_);
        if ($closePos === false) {
            return $text_; // No closing tag found
        }

        // Extract content between tags
        $contentStart = $blockPosition_ + $openTagLen;
        $content = substr($text_, $contentStart, $closePos - $contentStart);

        // Transform content
        $result = strtoupper($content);

        // Replace block with result
        $fullBlock = substr($text_, $blockPosition_, $closePos + $closeTagLen - $blockPosition_);
        return str_replace($fullBlock, $result, $text_);
    }
}
```

### Registration

```php
// Register the parser before Eisodos::$render->start()
Eisodos::$templateEngine->registerParser(new UppercaseParser());
```

### Usage in Templates

```html
<p>{{UPPER:hello world:UPPER}}</p>
<!-- Output: <p>HELLO WORLD</p> -->
```

## Advanced Implementation

### Parser with Configuration

```php
class ConditionalParser implements ParserInterface {
    public function openTag(): string {
        return '{{IF:';
    }

    public function closeTag(): string {
        return ':IF}}';
    }

    public function enabled(): bool {
        return Eisodos::$parameterHandler->isOn('ENABLE_CONDITIONALS');
    }

    public function parse(string $text_, bool|int $blockPosition_ = false): string {
        // Find closing tag
        $closePos = strpos($text_, $this->closeTag(), $blockPosition_);

        // Extract: condition|true_value|false_value
        $contentStart = $blockPosition_ + strlen($this->openTag());
        $content = substr($text_, $contentStart, $closePos - $contentStart);

        $parts = explode('|', $content, 3);
        $condition = trim($parts[0] ?? '');
        $trueValue = $parts[1] ?? '';
        $falseValue = $parts[2] ?? '';

        // Evaluate condition (parameter check)
        $result = Eisodos::$parameterHandler->isOn($condition) ? $trueValue : $falseValue;

        // Replace block
        $fullBlock = substr($text_, $blockPosition_, $closePos + strlen($this->closeTag()) - $blockPosition_);
        return str_replace($fullBlock, $result, $text_);
    }
}
```

Usage:
```html
{{IF:isLoggedIn|Welcome back!|Please log in:IF}}
```

## Built-in Parsers

| Parser | Open Tag | Close Tag | Description |
|--------|----------|-----------|-------------|
| `Translator` | `[:` | `:]` | Language translation |
| `CallbackFunctionParser` | `<%FUNC%` | `%FUNC%>` | Function callbacks |
| `CallbackFunctionShortParser` | `[%` | `%]` | Short callback syntax |

## Registration Rules

1. **Unique Tags** - Open and close tags must not overlap with existing parsers
2. **Non-empty Tags** - Both tags are required and cannot be empty
3. **Register Early** - Register before `Eisodos::$render->start()`

```php
try {
    Eisodos::$templateEngine->registerParser(new MyParser());
} catch (RuntimeException $e) {
    // Handle: Open tag already registered! or Close tag already registered!
}
```

## Parsing Order

Parsers are checked in registration order. The first matching parser processes each block. Parameter replacement (`$paramName`) is interleaved with block parsing based on position in the text.

## See Also

- [TemplateEngine](TemplateEngine.md) - Template processing
- [Translator](Translator.md) - Built-in parser implementation
- [CallbackFunctionParser](CallbackFunctionParser.md) - Built-in parser implementation
