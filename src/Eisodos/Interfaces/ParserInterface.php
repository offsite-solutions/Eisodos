<?php
  
  
  namespace Eisodos\Interfaces;
  
  /**
   * Interface Parser helps build a parser for blocks found in templates, texts
   * Class must be registered via Eisodos::TemplateEngine->registerParser() before Eisodos::Render->start()
   * @package Eisodos
   */
  interface ParserInterface {
    
    /**
     * Parse page
     * @param string $text_ The currently generated page
     * @param int|bool $blockPosition_ The identified openTag first occurrence in text_
     * @return string The new page after parsing
     */
    public function parse($text_, $blockPosition_ = false): string;
    
    /**
     * Defines the opening tag
     * @return string
     */
    public function openTag(): string;
    
    /**
     * Defines the opening tag
     * @return string
     */
    public function closeTag(): string;
    
    /**
     * Defines the parser is enabled or not
     * @return bool
     */
    public function enabled(): bool;
    
  }