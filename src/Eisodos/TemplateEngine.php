<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
  namespace Eisodos;
  
  use Eisodos\Abstracts\Singleton;
  use Eisodos\Interfaces\ParserInterface;
  use Exception;
  use RuntimeException;
  
  final class TemplateEngine extends Singleton {
    
    // Private properties
    
    /**
     * @var string Default callback function name
     */
    public string $defaultCallbackFunctionName = '';
    /**
     * @var array Template cache
     */
    private array $_templateCache = [];
    
    /**
     * @var array Parsed typed parameter rules, keyed by rule text
     */
    private array $_typedRuleCache = [];
    
    // Public properties
    /**
     * Registered template block parsers
     * @var array
     */
    private array $_registeredParsers = [];
    
    // Private function
    
    /**
     * Template Engine initialization
     * @param mixed $options_
     * @return void
     */
    public function init($options_): void {
    }
    
    /**
     * Returns with bounch of concatenated parsed templates
     * @param array $templateID_ Array of Template IDs
     * @param array $listOfValuePairs_ Optional value parameters
     * @param bool $addResultToResponse_ Add to result or just give it back
     * @param bool $disableParsing_ Disable parsing of the templates
     * @param bool $disableLanguageTagParsing_ Disable language tag parsing
     * @param int $templateRow_ Give back only the specified row of the template
     * @return string
     */
    public function getMultiTemplate(
      array $templateID_,
      array $listOfValuePairs_ = array(),
      bool  $addResultToResponse_ = true,
      bool  $disableParsing_ = false,
      bool  $disableLanguageTagParsing_ = false,
      int   $templateRow_ = -1
    ): string {
      $result = '';
      foreach ($templateID_ as $v) {
        if ($v !== '') {
          $result .= $this->getTemplate(
            $v,
            $listOfValuePairs_,
            $addResultToResponse_,
            $disableParsing_,
            $disableLanguageTagParsing_,
            $templateRow_
          );
        }
      }
      
      return $result;
    }
    
    /**
     * @param string $templateText_
     * @param array $listOfValuePairs_
     * @param bool $addResultToResponse_
     * @param string $variablePrefix_
     * @return string
     */
    public function parseTemplateText(
      string $templateText_,
      array  $listOfValuePairs_ = array(),
      bool   $addResultToResponse_ = true,
      string $variablePrefix_ = ''
    ): string {
      $Page = '';
      $commentMark = Eisodos::$parameterHandler->getParam('COMMENTMARK', '##');
      $editorMode = Eisodos::$parameterHandler->isOn('EditorMode');
      foreach (explode("\n", $templateText_) as $line) {
        if ($editorMode // remove line ends starting with comment mark (##)
          && str_contains($line, $commentMark)) {
          $line = substr($line, 0, strpos($line, $commentMark));
          if ($line === '') {
            continue;
          }
        }
        $Page .= (($Page !== '') ? PHP_EOL : '') . $line;
      }
      $Page = Eisodos::$utils->replace_all(
        Eisodos::$utils->replace_all($Page, '\{', '{'),
        '\}',
        '}'
      );
      if ($variablePrefix_ !== '') {
        $Page = Eisodos::$utils->replace_all($Page, $variablePrefix_, '$');
      }
      $Page = $this->parse($Page, $listOfValuePairs_);
      
      if ($addResultToResponse_ === true) {
        Eisodos::$render->Response .= $Page;
        
        return '';
      }
      
      return $Page;
    }
    
    /**
     * Adds text to page response
     * @param $text_
     */
    public function addToResponse($text_): void {
      Eisodos::$render->Response .= $text_;
    }
    
    public function setDefaultCallbackFunction($defaultCallbackFunctionName_): void {
      $this->defaultCallbackFunctionName = $defaultCallbackFunctionName_;
    }
    
    /**
     * Register template block parser
     * @param ParserInterface $parser_ Template block parser object
     * @throws Exception
     */
    public function registerParser(ParserInterface $parser_): void {
      if ($parser_->openTag() === ''
        || $parser_->closeTag() === '') {
        throw new RuntimeException('Open and close tags are mandatory');
      }
      foreach ($this->_registeredParsers as $parser) {
        if (str_contains($parser->openTag(), $parser_->openTag())
          || str_contains($parser_->openTag(), $parser->openTag())) {
          throw new RuntimeException('Open tag already registered!');
        }
        if (str_contains($parser->closeTag(), $parser_->closeTag())
          || str_contains($parser_->closeTag(), $parser->closeTag())) {
          throw new RuntimeException('Close tag already registered!');
        }
      }
      $this->_registeredParsers[] = $parser_;
    }
    
    // Public functions
    
    /**
     * @param string $templateID_
     * @param array|null $listOfValuePairs_
     * @param bool $addResultToResponse_
     * @param bool $disableParsing_
     * @param bool $disableLanguageTagParsing_
     * @param int $templateRow_
     * @param bool $raiseOnMissingTemplate_
     * @return string
     */
    private function _getTemplate(
      string     $templateID_,
      array|null $listOfValuePairs_ = [],
      bool       $addResultToResponse_ = true,
      bool       $disableParsing_ = false,
      bool       $disableLanguageTagParsing_ = false,
      int        $templateRow_ = -1,
      bool       $raiseOnMissingTemplate_ = false
    ): string {
      $Page = '';
      if ($templateID_ === '') {
        return '';
      }
      
      Eisodos::$parameterHandler->setParam('LastRenderedTemplateID', $templateID_);
      
      if (isset($this->_templateCache[$templateID_]) && !Eisodos::$parameterHandler->isOn('EditorMode')) {
        $Page = $this->_templateCache[$templateID_];
      }
      
      if (($disableLanguageTagParsing_ === false)
        && (Eisodos::$parameterHandler->isOn('MULTILANG'))
        && !Eisodos::$parameterHandler->isOn('EditorMode')) {
        $LangSpec = Eisodos::$parameterHandler->getParam(
            'Lang',
            Eisodos::$parameterHandler->getParam('DEFLANG', 'HU')
          ) . DIRECTORY_SEPARATOR;
      } else {
        $LangSpec = '';
      }
      
      $templateFile = '';
      // ha a templateId tartalmaz / jelet, akkor megnezni, hogy ehhez tartozik-e kulon config konyvtar
      if (str_contains($templateID_, '/')) {
        $templateDir = Eisodos::$parameterHandler->getParam(explode('/', $templateID_, 2)[0] . '.TEMPLATEDIR');
      } else {
        $templateDir = '';
      }
      if ($templateDir === '') {
        $templateDir = Eisodos::$parameterHandler->getParam('TEMPLATEDIR');
      }
      
      // TODO EditorMode-ban engedelyezni a template-ek betolteset abszolut path-rol (vendor/EisodosEditor/templates/) hogy ne kelljen linkelni
      
      if ($Page === '') {
        /** @noinspection HttpUrlsUsage */
        if (!str_contains($templateDir, 'http://')
          && !str_contains($templateDir, 'https://')) {
          if (file_exists($templateDir . $LangSpec . $templateID_ . '.template')) {
            $templateFile = $templateDir . $LangSpec . $templateID_ . '.template';
          } elseif (Eisodos::$parameterHandler->neq('DEFTEMPLATELANG', '')) {
            if (file_exists(
              $templateDir . Eisodos::$parameterHandler->getParam('DEFTEMPLATELANG') .
              DIRECTORY_SEPARATOR . $templateID_ . '.template'
            )) {
              $templateFile = $templateDir . Eisodos::$parameterHandler->getParam('DEFTEMPLATELANG') .
                DIRECTORY_SEPARATOR . $templateID_ . '.template';
            }
          }
        } else {
          // http-rol kapja a template-t
          $templateFile = $templateDir . $LangSpec . $templateID_ . '.template';
        }
      }
      
      if ($templateFile !== '') {
        $cLine = 0;
        $file = fopen($templateFile, 'rb');
        if (!($file === false)) {
          while (!feof($file)) {
            $line = rtrim(fgets($file));
            if (!Eisodos::$parameterHandler->isOn('EditorMode')
              && str_contains($line, Eisodos::$parameterHandler->getParam('COMMENTMARK', '##'))) {
              $line = substr(
                $line,
                0,
                strpos($line, Eisodos::$parameterHandler->getParam('COMMENTMARK', '##'))
              );
              if ($line === '') {
                continue;
              }
            }
            $cLine++;
            if ($disableParsing_ === false) {
              if ($templateRow_ === -1) {
                if (!(($cLine === 1) && feof($file))) {
                  $Page .= $line . PHP_EOL;
                } else {
                  $Page .= $line . ' ';
                }
              } elseif ($cLine === $templateRow_) {
                $Page = $line;
              }
            } elseif ($Page !== '') {
              $Page .= PHP_EOL . $line;
            } else {
              $Page = $line;
            }
          }
          fclose($file);
        }
        
        if ($disableParsing_ === false) {
          $Page = Eisodos::$utils->replace_all(
            Eisodos::$utils->replace_all($Page, '\{', '{'),
            '\}',
            '}'
          );
        }
        
        // hozzaadas cache-hez
        $this->_templateCache[$templateID_] = $Page;
      } elseif ($Page === '') {
        if (Eisodos::$parameterHandler->isOn('SHOWMISSINGTEMPLATE')) {
          Eisodos::$render->pageDebugInfo(
            'No template found with name: [' . $templateDir . $LangSpec . $templateID_ . '] (' . $templateFile . ')'
          );
        }
        if ($raiseOnMissingTemplate_ === true) {
          die('No template found with name: [' . $LangSpec . $templateID_ . ']');
        }
      }
      
      if (!$listOfValuePairs_) {
        $listOfValuePairs_ = [];
      }
      
      $Page = $this->parse($Page, $listOfValuePairs_, $disableParsing_);
      
      if ($addResultToResponse_ === true) {
        Eisodos::$render->Response .= $Page;
        
        return '';
      }
      
      return $Page;
    }
    
    /**
     * @param string $text_ Part of page
     * @param array|null $listOfValuePairs_
     * @param bool $disableParsing_
     * @return string
     */
    public function parse(string $text_, array|null $listOfValuePairs_ = [], bool $disableParsing_ = false): string {
      $loopCountLimit = (int)Eisodos::$parameterHandler->getParam('LOOPCOUNT', '1000');
      
      if (!$listOfValuePairs_) {
        $listOfValuePairs_ = [];
      }
      
      foreach ($listOfValuePairs_ as $k => $v) {
        Eisodos::$parameterHandler->setParam($k, $v);
      }
      
      if ($disableParsing_ === false) {
        $LoopCount = 0;
        
        $blockPosition = PHP_INT_MAX;
        $parameterExists = $this->_getParameterExists($text_);
        $parser = $this->_findUnparsedBlock($text_, $blockPosition);
        
        while (($parameterExists || $parser) && $LoopCount <= $loopCountLimit
        ) {
          $foundParameter = '';
          $parameterPosition = $this->_getParameterPos($text_, $foundParameter);
          
          if ($parameterPosition === false) {
            $parameterPosition = PHP_INT_MAX;
          }
          
          if ($parameterPosition < $blockPosition) {
            $text_ = $this->_replaceParam($parameterPosition, $foundParameter, $text_);
          } elseif ($blockPosition < $parameterPosition) {
            $text_ = $parser->parse($text_, $blockPosition);
          }
          
          $parameterExists = $this->_getParameterExists($text_);
          $parser = $this->_findUnparsedBlock($text_, $blockPosition);
          $LoopCount++;
        }
      }
      
      return $text_;
    }
    
    /**
     * Gives back if a text contains a parameter definition $param
     * @param $text_
     * @return false|int
     */
    private function _getParameterExists($text_): false|int {
      return preg_match('/\$[\w]+/', $text_);
    }
    
    private function _findUnparsedBlock($text_, &$foundPosition) {
      $foundPosition = PHP_INT_MAX;
      $foundParser = NULL;
      foreach ($this->_registeredParsers as $parser) {
        if (($parser->enabled()
            && ($position = strpos($text_, $parser->openTag())) !== false)
          && $position < $foundPosition) {
          $foundPosition = $position;
          $foundParser = $parser;
        }
      }
      
      return $foundParser;
    }
    
    /**
     * Gives back the position of the first valuable character
     * @param string $text_ Search subject
     * @param string $match_ Found parameter's name
     * @return int|bool
     */
    private function _getParameterPos(string $text_, string &$match_): int|bool {
      $matches = array();
      if (preg_match('/\$[\w]+/', $text_, $matches, PREG_OFFSET_CAPTURE)) {
        $match_ = $matches[0][0];
        
        return 1 * $matches[0][1];
      }
      
      return false;
    }
    
    /**
     * Replace and parses parameters in a string
     * The reason it is here instead of ParameterHandler: parameters can embed templates, callbacks, etc.
     * @param int $fromPosition_ Starts replacing from the specified position (position of '$')
     * @param string $paramName_ Found parameter
     * @param string $page_ The source string
     * @return string
     */
    private function _replaceParam(int $fromPosition_, string $paramName_, string $page_): string {
      $result = $page_;
      $EndPos = $fromPosition_ + strlen($paramName_);                                  // next character position
      if ($EndPos > 0) {
        $pageBeforeParam = substr($result, 0, $fromPosition_);                       // page before the parameter
        $paramName = substr($paramName_, 1);
        if (substr($result, $EndPos, 2) === '[['
          && ($ruleEndPos = strpos($result, ']]', $EndPos + 2)) !== false
          && $ruleEndPos - $EndPos - 2 <= 255
          && !preg_match('/[\r\n;$]/', substr($result, $EndPos + 2, $ruleEndPos - $EndPos - 2))) { // typed parameter $param[[type,default,protection]]
          $rule = $this->_parseTypedRule(substr($result, $EndPos + 2, $ruleEndPos - $EndPos - 2));
          $afterRule = $ruleEndPos + 2;
          $paramValue = Eisodos::$parameterHandler->getParam($paramName);
          if (str_starts_with($paramName, 'template_')
            || str_starts_with($paramName, 'templateabs_')
            || str_starts_with($paramName, 'callback_')
            || substr($result, $afterRule, 3) === ":='"
            || $paramValue === '') {                                                   // rule is dropped, normal processing goes on
            $result = substr($result, 0, $EndPos) . substr($result, $afterRule);
          } else {
            $pageAfterParam = substr($result, $afterRule);
            if (str_starts_with($pageAfterParam, "~='")) {                             // default for empty value is not needed
              $closingPos = strpos($pageAfterParam, "'", 3);
              $pageAfterParam = ($closingPos === false) ? '' : substr($pageAfterParam, $closingPos + 1);
              if (($pageAfterParam !== '') && ($pageAfterParam[0] === ';')) {
                $pageAfterParam = substr($pageAfterParam, 1);
              }
            }

            return $pageBeforeParam
              . $this->_applyTypedRule($paramName, $paramValue, $rule, substr($pageBeforeParam, -1), substr($pageAfterParam, 0, 1))
              . $pageAfterParam;
          }
        }
        if (substr(
            $result,
            $EndPos,
            3
          ) === ":='") {                                            // add value to param
          $pageAfterParam = substr($result, $EndPos + 3);
          $paramValue = substr($pageAfterParam, 0, strpos($pageAfterParam, "'"));        // value
          Eisodos::$parameterHandler->setParam($paramName, $paramValue);
          $pageAfterParam = substr($result, $EndPos + 3 + strlen($paramValue) + 1);
          if (($pageAfterParam !== '') && ($pageAfterParam[0] === ';')) {                      // if there is closing ; (it must be) then cut it
            $pageAfterParam = substr($pageAfterParam, 1);
          }
          $result = $pageBeforeParam . $pageAfterParam;
        } elseif (substr($result, $EndPos, 3) === "~='") {
          $pageAfterParam = substr($result, $EndPos + 3);
          $paramDefaultValue = substr($pageAfterParam, 0, strpos($pageAfterParam, "'"));
          $pageAfterParam = substr($result, $EndPos + 3 + strlen($paramDefaultValue) + 1);
          if (($pageAfterParam !== '') && ($pageAfterParam[0] === ';')) {
            $pageAfterParam = substr($pageAfterParam, 1);
          }
          if (($paramValue = Eisodos::$parameterHandler->getParam($paramName)) === '') {
            $result = $pageBeforeParam . $this->replaceParamInString($paramDefaultValue) . $pageAfterParam;
          } else {
            $result = $pageBeforeParam . $paramValue . $pageAfterParam;
          }
        } else {
          if (str_starts_with($paramName, 'template_')) {
            $paramValue = $this->getTemplate(
              'inline.' . Eisodos::$utils->replace_all(substr($paramName, 9), '_', '.', true, false),
              array(),
              false,
              false,
              false,
              -1
            );
          } elseif (str_starts_with($paramName, 'templateabs_')) {
            if (Eisodos::$parameterHandler->isOn('ENABLETEMPLATEABS')) {
              $paramValue = $this->getTemplate(
                Eisodos::$utils->replace_all(
                  Eisodos::$utils->replace_all(
                    substr($paramName, 12),
                    '__',
                    '/',
                    true,
                    false
                  ),
                  '_',
                  '.',
                  true,
                  false
                ),
                array(),
                false,
                false,
                false,
                -1
              );
            } else {
              $paramValue = '<!-- Absolute inline templates not allowed -->';
            }
          } elseif (str_starts_with($paramName, 'callback_')) {
            if (Eisodos::$templateEngine->defaultCallbackFunctionName
              && Eisodos::$parameterHandler->isOn('ENABLEPARAMCALLBACK')) {
              $paramValue =
                call_user_func(
                  Eisodos::$templateEngine->defaultCallbackFunctionName,
                  ["funcjob" => "paramcallback",
                    "param" => substr($paramName, 9)]
                );
            } else {
              $paramValue = '<!-- Parameter callbacks not allowed -->';
            }
          } else {
            $paramValue = Eisodos::$parameterHandler->getParam($paramName);
          }
          $pageAfterParam = substr($result, $EndPos);
          $result = $pageBeforeParam . $paramValue . $pageAfterParam;
        }
      } else {
        $result[$fromPosition_] = '*';
      }
      
      return $result;
    }
    
    /**
     * Splits a typed parameter rule (type,default,protection) into its fields
     * @param string $ruleText_ Text between [[ and ]], a literal comma is written as \,
     * @return array ['type' => string, 'default' => string, 'protection' => string]
     * @throws RuntimeException on unknown protection or invalid regex
     */
    private function _parseTypedRule(string $ruleText_): array {
      if (array_key_exists($ruleText_, $this->_typedRuleCache)) {
        return $this->_typedRuleCache[$ruleText_];
      }
      $fields = array_map(
        fn($field) => str_replace('\,', ',', $field),
        preg_split('/(?<!\\\\),/', $ruleText_, 3)
      );
      $rule = [
        'type' => Eisodos::$utils->safe_array_value($fields, 0),
        'default' => Eisodos::$utils->safe_array_value($fields, 1),
        'protection' => strtolower(Eisodos::$utils->safe_array_value($fields, 2))
      ];
      if (!in_array($rule['protection'], ['', 'sql', 'html', 'javascript'], true)
        || (!in_array($rule['type'], ['', 'string', 'numeric'], true)
          && @preg_match($this->_typedRuleRegex($rule['type']), '') === false)) {
        throw new RuntimeException('Invalid typed parameter rule [' . $ruleText_ . ']');
      }
      $this->_typedRuleCache[$ruleText_] = $rule;

      return $rule;
    }

    /**
     * Builds the anchored regex of a typed parameter rule
     * @param string $type_ Regex without delimiters
     * @return string
     */
    private function _typedRuleRegex(string $type_): string {
      return '/^(?:' . preg_replace('~\\\\.(*SKIP)(*FAIL)|/~', '\/', $type_) . ')$/uD'; // escapes only unescaped slashes
    }

    /**
     * Checks a non-empty value against the type of a typed parameter rule
     * @param mixed $value_ Parameter value
     * @param string $type_ '', string, numeric or regex
     * @return bool
     */
    private function _isTypedValueValid(mixed $value_, string $type_): bool {
      if (is_array($value_)) {
        return false;
      }
      if ($type_ === '' || $type_ === 'string') {
        return true;
      }
      if ($type_ === 'numeric') {
        return preg_match('/^-?\d+$/D', $value_) === 1;
      }

      return @preg_match($this->_typedRuleRegex($type_), $value_) === 1;
    }

    /**
     * Removes template control sequences from a typed parameter value, so the inserted text
     * can not be expanded again by parse() or by the final replace in Render
     * @param string $value_ Parameter value
     * @return string
     */
    private function _stripTemplateControl(string $value_): string {
      do {
        $previous = $value_;
        $value_ = str_replace(['$', '_dollar_', '[%', '<%', '[:', '[#', '{#', '#}'], '', $value_);
      } while ($value_ !== $previous);

      return $value_;
    }

    /**
     * Trims the ends of a typed parameter value where they would form a control sequence
     * with the neighbouring template text ([ + %, < + %, { + # ...)
     * @param string $paramName_ Parameter name
     * @param string $value_ Parameter value
     * @param string $before_ Last character before the parameter
     * @param string $after_ First character after the parameter
     * @return string
     * @throws RuntimeException if a $ in the template would turn the value into a parameter name
     */
    private function _guardTypedBoundaries(string $paramName_, string $value_, string $before_, string $after_): string {
      if ($before_ === '$' && preg_match('/^\w/', $value_)) {
        throw new RuntimeException('Typed parameter directly after $ [' . $paramName_ . ']');
      }
      do {
        $previous = $value_;
        $value_ = match ($before_) {
          '[' => ltrim($value_, '%:#'),
          '<' => ltrim($value_, '%'),
          '{' => ltrim($value_, '#'),
          default => $value_
        };
        if (in_array($after_, ['%', ':', '#'], true)) {
          $value_ = rtrim($value_, '[');
        }
        if ($after_ === '%') {
          $value_ = rtrim($value_, '<');
        }
        if ($after_ === '#') {
          $value_ = rtrim($value_, '{');
        }
      } while ($value_ !== $previous);
      
      return $value_;
    }
    
    /**
     * Escapes a typed parameter value for the given context
     * @param string $value_ Parameter value
     * @param string $protection_ '', sql, html or javascript
     * @return string
     */
    private function _escapeTypedValue(string $value_, string $protection_): string {
      return match ($protection_) {
        'sql' => str_replace("'", "''", $value_),
        'html' => htmlspecialchars($value_, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'javascript' => strtr($value_, [                                               // strtr replaces each sequence once, \ included
          '\\' => '\\\\',
          "'" => "\\'",
          '"' => '\\"',
          "\n" => '\\n',
          "\r" => '\\r',
          "\u{2028}" => '\\u2028',
          "\u{2029}" => '\\u2029',
          '</' => '<\\/'
        ]),
        default => $value_
      };
    }

    /**
     * Validates a non-empty value of a typed parameter
     * @param string $paramName_ Parameter name
     * @param mixed $value_ Parameter value
     * @param array $rule_ Rule from _parseTypedRule()
     * @param string $before_ Last character before the parameter
     * @param string $after_ First character after the parameter
     * @return string Value to insert
     * @throws RuntimeException if the value is invalid and there is no default
     */
    private function _applyTypedRule(string $paramName_, mixed $value_, array $rule_, string $before_ = '', string $after_ = ''): string {
      if ($this->_isTypedValueValid($value_, $rule_['type'])) {
        return $this->_escapeTypedValue(
          $this->_guardTypedBoundaries($paramName_, $this->_stripTemplateControl($value_), $before_, $after_),
          $rule_['protection']
        );
      }
      if ($rule_['default'] === '') {
        throw new RuntimeException('Invalid parameter value [' . $paramName_ . ']');
      }
      $parameter = Eisodos::$utils->safe_array_value(Eisodos::$parameterHandler->getParameterArray(), strtolower($paramName_));
      Eisodos::$logger->debug('Invalid parameter value [' . $paramName_ . ']=['
        . (is_array($parameter) && Eisodos::$utils->safe_array_value($parameter, 'secret') ? '***'
          : (is_array($value_) ? 'Array' : str_replace(["\r", "\n"], ['\r', '\n'], $value_))) . ']');
      if (!is_array($parameter) || !Eisodos::$utils->safe_array_value($parameter, 'readonly')) {         // readonly parameters keep their value
        Eisodos::$parameterHandler->setParam($paramName_, $rule_['default'], false, false, 'eisodos::templateEngine');
      }

      return $this->_escapeTypedValue($rule_['default'], $rule_['protection']);
    }

    /**
     * Replace all parameters in a string
     * @param string $text_ Text to search for parameters
     * @return string
     */
    public function replaceParamInString(string $text_): string {
      $loopCountLimit = (int)Eisodos::$parameterHandler->getParam('LOOPCOUNT', '1000');
      $LoopCount = 0;
      $match = '';
      while (($pos = $this->_getParameterPos($text_, $match)) !== false
        and ($LoopCount <= $loopCountLimit)) {
        $text_ = $this->_replaceParam($pos, $match, $text_);
        $LoopCount++;
      }
      if ($LoopCount > $loopCountLimit) {
        die('Infinite parameter-loop (' . $loopCountLimit . ')');
      }
      
      return $text_;
    }
    
    /**
     * Reads and parses a template
     * @param string $templateID_ Template ID
     * @param array|null $listOfValuePairs_ Optional value pairs
     * @param bool $addResultToResponse_ Add to result or just give it back
     * @param bool $disableParsing_ Disable parsing
     * @param bool $disableLanguageTagParsing_ Disable language tag parsing
     * @param int $templateRow_ Gives back only the specified row
     * @param bool $raiseOnMissingTemplate_ Raise Exception if template not exists
     * @return string
     */
    public function getTemplate(
      string     $templateID_,
      array|null $listOfValuePairs_ = [],
      bool       $addResultToResponse_ = true,
      bool       $disableParsing_ = false,
      bool       $disableLanguageTagParsing_ = false,
      int        $templateRow_ = -1,
      bool       $raiseOnMissingTemplate_ = false
    ): string {
      $result = '';
      if (!Eisodos::$parameterHandler->isOn('EditorMode')) {
        foreach (Eisodos::$configLoader->getActiveVersions() as $v) {
          $result = $this->_getTemplate(
            $v . $templateID_,
            $listOfValuePairs_,
            false,
            $disableParsing_,
            $disableLanguageTagParsing_,
            $templateRow_,
            $raiseOnMissingTemplate_
          );
          if ($result !== '') {
            break;
          }
        }
      } else {
        return $this->_getTemplate(
          $templateID_,
          $listOfValuePairs_,
          $addResultToResponse_,
          $disableParsing_,
          $disableLanguageTagParsing_,
          $templateRow_,
          $raiseOnMissingTemplate_
        );
      }
      if ($addResultToResponse_ === true) {
        Eisodos::$render->Response .= $result;
        
        return '';
      }
      
      return $result;
    }
    
  }