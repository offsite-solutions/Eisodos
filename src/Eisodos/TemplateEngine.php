<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
  namespace Eisodos;
  
  use Eisodos\Abstracts\Singleton;
  use Eisodos\Interfaces\ParserInterface;
  use Exception;
  use RuntimeException;
  
  class TemplateEngine extends Singleton {
    
    // Private properties
    
    /**
     * @var string Default callback function name
     */
    public $defaultCallbackFunctionName = '';
    /**
     * @var array Template cache
     */
    private $_templateCache = [];
    
    // Public properties
    /**
     * Registered template block parsers
     * @var array
     */
    private $_registeredParsers = [];
    
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
      $listOfValuePairs_ = array(),
      $addResultToResponse_ = true,
      $disableParsing_ = false,
      $disableLanguageTagParsing_ = false,
      $templateRow_ = -1
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
     * @param $templateText_
     * @param array $listOfValuePairs_
     * @param bool $addResultToResponse_
     * @param string $variablePrefix_
     * @return string
     */
    public function parseTemplateText(
      $templateText_,
      $listOfValuePairs_ = array(),
      $addResultToResponse_ = true,
      $variablePrefix_ = ''
    ): string {
      $Page = '';
      foreach (explode("\n", $templateText_) as $line) {
        if (Eisodos::$parameterHandler->isOn('EditorMode') // remove line ends starting with comment mark (##)
          and strpos($line, Eisodos::$parameterHandler->getParam('COMMENTMARK', '##')) !== false) {
          $line = substr($line, 0, strpos($line, Eisodos::$parameterHandler->getParam('COMMENTMARK', '##')));
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
        or $parser_->closeTag() === '') {
        throw new RuntimeException('Open and close tags are mandatory');
      }
      foreach ($this->_registeredParsers as $parser) {
        if (strpos($parser->openTag(), $parser_->openTag()) !== false
          or strpos($parser_->openTag(), $parser->openTag()) !== false) {
          throw new RuntimeException('Open tag already registered!');
        }
        if (strpos($parser->closeTag(), $parser_->closeTag()) !== false
          or strpos($parser_->closeTag(), $parser->closeTag()) !== false) {
          throw new RuntimeException('Close tag already registered!');
        }
      }
      $this->_registeredParsers[] = $parser_;
    }
    
    // Public functions
    
    /**
     * @param $templateID_
     * @param array $listOfValuePairs_
     * @param bool $addResultToResponse_
     * @param bool $disableParsing_
     * @param bool $disableLanguageTagParsing_
     * @param int $templateRow_
     * @param bool $raiseOnMissingTemplate_
     * @return bool|mixed|string
     */
    private function _getTemplate(
      $templateID_,
      $listOfValuePairs_ = array(),
      $addResultToResponse_ = true,
      $disableParsing_ = false,
      $disableLanguageTagParsing_ = false,
      $templateRow_ = -1,
      $raiseOnMissingTemplate_ = false
    ) {
      // Eisodos::$logger->trace('BEGIN', $this);
      $Page = '';
      if ($templateID_ === '') {
        return '';
      }
  
      if (isset($this->_templateCache[$templateID_]) and !Eisodos::$parameterHandler->isOn('EditorMode')) {
        $Page = $this->_templateCache[$templateID_];
      }
  
      if (($disableLanguageTagParsing_ === false)
        and (Eisodos::$parameterHandler->isOn('MULTILANG'))
        and !Eisodos::$parameterHandler->isOn('EditorMode')) {
        $LangSpec = Eisodos::$parameterHandler->getParam(
            'Lang',
            Eisodos::$parameterHandler->getParam('DEFLANG', 'HU')
          ) . DIRECTORY_SEPARATOR;
      } else {
        $LangSpec = '';
      }
      
      $TemplateFile = '';
      $templateDir = Eisodos::$parameterHandler->getParam('TEMPLATEDIR');
      
      // TODO EditorMode-ban engedelyezni a template-ek betolteset abszolut path-rol (vendor/EisodosEditor/templates/) hogy ne kelljen linkelni
      
      if ($Page === '') {
        if (strpos($templateDir, 'http://') === false
          and strpos($templateDir, 'https://') === false) {
          if (file_exists($templateDir . $LangSpec . $templateID_ . '.template')) {
            $TemplateFile = $templateDir . $LangSpec . $templateID_ . '.template';
          } elseif (Eisodos::$parameterHandler->neq('DEFTEMPLATELANG', '')) {
            if (file_exists(
              $templateDir . Eisodos::$parameterHandler->getParam('DEFTEMPLATELANG') .
              DIRECTORY_SEPARATOR . $templateID_ . '.template'
            )) {
              $TemplateFile = $templateDir . Eisodos::$parameterHandler->getParam('DEFTEMPLATELANG') .
                DIRECTORY_SEPARATOR . $templateID_ . '.template';
            }
          }
        } else {
          // http-rol kapja a template-t
          $TemplateFile = $templateDir . $LangSpec . $templateID_ . '.template';
        }
      }
      
      if ($TemplateFile !== '') {
        $cLine = 0;
        $file = fopen($TemplateFile, 'rb');
        if (!($file === false)) {
          while (!feof($file)) {
            $line = rtrim(fgets($file));
            if (!Eisodos::$parameterHandler->isOn('EditorMode')
              and strpos($line, Eisodos::$parameterHandler->getParam('COMMENTMARK', '##')) !== false) {
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
                if (!(($cLine === 1) and feof($file))) {
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
            'No template found with name: [' . $LangSpec . $templateID_ . '] (' . $TemplateFile . ')'
          );
        }
        if ($raiseOnMissingTemplate_ === true) {
          die('No template found with name: [' . $LangSpec . $templateID_ . ']');
        }
      }
      
      $Page = $this->parse($Page, $listOfValuePairs_, $disableParsing_);
      
      //Eisodos::$logger->trace('END', $this);
      
      if ($addResultToResponse_ === true) {
        Eisodos::$render->Response .= $Page;
        
        return '';
      }
      
      return $Page;
    }
    
    /**
     * @param string $text_ Part of page
     * @param array $listOfValuePairs_
     * @param bool $disableParsing_
     * @return mixed|string
     */
    public function parse(string $text_, $listOfValuePairs_ = array(), $disableParsing_ = false): string {
      $loopCountLimit = (integer)Eisodos::$parameterHandler->getParam('LOOPCOUNT', '1000');
      
      if (!$listOfValuePairs_) {
        $listOfValuePairs_ = array();
      }
      
      foreach ($listOfValuePairs_ as $k => $v) {
        Eisodos::$parameterHandler->setParam($k, $v);
      }
      
      if ($disableParsing_ === false) {
        $LoopCount = 0;
        
        $blockPosition = PHP_INT_MAX;
        $parameterExists = $this->_getParameterExists($text_);
        $parser = $this->_findUnparsedBlock($text_, $blockPosition);
        
        while (($parameterExists or $parser) and $LoopCount <= $loopCountLimit
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
    private function _getParameterExists($text_) {
      return preg_match('/\$[\w]+/', $text_);
    }
    
    private function _findUnparsedBlock($text_, &$foundPosition) {
      $foundPosition = PHP_INT_MAX;
      $foundParser = NULL;
      foreach ($this->_registeredParsers as $parser) {
        if (($parser->enabled()
            and ($position = strpos($text_, $parser->openTag())) !== false)
          and $position < $foundPosition) {
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
     * @return float|int
     */
    private function _getParameterPos(string $text_, string &$match_) {
      $matches = array();
      if (preg_match('/\$[\w]+/', $text_, $matches, PREG_OFFSET_CAPTURE)) {
        if (isset($match_)) {
          $match_ = $matches[0][0];
        }
        
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
        if (substr(
            $result,
            $EndPos,
            3
          ) === ":='") {                                            // add value to param
          $pageAfterParam = substr($result, $EndPos + 3);
          $paramValue = substr($pageAfterParam, 0, strpos($pageAfterParam, "'"));        // value
          Eisodos::$parameterHandler->setParam($paramName, $paramValue);
          $pageAfterParam = substr($result, $EndPos + 3 + strlen($paramValue) + 1);
          if (($pageAfterParam !== '') and ($pageAfterParam[0] = ';')) {                      // if there is closing ; (it must be) then cut it
            $pageAfterParam = substr($pageAfterParam, 1);
          }
          $result = $pageBeforeParam . $pageAfterParam;
        } elseif (substr($result, $EndPos, 3) === "~='") {
          $pageAfterParam = substr($result, $EndPos + 3);
          $paramDefaultValue = substr($pageAfterParam, 0, strpos($pageAfterParam, "'"));
          $pageAfterParam = substr($result, $EndPos + 3 + strlen($paramDefaultValue) + 1);
          if (($pageAfterParam !== '') and ($pageAfterParam[0] = ';')) {
            $pageAfterParam = substr($pageAfterParam, 1);
          }
          if (($paramValue = Eisodos::$parameterHandler->getParam($paramName)) === '') {
            $result = $pageBeforeParam . $this->replaceParamInString($paramDefaultValue) . $pageAfterParam;
          } else {
            $result = $pageBeforeParam . $paramValue . $pageAfterParam;
          }
        } else {
          if (strpos($paramName, 'template_') === 0) {
            $paramValue = $this->getTemplate(
              'inline.' . Eisodos::$utils->replace_all(substr($paramName, 9), '_', '.', true, false),
              array(),
              false,
              false,
              false,
              -1
            );
          } elseif (strpos($paramName, 'templateabs_') === 0) {
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
          } elseif (strpos($paramName, 'callback_') === 0) {
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
     * Replace all parameters in a string
     * @param string $text_ Text to search for parameters
     * @return string
     */
    public function replaceParamInString(string $text_): string {
      $loopCountLimit = (integer)Eisodos::$parameterHandler->getParam('LOOPCOUNT', '1000');
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
     * @param array $listOfValuePairs_ Optional value pairs
     * @param bool $addResultToResponse_ Add to result or just give it back
     * @param bool $disableParsing_ Disable parsing
     * @param bool $disableLanguageTagParsing_ Disable language tag parsing
     * @param int $templateRow_ Gives back only the specified row
     * @param bool $raiseOnMissingTemplate_ Raise Exception if template not exists
     * @return Exception|string
     */
    public function getTemplate(
      string $templateID_,
      $listOfValuePairs_ = array(),
      $addResultToResponse_ = true,
      $disableParsing_ = false,
      $disableLanguageTagParsing_ = false,
      $templateRow_ = -1,
      $raiseOnMissingTemplate_ = false
    ) {
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