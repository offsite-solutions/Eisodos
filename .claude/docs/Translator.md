# Translator Class

Multi-language translation support with template integration.

**Namespace:** `Eisodos`
**Extends:** `Eisodos\Abstracts\Singleton`
**Implements:** `Eisodos\Interfaces\ParserInterface`
**Source:** `src/Eisodos/Translator.php`

## Overview

The `Translator` class provides multi-language support for the Eisodos framework. It loads language IDs from files, supports user-editable translations, integrates with the template engine as a block parser, and provides language collection mode for extracting untranslated strings.

## Properties

| Property | Type | Description |
|----------|------|-------------|
| `$userLanguageIDs` | `array` | User-editable language translations |

## Methods

### init(array $options_ = []): void

Initializes the translator.

**Actions:**
1. Loads master language file
2. Loads user-editable language file
3. Registers as template parser

### loadMasterLanguageFile(bool $forceCollection_ = false): void

Loads the master (generated) language file.

**Parameters:**
- `$forceCollection_` - Force language ID collection mode

### getLangText(string $languageID_, array $textParams_ = [], bool $findHashmarked_ = false): string

Gets translated text for a language ID.

**Parameters:**
- `$languageID_` - Language ID in format `LANGUAGE_ID` or `LANGUAGE_ID,default text`
- `$textParams_` - Parameters for sprintf formatting
- `$findHashmarked_` - Return default text if no translation found

**Returns:** Translated text

**Example:**
```php
// Simple translation
$text = Eisodos::$translator->getLangText('WELCOME_MESSAGE');

// With default
$text = Eisodos::$translator->getLangText('GREETING,Hello');

// With parameters (uses sprintf)
$text = Eisodos::$translator->getLangText('USER_COUNT', [5]);
// Language file: USER_COUNT.EN=There are %d users
// Result: There are 5 users
```

### getLangTextForTranslate(string $languageID_, string $language_, bool $userEdited_ = true): string

Gets translation for a specific language (for editing interfaces).

**Parameters:**
- `$languageID_` - Language ID
- `$language_` - Language code (e.g., `EN`, `HU`)
- `$userEdited_` - Include user-edited translations

### translateText(string $text_, bool $findHashmarked_ = false): string

Translates all language tags in text.

**Parameters:**
- `$text_` - Text containing `[:ID,default:]` tags
- `$findHashmarked_` - Return hashmarked (default) translation if not found

### explodeLangText(string $languageFormat_, bool $findHashmarked_ = false): string

Parses stripped language format (`language_id,default:parameters`).

### getLanguageIDs(): array

Returns all language IDs (without language suffixes).

### finish(): void

Saves collected language IDs to file (if collection mode enabled).

### setCollectLangIDs(bool $value_): void

Enables/disables language ID collection mode.

## Parser Interface Implementation

The Translator implements `ParserInterface` for template integration:

| Method | Value |
|--------|-------|
| `openTag()` | `[:` |
| `closeTag()` | `:]` |
| `enabled()` | When `TranslateLanguageTags` is on and `LANGS` is set |

## Template Syntax

### Basic Translation

```html
[:WELCOME_MESSAGE:]
```

### Translation with Default

```html
[:GREETING,Hello:]
```

### Translation with Parameters

```html
[:USER_COUNT:5:]
[:DATE_FORMAT:%s;%s;%s:]
```

## Language File Format

### Master Language File (LANGIDFILE)

```
WELCOME_MESSAGE.EN=Welcome
WELCOME_MESSAGE.HU=Üdvözöljük
WELCOME_MESSAGE.#=Welcome
GREETING.EN=Hello, %s!
GREETING.HU=Szia, %s!
USER_COUNT.EN=There are %d users
USER_COUNT.HU=%d felhasználó van
```

Format: `LANGUAGE_ID.LANG=Translation`

- `.#` suffix is the default/hashmarked translation

### User Language File (USERLANGIDFILE)

Same format, but user-editable. Overrides master translations.

## Configuration Parameters

| Parameter | Description |
|-----------|-------------|
| `LANGIDFILE` | Path to master language file |
| `USERLANGIDFILE` | Path to user-editable language file |
| `LANGS` | Comma-separated available languages (e.g., `EN,HU,DE`) |
| `DEFLANG` | Default language code |
| `Lang` | Current language (usually from session) |
| `MULTILANG` | Enable multi-language support |
| `COLLECTLANGIDS` | Enable language ID collection mode |
| `SHOWLANGIDS` | Show language IDs instead of translations (for debugging) |
| `SHOWMISSINGLANGIDS` | Show `:LANGID` for missing translations |
| `TranslateLanguageTags` | Enable language tag parsing in templates |

## Language ID Collection

When `COLLECTLANGIDS` is enabled, the translator collects all used language IDs:

```php
// Enable collection
Eisodos::$translator->setCollectLangIDs(true);

// Or in configuration
COLLECTLANGIDS=T
```

Collected IDs are saved to `LANGIDFILE` at the end of request.

## Usage Examples

### Basic Setup

```php
// Configuration
LANGIDFILE=/var/www/app/lang/master.lang
USERLANGIDFILE=/var/www/app/lang/user.lang
LANGS=EN,HU
DEFLANG=EN
TranslateLanguageTags=T
```

### In Templates

```html
<h1>[:PAGE_TITLE,Home:]</h1>
<p>[:WELCOME_TEXT,Welcome to our site:]</p>
<p>[:ITEMS_COUNT:%d items:3:]</p>
```

### In PHP Code

```php
// Set current language
Eisodos::$parameterHandler->setParam('Lang', 'HU', true);

// Get translation
$message = Eisodos::$translator->getLangText('ERROR_MESSAGE,An error occurred');

// With formatting
$count = Eisodos::$translator->getLangText('ITEM_COUNT', [$itemCount]);
```

### Translation Editor

```php
// Get all language IDs
$ids = Eisodos::$translator->getLanguageIDs();

// Get specific translation for editing
$enText = Eisodos::$translator->getLangTextForTranslate('WELCOME', 'EN');
$huText = Eisodos::$translator->getLangTextForTranslate('WELCOME', 'HU');
```

## See Also

- [Eisodos](Eisodos.md) - Main framework class
- [TemplateEngine](TemplateEngine.md) - Template processing
- [ParserInterface](ParserInterface.md) - Parser interface
