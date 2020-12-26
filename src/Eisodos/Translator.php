<?php /** @noinspection DuplicatedCode SpellCheckingInspection PhpUnusedFunctionInspection NotOptimalIfConditionsInspection */
  
  namespace Eisodos;
  
  use Eisodos\Abstracts\Singleton;
  use Eisodos\Interfaces\ParserInterface;
  use Exception;
  use PC;

  /**
   * Class Translator
   * @package Eisodos
   */
  final class Translator extends Singleton implements ParserInterface {
    
    // Private variables
    
    public $userLanguageIDs = array();
    private
      $_languageIDs = array(),
      $_languageIDsCRC = 0,
      $_languageIDsFileError = false;
    
    // Public variables
    /**
     * @var bool
     */
    private $_collectLangIDs;
  
    // Private functions
  
    /**
     * Translation initialization
     * @param array $options_
     * @return void
     * @throws Exception
     */
    public function init($options_ = []): void {
      $this->_collectLangIDs = Eisodos::$parameterHandler->isOn('COLLECTLANGIDS');
  
      // loading translate file
      $this->loadMasterLanguageFile();
  
      // loading user editable translate file
      $this->_loadUserLanguageFile();
  
      // register as Parser
      Eisodos::$templateEngine->registerParser($this);
    }
    
    // Public functions
    
    /**
     * Loads master (generated) language file
     * @param bool $forceCollection_
     */
    public function loadMasterLanguageFile($forceCollection_ = false): void {
      if ($forceCollection_) {
        Eisodos::$parameterHandler->setParam('COLLECTLANGIDS', 'T', false, false, 'eisodos::translator');
        $this->_collectLangIDs = true;
      }
      if (Eisodos::$parameterHandler->neq('LANGIDFILE', '')
        and Eisodos::$parameterHandler->neq('Langs', '')
        and file_exists(Eisodos::$parameterHandler->getParam('LANGIDFILE'))) {
        $file = fopen(Eisodos::$parameterHandler->getParam('LANGIDFILE'), 'rb');
        if (!($file === false)
          and (Eisodos::$parameterHandler->isOn('COLLECTLANGIDS')
            or flock($file, LOCK_EX))) {
          while (!feof($file)) {
            $line = rtrim(fgets($file));
            if ($line === '') {
              continue;
            }
            $l = explode('=', $line, 2);
            $l[0] = strtoupper($l[0]);
            if ($this->_collectLangIDs) {
              if (preg_match('/^[#0-9A-Z_.\-]+$/', $l[0])) {
                $this->_languageIDs[$l[0]] = (count($l) === 1 ? '' : $l[1]);
              }
            } else {
              $this->_languageIDs[$l[0]] = (count($l) === 1 ? '' : $l[1]);
            }
          }
          flock($file, LOCK_UN);
          fclose($file);
          $this->_languageIDsCRC = crc32(print_r($this->_languageIDs, true));
        } else {
          $this->_languageIDsFileError = true;
        }
      }
    }
    
    /**
     * Loads user language file to memory
     */
    private function _loadUserLanguageFile(): void {
      if (Eisodos::$parameterHandler->neq('USERLANGIDFILE', '')
        and Eisodos::$parameterHandler->neq('Langs', '')
        and file_exists(Eisodos::$parameterHandler->getParam('USERLANGIDFILE'))) {
        $file = fopen(Eisodos::$parameterHandler->getParam('USERLANGIDFILE'), 'rb');
        if (!($file === false)) {
          while (!feof($file)) {
            $line = rtrim(fgets($file));
            if ($line === '') {
              continue;
            }
            $l = explode('=', $line, 2);
            $this->userLanguageIDs[strtoupper($l[0])] = (count($l) === 1 ? '' : $l[1]);
          }
          fclose($file);
        }
      }
    }
    
    /**
     * Gives back language ids only
     * @return array
     */
    public function getLanguageIDs(): array {
      $r = array();
      foreach ($this->_languageIDs as $key => $row) {
        if (!strpos($key, '.#') and !array_key_exists(substr($key, 0, -3), $r)) {
          $r[substr($key, 0, -3)] = '';
        }
      }
      
      return $r;
    }
    
    /**
     * @param $languageID_
     * @param $language
     * @param bool $userEdited
     * @return string
     */
    public function getLangTextForTranslate($languageID_, $language, $userEdited = true): string {
      $languageID_ = strtoupper($languageID_);
      $language = strtoupper($language);
      if ($userEdited) {
        return Eisodos::$utils->safe_array_value(
          $this->userLanguageIDs,
          $languageID_ . '.' . $language,
          Eisodos::$utils->safe_array_value(
            $this->_languageIDs,
            $languageID_ . '.' . $language
          )
        );
      }
      
      return Eisodos::$utils->safe_array_value(
        $this->_languageIDs,
        $languageID_ . '.' . $language
      );
    }
    
    /**
     * Translates all language tags given in [:ID,def:] format
     * @param string $text_ Find language tags in text
     * @param bool $findHashmarked_ Give back hashmarked (default) translation if no translation found
     * @return string
     */
    public function translateText(string $text_, $findHashmarked_ = false): string {
      $loopCountLimit = (integer)Eisodos::$parameterHandler->getParam('LOOPCOUNT', '1000');
      $LoopCount = 0;
      while ((Eisodos::$parameterHandler->neq('LANGS', '')
          and strpos($text_, '[:') !== false)
        and $LoopCount <= $loopCountLimit) {
        $translatepos = strpos($text_, '[:');
  
        $text_ = Eisodos::$utils->replace_all(
          $text_,
          substr($text_, $translatepos, strpos($text_, ':]') - $translatepos + 2),
          $this->explodeLangText(
            substr($text_, $translatepos + 2, strpos($text_, ':]') - $translatepos - 2),
            $findHashmarked_
          )
        );
        $LoopCount++;
      }
      
      return $text_;
    }
    
    /**
     * Gives back translation of a stripped language id (language_id,default text:parameters)
     * @param $languageFormat_
     * @param bool $findHashmarked_
     * @return string
     */
    public function explodeLangText($languageFormat_, $findHashmarked_ = false): string {
      $p = explode(':', $languageFormat_);
      
      return Eisodos::$translator->getLangText($p[0], explode(';', (count($p) === 1 ? '' : $p[1])), $findHashmarked_);
    }
    
    /**
     * Gives back a translated language id
     * @param string $languageID_ Language ID in format LANGUAGE_ID,default text
     * @param array $textParams_ Optional parameters for formatting (if translation contains %s, %d, etc)
     * @param bool $findHashmarked_ Gives back hashmarked (default text) translation if no translation found
     * @return string
     */
    public function getLangText(string $languageID_, $textParams_ = array(), $findHashmarked_ = false): string {
      if (strpos($languageID_, ',') !== false) {
        [$languageID_, $defText] = explode(',', $languageID_, 2);
      } else {
        $defText = '';
      }
      $languageID_ = strtoupper($languageID_);
      $currentLanguage = strtoupper(
        Eisodos::$parameterHandler->getParam(
          'Lang',
          Eisodos::$parameterHandler->getParam('DEFLANG')
        )
      );
      if (!preg_match('/^[#0-9A-Z_.\-]+$/', $languageID_)) {
        PC::debug('Invalid language tag: ' . $languageID_);
        
        return '';
      }
      if ($this->_collectLangIDs) {
        foreach (explode(',', Eisodos::$parameterHandler->getParam('LANGS')) as $lang) {
          $this->_languageIDs[$languageID_ . '.' . $lang] = Eisodos::$utils->safe_array_value(
            $this->_languageIDs,
            $languageID_ . '.' . $lang
          );
        }
        if ($defText !== '') {
          $this->_languageIDs[$languageID_ . '.#'] = $defText;
        }
      }
      if ($defText === '' and $findHashmarked_) {
        $defText = Eisodos::$utils->safe_array_value($this->_languageIDs, $languageID_ . '.#');
      }
      if (Eisodos::$parameterHandler->isOn('SHOWLANGIDS')) {
        return ':' . $languageID_;
      }
      
      return @vsprintf(
        Eisodos::$utils->safe_array_value(
          $this->userLanguageIDs,
          $languageID_ . '.' . $currentLanguage,
          Eisodos::$utils->safe_array_value(
            $this->_languageIDs,
            $languageID_ . '.' . $currentLanguage,
            Eisodos::$utils->safe_array_value(
              $this->_languageIDs,
              strtoupper($languageID_ . '.' . Eisodos::$parameterHandler->getParam('DEFLANG')),
              (Eisodos::$parameterHandler->isOn('SHOWMISSINGLANGIDS') ? ':' . $languageID_ : $defText)
            )
          )
        ),
        $textParams_
      );
    }
  
    public function finish(): void {
      if (Eisodos::$parameterHandler->neq('LANGIDFILE', '')
        and Eisodos::$parameterHandler->neq('LANGS', '')
        and Eisodos::$parameterHandler->isOn('COLLECTLANGIDS')
        and $this->_languageIDsFileError === false
        and $this->_languageIDsCRC !== crc32(print_r($this->_languageIDs, true))
      ) {
        $file = fopen(Eisodos::$parameterHandler->getParam('LANGIDFILE'), 'wb');
        if (flock($file, LOCK_EX)) {
          ksort($this->_languageIDs);
          foreach ($this->_languageIDs as $key => $value) {
            fwrite($file, $key . '=' . $value . "\n");
          }
          flock($file, LOCK_UN);
        } else {
          PC::debug('Language file was blocked for writing!');
        }
        fclose($file);
      }
    }
    
    /**
     * @inheritDoc
     */
    public function openTag(): string {
      return '[:';
    }
    
    /**
     * @inheritDoc
     */
    public function closeTag(): string {
      return ':]';
    }
    
    /**
     * @inheritDoc
     */
    public function parse(string $text_, $blockPosition_ = false): string {
      $text_ = Eisodos::$utils->replace_all(
        $text_,
        substr($text_, $blockPosition_, strpos($text_, ':]') - $blockPosition_ + 2),
        $this->explodeLangText(
          substr($text_, $blockPosition_ + 2, strpos($text_, ':]') - $blockPosition_ - 2)
        )
      );
  
      return $text_;
    }
    
    /** @inheritDoc */
    public function enabled(): bool {
      return (Eisodos::$parameterHandler->isOn('TranslateLanguageTags')
        and Eisodos::$parameterHandler->neq('LANGS', ''));
    }
    
  }