<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
  namespace Eisodos\Parsers;
  
  use Eisodos\Eisodos;
  use Eisodos\Interfaces\ParserInterface;
  use Exception;
  use RuntimeException;

  class CallbackFunctionParser implements ParserInterface {
    
    /**
     * @inheritDoc
     */
    public function openTag(): string {
      return '<%FUNC%';
    }
    
    /**
     * @inheritDoc
     */
    public function closeTag(): string {
      return '%FUNC%>';
    }
    
    /**
     * @inheritDoc
     */
    public function parse(string $text_, bool|int $blockPosition_ = false): string {
      $orig = '';
      try {
        $structure = substr($text_, strpos($text_, '<%') + 2);
        $structure = substr($structure, 0, strpos($structure, '%'));
        $orig_ = substr($text_, strpos($text_, '<%' . $structure . '%'));
        $closenum = substr_count($orig_, '%' . $structure . '%>');
        for ($a = 1; $a <= $closenum; $a++) {
          $orig_temp = substr(
            $orig_,
            0,
            Eisodos::$utils->_strpos_offset('%' . $structure . '%>', $orig_, $a) + 1 + strlen($structure) + 2
          );
          if (substr_count($orig_temp, '<%') === substr_count($orig_temp, '%>')) {
            $orig = $orig_temp;
            break;
          }
        }
        if ($orig === '') {
          throw new RuntimeException('Structure is not closed!');
        }
        $body = substr(
          $orig,
          strlen($structure) + 3,
          strlen($orig) - (strlen($structure) * 2 + 6)
        );
        $body = trim($body);
        $blockLines = explode("\n", $body);
        foreach ($blockLines as &$row) {
          $row = trim($row);
        }
        unset($row);
        
        // $this->writeErrorLog(NULL,$debug);
        
        $LFuncParams = array();
        
        // include structure control variables
        
        $include = '';
        $functionName = '';
        $parameterPrefix = '';
        $userFuncArray = false;
        
        $multiLineSeparator = '';
        $multiLineCloseSeparator = '';
        $parameterName = '';
        $parseValue = false;
        
        foreach ($blockLines as $blockLine) {
          if ($multiLineSeparator === ''
            && str_contains($blockLine, '>>')) {
            $parameterName = (trim(substr($blockLine, 0, strpos($blockLine, '>>'))));
            if (str_starts_with($parameterName, '@')) {
              $parseValue = true;
              $parameterName = substr($parameterName, 1);
            } else {
              $parseValue = false;
            }
            $multiLineSeparator = substr($blockLine, strpos($blockLine, '>>'));
            $multiLineSeparator = substr($multiLineSeparator, 0, strpos($multiLineSeparator, '='));
            $multiLineCloseSeparator = Eisodos::$utils->replace_all($multiLineSeparator, '>', '<');
            $LFuncParams[$parameterName] = substr($blockLine, strpos($blockLine, '=') + 1);
          } elseif ($multiLineSeparator === '' && strlen($blockLine) >= 2) {
            $parameterName = (trim(substr($blockLine, 0, strpos($blockLine, '='))));
            if (str_starts_with($parameterName, '@')) {
              $parseValue = true;
              $parameterName = substr($parameterName, 1);
            } else {
              $parseValue = false;
            }
            $value = substr($blockLine, strpos($blockLine, '=') + 1);
            switch ($parameterName) {
              case '_include':
                $include = trim($value);
                break;
              case '_function_name':
                $functionName = trim($value);
                break;
              case '_parameter_prefix':
                $parameterPrefix = trim($value);
                break;
              case '_real_parameters':
                $userFuncArray = in_array(strtoupper(trim($value)), ['Y', 'T', '1', 'TRUE', 'ON'], true);
                break;
              default:
                $LFuncParams[$parameterName] = ($parseValue ? Eisodos::$templateEngine->parse(
                  $value,
                  $LFuncParams
                ) : $value);
                break;
            }
          } elseif ($multiLineSeparator !== '') {
            if (trim($blockLine) === $multiLineCloseSeparator) {
              $LFuncParams[$parameterName] = ($parseValue ? Eisodos::$templateEngine->parse(
                $LFuncParams[$parameterName],
                $LFuncParams
              ) : $LFuncParams[$parameterName]);
              $parameterName = '';
              $multiLineSeparator = '';
            } else {
              $LFuncParams[$parameterName] = $LFuncParams[trim($parameterName)] . "\n" . $blockLine;
            }
          } elseif (trim($blockLine) !== '') {
            Eisodos::$render->pageDebugInfo("Parameter parse error in INC structure [$parameterName]!");
          }
        }
        
        // if parameter prefix is not null then give LFuncParams to global parameters
        
        if ($parameterPrefix !== '') {
          foreach ($LFuncParams as $key => $value) {
            Eisodos::$parameterHandler->setParam($parameterPrefix . '_' . $key, $value);
          }
        }
        
        {
          if ($include !== '' && !str_contains($include, '::')) {
            /** @noinspection PhpIncludeInspection */
            @require($include);
          }
          
          $result = Eisodos::$utils->replace_all(
            $text_,
            $orig,
            ($userFuncArray ?
              call_user_func_array(
                $functionName,
                array_merge(array_values($LFuncParams), [$parameterPrefix])
              ) :
              call_user_func(
                ($functionName === '' ? Eisodos::$templateEngine->defaultCallbackFunctionName : $functionName),
                $LFuncParams,
                $parameterPrefix
              )),
            false,
            false
          );
          
          return ($result);
        }
      } catch (Exception $e) {
        return Eisodos::$utils->replace_all(
          $text_,
          $orig,
          '<!-- Error in include: ' . $e->getMessage() . ' -->',
          false,
          false
        );
      }
    }
    
    /** @inheritDoc */
    public function enabled(): bool {
      return true;
    }
    
  }