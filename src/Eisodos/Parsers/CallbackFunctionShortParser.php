<?php


namespace Eisodos\Parsers;

use Eisodos\Eisodos;
use Eisodos\Interfaces\ParserInterface;

class CallbackFunctionShortParser extends CallbackFunctionParser implements ParserInterface
{

    /**
     * @inheritDoc
     */
    public function openTag()
    {
        return '[%';
    }

    /**
     * @inheritDoc
     */
    public function closeTag()
    {
        return '%]';
    }

    /**
     * @inheritDoc
     */
    public function parse($text_, $blockPosition = false)
    {
        $closeTagPosition = strpos($text_, '%]');
        $functionBody = '';
        foreach (
            explode(
                ";",
                substr($text_, $blockPosition + 2, $closeTagPosition - $blockPosition - 2)
            ) as $parameter
        ) {
            $functionBody .= (strlen($functionBody) > 0 ? "\n" : "") . $parameter;
        }
        $functionBody = "<%FUNC%" . PHP_EOL . $functionBody . "%FUNC%>";
        return Eisodos::$utils->replace_all(
            $text_,
            substr($text_, $blockPosition, $closeTagPosition - $blockPosition + 2),
            parent::parse($functionBody, 0),
            false,
            false
        );
    }

    public function enabled()
    {
        return true;
    }

}