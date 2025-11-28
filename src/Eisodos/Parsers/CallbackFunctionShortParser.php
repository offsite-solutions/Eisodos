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
    public function parse(string $text_, bool|int $blockPosition_ = false): string {
      $closeTagPosition = strpos(substr($text_, $blockPosition_ + 2), '%]');
      $functionBody = '';
      foreach (
        explode(
          ';',
          substr($text_, $blockPosition_ + 2, $closeTagPosition)
        ) as $parameter
      ) {
        $functionBody .= $parameter . PHP_EOL;
      }
      $functionBody = '<%FUNC%' . PHP_EOL . $functionBody . '%FUNC%>';
      
      return Eisodos::$utils->replace_all(
        $text_,
        substr($text_, $blockPosition_, $closeTagPosition + 4),
        parent::parse($functionBody, 0),
        false,
        false
      );
    }
    
  }