<?php

// inline callbacks
  
  use Eisodos\Eisodos;
  
  function callback_default($LFuncParams = array(), $parameterPrefix_ = '') {
    if ($LFuncParams["function"] === "eq") {
      if (Eisodos::$parameterHandler->eq($LFuncParams["param"], $LFuncParams["value"])) {
        return Eisodos::$templateEngine->getTemplate($LFuncParams["true"], array(), false);
      }
      
      return Eisodos::$templateEngine->getTemplate($LFuncParams["false"], array(), false);
    }
    
    if ($LFuncParams["function"] === "eqs") {
      if (Eisodos::$parameterHandler->eq($LFuncParams["param"], $LFuncParams["value"])) {
        return $LFuncParams["true"];
      }
      
      return $LFuncParams["false"];
    }
    
    return "";
  }
  
  function eqs($param_, $value_, $true_, $false_, $parameterPrefix_ = '') {
    if (Eisodos::$parameterHandler->eq($param_, $value_)) {
      return $true_;
    }
    
    return $false_;
  }