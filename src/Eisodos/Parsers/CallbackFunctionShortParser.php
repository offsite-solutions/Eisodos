<?php


namespace Eisodos\Parsers;

use Eisodos\Eisodos;
use Eisodos\Interfaces\ParserInterface;

class CallbackFunctionShortParser extends CallbackFunctionParser
{

    /**
     * @inheritDoc
     */
    public function openTag(): string
    {
        return '[%';
    }

    /**
     * @inheritDoc
     */
    public function closeTag(): string
    {
        return '%]';
    }

    /**
     * @inheritDoc
     */
    public function parse($text_, $blockPosition = false): string
    {
        $closeTagPosition = strpos($text_, '%]');
        $functionBody = '';
        foreach (
            explode(
                ';',
                substr($text_, $blockPosition + 2, $closeTagPosition - $blockPosition - 2)
            ) as $parameter
        ) {
            $functionBody .= ($functionBody !== '' ? "\n" : '') . $parameter;
        }
        $functionBody = '<%FUNC%' . PHP_EOL . $functionBody . '%FUNC%>';
        return Eisodos::$utils->replace_all(
            $text_,
            substr($text_, $blockPosition, $closeTagPosition - $blockPosition + 2),
            parent::parse($functionBody, 0),
            false,
            false
        );
    }

    /** @inheritDoc */
    public function enabled(): bool
    {
        return true;
    }

}