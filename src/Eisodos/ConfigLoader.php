<?php

namespace Eisodos;

use Eisodos\Abstracts\Singleton;
use Exception;
use PC;

/*
 * TODO: (normal) load and keep config.section into session and prevent re-parsing every time (mark section session) [test:session,...]
 * TODO: (critical) protect config values by overwritten from HTTP request parameters (mark section writeable [test:writable,...])
 * TODO: (low) config file editor
 * TODO: (critical) same handling of INI type's (T,"true",F,"false",empty) and JSON type's (true,false,null) values
 */

/**
 * Fills up parameters from configuration files
 * Files can be in classic INI files with sections or in JSON format
 *
 * @package Eisodos
 */
final class ConfigLoader extends Singleton
{

    public const CONFIG_TYPE_INI = 0;
    public const CONFIG_TYPE_JSON = 1;

    /**
     * @var string $_activeVersionsString List of active versions
     */
    private $_activeVersionsString = '';

    /**
     * @var array $_configCache Multidimensional array cache for faster loading [configFile][section][key]=value
     */
    private $_configCache = [];

    /**
     * @var string $_environment Environment prefix for config files
     */
    private $_environment = '';

    /**
     * @var mixed|string $_configPath Config file's path
     */
    private $_configPath = '';

    /**
     * @var int $_configType Format of configurations file
     */
    private $_configType = self::CONFIG_TYPE_INI;

    /**
     * @var array
     */
    private $_activeVersions;

    /**
     * Loads basic configuration
     * @param array $configOverwrites_
     */
    private function _loadMainConfiguration($configOverwrites_ = array())
    {
        Eisodos::$logger->trace('BEGIN', $this);

        $PreInclude = array();
        $PostInclude = array();

        // loading config files set in [PreInclude] section
        $this->_readSection('PreInclude', $PreInclude);
        foreach ($PreInclude as $n => $v) {
            $x = explode(':', $v);
            if (count($x) > 1 and $x[1] != '') {
                $this->importConfigSection($x[1], $x[0]);
            }
        }

        // loading config values from ini 'config' section
        $this->importConfigSection('config');

        // version handling for templateEngine
        $Versions = array();
        $this->_readSection('Versions', $Versions);
        $this->_activeVersionsString = Eisodos::$utils->safe_array_value($Versions, 'activeversions');
        Eisodos::$parameterHandler->setParam('TemplateVersionAlert', Eisodos::$utils->safe_array_value($Versions, 'templateversionalert'));

        // loading version sections in reverse order
        foreach (array_reverse(explode(',', $this->_activeVersionsString), true) as $v) {
            if ($v != '') {
                $this->importConfigSection($v);
            }
        }

        // loading config files set in [PostInclude] section
        $this->_readSection('PostInclude', $PostInclude);
        foreach ($PostInclude as $n => $v) {
            $x = explode(':', $v);
            $this->importConfigSection($x[1], $x[0]);
        }

        // overwrite config values set in constructor
        foreach ($configOverwrites_ as $key => $value) {
            Eisodos::$parameterHandler->setParam($key, $value);
        }

        Eisodos::$logger->trace('END', $this);
    }

    /**
     * Sets the desired extension by config type
     * @return string
     */
    private function _getExtension()
    {
        if ($this->_configType == self::CONFIG_TYPE_INI) {
            return '.conf';
        } else {
            return '.json';
        }
    }

    /**
     * Loads configuration file values to an array
     * @param string $section_ Section
     * @param array &$array_ Array of options
     * @param string $configFile_ Configuration file's name
     */
    private function _readSection($section_, &$array_ = array(), $configFile_ = '')
    {
        // Eisodos::trace('BEGIN',$this);

        if ($section_ == '') {
            return;
        }

        $section_ = strtolower($section_);
        $comment = '#';
        $group = '';

        $extension = $this->_getExtension();

        /*
           trying to find config file differentiated by the environment
           if no configFile given, it must be in a form {configPath}/{environment}-{applicationName}.ini|json
           if configFile set, it must be:
                {configFile} if absolute path was given
             or {configPath}/{environment}-{configFile}
             or {configPath}/{configFile}
        */
        if ($configFile_ == '') {
            $configFile = $this->_configPath . DIRECTORY_SEPARATOR . $this->_environment . Eisodos::$applicationName . $extension;
        } elseif (file_exists($configFile_)) {
            $configFile = $configFile_;
        } elseif (file_exists($this->_configPath . DIRECTORY_SEPARATOR . $this->_environment . $configFile_)) {
            $configFile = $this->_configPath . DIRECTORY_SEPARATOR . $this->_environment . $configFile_;
        } elseif (file_exists($this->_configPath . DIRECTORY_SEPARATOR . $configFile_)) {
            $configFile = $this->_configPath . DIRECTORY_SEPARATOR . $configFile_;
        } else {
            return;
        }

        // Eisodos::trace($configfile.' ['.$section_.']',$this);

        // check if config file already in the cache
        if (array_key_exists($configFile, $this->_configCache)
            and array_key_exists($section_, $this->_configCache[$configFile])) {
            $array_ = $this->_configCache[$configFile][$section_];
        }

        if ($this->_configType == self::CONFIG_TYPE_INI) {
            $fp = fopen($configFile, 'r');
            while (!@feof($fp)) {
                $line = trim(@fgets($fp));
                // skipping commented or empty lines
                if ($line and $line[0] != $comment) {
                    // section pattern [section]
                    if (preg_match('/^\[.*]$/', $line)) {
                        $group = strtolower(substr($line, 1, -1));
                    } else {
                        // parse key=value pair
                        $pieces = explode('=', $line, 2);
                        $option = strtolower($pieces[0]);
                        if (count($pieces) > 1) {
                            $value = trim($pieces[1], '" ');
                        } else {
                            $value = '';
                        }
                        // push section into the cache
                        $this->_configCache[$configFile][$group][$option] = $value;
                    }
                }
            }
            fclose($fp);

            // read values from the cache
            if (array_key_exists($section_, $this->_configCache[$configFile])) {
                $array_ = $this->_configCache[$configFile][$section_];
            }
        } else {
            try {
                // loads file directly into the config cache
                $this->_configCache[$configFile] = array_change_key_case(
                    json_decode(file_get_contents($configFile), true)
                );
                // read section from config cache
                if (array_key_exists($section_, $this->_configCache[$configFile])) {
                    $array_ = $array_ = $this->_configCache[$configFile][$section_];
                }
            } catch (Exception $e) {
            }
        }
        // Eisodos::trace('END',$this);

    }

    /**
     * Loading environment section of config file
     */
    private function _loadAndSetEnvironmentValues()
    {
        $L = array();
        $this->_readSection('Env', $L);
        foreach ($L as $key => $val) {
            if (!putenv($key . '=' . $val)) {
                PC::debug('PutEnv failed (' . $key . '=' . $val . ')');
            }
        }
    }

    // Public functions

    /**
     * ConfigLoader initializer
     * @param array $configOptions_ =[
     *     'configPath' => './config', // Configuration file's path
     *     'configType' => ConfigLoader::CONFIG_TYPE_INI,
     *     'environment' => '',        // environment variable
     *     'overwrites' => [
     *             $anykey => ''
     *         ]                       // Configuration value overwrites
     *     ] Config options
     */
    public function init(
        $configOptions_ = []
    ) {
        Eisodos::$logger->trace('BEGIN', $this);

        // setting default config options values
        $this->_configPath = Eisodos::$utils->safe_array_value(
            $configOptions_,
            'configPath',
            Eisodos::$parameterHandler->getParam('_applicationDir') . '/config'
        );
        $this->_configType = Eisodos::$utils->safe_array_value($configOptions_, 'configType', self::CONFIG_TYPE_INI);

        // setting environment variable
        // it must be
        //      {configPath}/environment file
        //   or {applicationName}_ENVIRONMENT environment variable
        //   or it can be set by in config options
        if (file_exists($this->_configPath . '/environment')) {
            $this->_environment = trim(file_get_contents($this->_configPath . '/environment')) . '-';
        } elseif (array_key_exists('environment', $configOptions_)) {
            $this->_environment = $configOptions_['environment'] . '-';
        } else {
            $this->_environment = getenv(Eisodos::$applicationName . '_ENVIRONMENT');
            if ($this->_environment === false) {
                $this->_environment = '';
            } else {
                $this->_environment .= '-';
            }
        }

        // add to global variables, cutting down the '-' sign
        Eisodos::$parameterHandler->setParam('_environment', substr($this->_environment, -1));

        if (!file_exists(
            $this->_configPath . '/' . $this->_environment .
            Eisodos::$applicationName . $this->_getExtension()
        )) {
            die(
                'ConfigPath(' . $this->_configPath . ') or AppName(' .
                Eisodos::$applicationName .
                ') not set or invalid! (' .
                $this->_configPath . '/' . $this->_environment .
                Eisodos::$applicationName .
                $this->_getExtension() .
                ') Could not initialize application!'
            );
        }

        // loading environments
        $this->_loadAndSetEnvironmentValues();

        // loading configuration
        $this->_loadMainConfiguration(
            array_key_exists('overwrites', $configOptions_) ? $configOptions_['overwrites'] : []
        );

        Eisodos::$logger->trace('END', $this);
    }

    /**
     * Loads configuration file's section to the parameters array
     * @param string $section_ Section of the configuration file [SECTION_NAME]
     * @param string $configFile_ Configuration file's name
     */
    public function importConfigSection($section_, $configFile_ = '')
    {
        $L = array();
        $this->_readSection($section_, $L, $configFile_);
        foreach ($L as $key => $val) {
            // add imported key values to the parameterHandler by replacing variables in the values
            Eisodos::$parameterHandler->setParam(
                $key,
                (strpos($val, '$') !== false ? Eisodos::$templateEngine->replaceParamInString($val) : $val)
            );
        }
    }

    public function initVersioning($developerVersion_)
    {
        if ($developerVersion_ != "") {
            $this->importConfigSection(
                $developerVersion_
            );                                 // [devversion] config szekcio beolvasasa
            $this->_activeVersionsString = $developerVersion_ . ($this->_activeVersionsString == "" ? "" : ",") . $this->_activeVersionsString;  // hozzafuzes az activeversionhoz
        }
        $this->_activeVersionsString .= ",";

        $this->_activeVersions = explode(",", $this->_activeVersionsString);

        // _activeVersions array contains the version prefixes, ex: v3., v2., empty
        Eisodos::$parameterHandler->setParam("AppVersion", $this->_activeVersions[0]);
        foreach ($this->_activeVersions as &$row) {
            if ($row) {
                $row = $row . ".";
            }
        }
    }

    public function loadParameterFilters(&$parameterFilters_)
    {
        $parameterFilterFilename = '';
        if (file_exists(
            $this->_configPath .
            DIRECTORY_SEPARATOR .
            $this->_environment .
            Eisodos::$applicationName .
            '.params'
        )) {
            $parameterFilterFilename = $this->_configPath . DIRECTORY_SEPARATOR . $this->_environment . Eisodos::$applicationName . '.params';
        } elseif (file_exists($this->_configPath . DIRECTORY_SEPARATOR . Eisodos::$applicationName . '.params')) {
            $parameterFilterFilename = $this->_configPath . DIRECTORY_SEPARATOR . Eisodos::$applicationName . '.params';
        }

        if ($parameterFilterFilename!='') {
          $parameterFilters_=explode("\n",file_get_contents($parameterFilterFilename));
        }
    }

    public function getActiveVersions() {
      return $this->_activeVersions;
    }
}