<?php

// inline callbacks

use Eisodos\Eisodos;

function callback_default($LFuncParams = array(), $parameterPrefix_ = '')
{
    if ($LFuncParams["function"] == "eq") {
        if (Eisodos::$parameterHandler->eq($LFuncParams["param"], $LFuncParams["value"])) {
            return Eisodos::$templateEngine->getTemplate($LFuncParams["true"], array(), false);
        } else {
            return Eisodos::$templateEngine->getTemplate($LFuncParams["false"], array(), false);
        }
    } elseif ($LFuncParams["function"] == "eqs") {
        if (Eisodos::$parameterHandler->eq($LFuncParams["param"], $LFuncParams["value"])) {
            return $LFuncParams["true"];
        } else {
            return $LFuncParams["false"];
        }
    } else {
        return "";
    }
}

function eqs($param_, $value_, $true_, $false_, $parameterPrefix_ = '')
{
    if (Eisodos::$parameterHandler->eq($param_, $value_)) {
        return $true_;
    }

    return $false_;
}