<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
  namespace Eisodos\Parsers;
  
  use Eisodos\Eisodos;

  class CallbackFunctionShortParser extends CallbackFunctionParser {
    
    /**
     * @inheritDoc
     */
    public function openTag(): string {
      return '[%';
    }
    
    /**
     * @inheritDoc
     */
    public function closeTag(): string {
      return '%]';
    }
    
    /**
     * @inheritDoc
     */
    public function parse(string $text_, $blockPosition_ = false): string {
      $closeTagPosition = strpos($text_, '%]');
      $functionBody = '';
      foreach (
        explode(
          ';',
          substr($text_, $blockPosition_ + 2, $closeTagPosition - $blockPosition_ - 2)
        ) as $parameter
      ) {
        $functionBody .= ($functionBody !== '' ? "\n" : '') . $parameter;
      }
      $functionBody = '<%FUNC%' . PHP_EOL . $functionBody . '%FUNC%>';
      
      return Eisodos::$utils->replace_all(
        $text_,
        substr($text_, $blockPosition_, $closeTagPosition - $blockPosition_ + 2),
        parent::parse($functionBody, 0),
        false,
        false
      );
    }
    
  }