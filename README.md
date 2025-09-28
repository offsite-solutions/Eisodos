# Eisodos framework

## Prerequisites
- PHP 8.4.x
- Packages
  - ext-mbstring
  - ext-bcmath
  - ext-json
  - psr/log

## Installation
```
composer install "offsite-solutions/eisodos"
```

## Eisodos

### Initializing
The first step of framework initialization, with the init method of Eisodos class.
It initializes the Eisodos instance (singleton) and all the necessary instances.
```
use Eisodos\Eisodos;

/**
 * @param array|mixed $options_ =[
 *     'applicationDir',   // Application directory
 *     'applicationName',  // Application name
 *     ] Application options - mandatory
 * @return Eisodos
 */
public function init(array $options_): Eisodos {}
```
**applicationDir** : Every relative path use this value as a base path\
**applicationName** : The application name value is used in the configuration files, logging, etc.

Initialization parameters can be accessed as an internal parameter via the ParameterHandler object, ex: 
```
Eisodos::$parameterHandler->getParam('applicationName');
```

After initialization the internal modules can be accessed via the base object as
```
Eisodos::$templateEngine->...
Eisodos::$configLoader->...
etc.
```

## ParameterHandler
Eisodos merges the incoming parameters (GET, POST, SESSION, HEAD, configuration values) into one internal parameter array. All parameters can be read, but there are rules which can be overwritten. All parameter names is converted to lower case. The order of processing is:
1. configuration parameters (readonly - see details in ConfigLoader)
2. 

## ConfigLoader

## DBConnectors interface

## Parser interface

## Logger

## Mailer

## Render

## TemplateEngine

## Translator

## Utils