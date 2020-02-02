<?php


namespace Eisodos\Parsers;

use Eisodos\Eisodos;
use Eisodos\Interfaces\ParserInterface;
use Exception;

class CallbackFunctionParser implements ParserInterface
{

    /**
     * @inheritDoc
     */
    public function openTag()
    {
        return '<%FUNC%';
    }

    /**
     * @inheritDoc
     */
    public function closeTag()
    {
        return '%FUNC%>';
    }

    /**
     * @inheritDoc
     */
    public function parse($text_, $blockPosition = false)
    {
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
                if (substr_count($orig_temp, '<%') == substr_count($orig_temp, '%>')) {
                    $orig = $orig_temp;
                    break;
                }
            }
            if (strlen($orig) == 0) {
                throw new Exception('Structure is not closed!');
            }
            $body = substr(
                $orig,
                strlen($structure) + 3,
                strlen($orig) - (strlen($structure) * 2 + 6)
            ); // a struktura parameterezese
            $body = trim($body);
            $blockLines = explode("\n", $body);
            foreach ($blockLines as &$row) {
                $row = trim($row);
            }

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

            for ($a = 0; $a < count($blockLines); $a++) {
                if ($multiLineSeparator == ''
                    and strpos($blockLines[$a], '>>') !== false) {
                    $parameterName = (trim(substr($blockLines[$a], 0, strpos($blockLines[$a], '>>'))));
                    if ($parameterName[0] == '@') {
                        $parseValue = true;
                        $parameterName = substr($parameterName, 1);
                    } else {
                        $parseValue = false;
                    }
                    $multiLineSeparator = substr($blockLines[$a], strpos($blockLines[$a], '>>'));
                    $multiLineSeparator = substr($multiLineSeparator, 0, strpos($multiLineSeparator, '='));
                    $multiLineCloseSeparator = Eisodos::$utils->replace_all($multiLineSeparator, '>', '<');
                    $LFuncParams[$parameterName] = substr($blockLines[$a], strpos($blockLines[$a], '=') + 1);
                } elseif (strlen($multiLineSeparator) == 0 and strlen($blockLines[$a]) >= 2) {
                    $parameterName = (trim(substr($blockLines[$a], 0, strpos($blockLines[$a], '='))));
                    if ($parameterName[0] == '@') {
                        $parseValue = true;
                        $parameterName = substr($parameterName, 1);
                    } else {
                        $parseValue = false;
                    }
                    $multiLineSeparator = '';
                    $value = substr($blockLines[$a], strpos($blockLines[$a], '=') + 1);
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
                            $userFuncArray = in_array(trim(strtoupper($value)), ['Y', 'T', '1', 'TRUE', 'ON']);
                            break;
                        default:
                            $LFuncParams[$parameterName] = ($parseValue ? Eisodos::$templateEngine->parse(
                                $value,
                                $LFuncParams
                            ) : $value);
                            break;
                    }
                } elseif (strlen($multiLineSeparator) > 0) {
                    if (trim($blockLines[$a]) == $multiLineCloseSeparator) {
                        $LFuncParams[$parameterName] = ($parseValue ? Eisodos::$templateEngine->parse(
                            $LFuncParams[$parameterName],
                            $LFuncParams
                        ) : $LFuncParams[$parameterName]);
                        $parameterName = "";
                        $multiLineSeparator = "";
                    } else {
                        $LFuncParams[$parameterName] = $LFuncParams[trim($parameterName)] . "\n" . $blockLines[$a];
                    }
                } elseif (strlen(trim($blockLines[$a])) > 0) {
                    Eisodos::$render->pageDebugInfo("Parameter parse error in INC structure [$parameterName]!");
                }
            }

            // if parameter prefix is not null then give LFuncParams to global parameters

            if (strlen($parameterPrefix) > 0) {
                foreach ($LFuncParams as $key => $value) {
                    Eisodos::$parameterHandler->setParam($parameterPrefix . "_" . $key, $value);
                }
            }

            {
                if (strlen($include) > 0) {
                    @require_once($include);
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
                            ($functionName == '' ? Eisodos::$templateEngine->defaultCallbackFunctionName : $functionName),
                            $LFuncParams,
                            $parameterPrefix
                        )),
                    false,
                    false
                );

                return ($result);
            }
        } catch (Exception $e) {
            return Eisodos::$utils->replace_all($text_, $orig, "<!-- Error in include: " . $e->getMessage() . " -->", false, false);
        }
    }

    public function enabled()
    {
        return true;
    }

}