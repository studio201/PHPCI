<?php
/**
 * PHPCI - Continuous Integration for PHP
 *
 * @copyright    Copyright 2014, Block 8 Limited.
 * @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
 * @link         https://www.phptesting.org/
 */

namespace PHPCI\Plugin;

use PHPCI\Builder;
use PHPCI\Helper\Lang;
use PHPCI\Model\Build;
use PHPCI\Plugin\Util\TestResultParsers\Codeception as Parser;
use Psr\Log\LogLevel;

/**
 * Codeception Plugin - Enables full acceptance, unit, and functional testing.
 * @author       Don Gilbert <don@dongilbert.net>
 * @author       Igor Timoshenko <contact@igortimoshenko.com>
 * @author       Adam Cooper <adam@networkpie.co.uk>
 * @package      PHPCI
 * @subpackage   Plugins
 */
class Codeception implements \PHPCI\Plugin, \PHPCI\ZeroConfigPlugin
{
    /** @var string */
    protected $args = '';

    /** @var Builder */
    protected $phpci;

    /** @var Build */
    protected $build;

    /**
     * @var string $ymlConfigFile The path of a yml config for Codeception
     */
    protected $ymlConfigFile;

    /**
     * @var
     */
    protected $chromeDriverPath;

    /**
     * @var mixed
     */
    protected $chromeDriverStartStop;

    /**
     * @var string $path The path to the codeception tests folder.
     */
    protected $path;

    /**
     * @param $stage
     * @param Builder $builder
     * @param Build $build
     * @return bool
     */
    public static function canExecute($stage, Builder $builder, Build $build)
    {
        return $stage == 'test' && !is_null(self::findConfigFile($builder->buildPath));
    }

    /**
     * Try and find the codeception YML config file.
     * @param $buildPath
     * @return null|string
     */
    public static function findConfigFile($buildPath)
    {
        if (file_exists($buildPath.'codeception.yml')) {
            return 'codeception.yml';
        }

        if (file_exists($buildPath.'codeception.dist.yml')) {
            return 'codeception.dist.yml';
        }

        return null;
    }

    /**
     * Set up the plugin, configure options, etc.
     * @param Builder $phpci
     * @param Build $build
     * @param array $options
     */
    public function __construct(Builder $phpci, Build $build, array $options = array())
    {
        $this->phpci = $phpci;
        $this->build = $build;
        $this->path = 'tests'.DIRECTORY_SEPARATOR.'_output'.DIRECTORY_SEPARATOR;
        $this->chromeDriverPath = '/usr/bin/chromedriver';
        $this->chromeDriverStartStop = true;
        if (empty($options['config'])) {
            $this->ymlConfigFile = self::findConfigFile($this->phpci->buildPath);
        } else {
            $this->ymlConfigFile = $options['config'];
        }
        if (isset($options['args'])) {
            $this->args = (string)$options['args'];
        }
        if (isset($options['path'])) {
            $this->path = $this->phpci->interpolate($options['path']);
        }
        if (isset($options['chromedriver_path'])) {
            $this->chromeDriverPath = $options['chromedriver_path'];
        }
        if (isset($options['chromedriver_startstop'])) {
            $this->chromeDriverStartStop = $options['chromedriver_startstop'];
        }


    }

    /**
     * Runs Codeception tests
     */
    public function execute()
    {
        if (empty($this->ymlConfigFile)) {
            throw new \Exception("No configuration file found");
        }

        // Run any config files first. This can be either a single value or an array.
        return $this->runConfigFile($this->ymlConfigFile);
    }

    /**
     * Run tests from a Codeception config file.
     * @param $configPath
     * @return bool|mixed
     * @throws \Exception
     */
    protected function runConfigFile($configPath)
    {
        $this->phpci->logExecOutput(true);

        $codecept = $this->phpci->findBinary('codecept');

        if (!$codecept) {
            $this->phpci->logFailure(Lang::get('could_not_find', 'codecept'));

            return false;
        }
        $codeceptPath = $this->phpci->buildPath.$this->path;
        if (is_dir($codeceptPath) == false) {
            $this->phpci->log(
            'Codeception mkdir('.$codeceptPath.')',
            Loglevel::DEBUG
            );
            mkdir($codeceptPath, 0777, true);
        }
         $this->args.='-o "paths: output: '.$this->phpci->buildPath.$this->path.'"';
        
        if ($this->chromeDriverStartStop == true && $this->chromeDriverPath != '') {
            $cmdStart = 'if [ ! -f "chromedriver.pid" ]; then '.$this->chromeDriverPath.' --url-base=/wd/hub  2>&1 & echo $! >chromedriver.pid; fi';
            $successStart = $this->phpci->executeCommand($cmdStart);
            $this->phpci->log(
                'Codeception Start Server: '.$cmdStart,
                Loglevel::DEBUG
            );
        }


        $cmd = 'cd "%s" && '.$codecept.' run -c "%s" --xml '.$this->args;

        if (IS_WIN) {
            $cmd = 'cd /d "%s" && '.$codecept.' run -c "%s" --xml '.$this->args;
        }
        $this->phpci->log(
            'Codeception cmd: '.$cmd,
            Loglevel::DEBUG
        );
        $configPath = $this->phpci->buildPath.$configPath;
        $success = $this->phpci->executeCommand($cmd, $this->phpci->buildPath, $configPath);

        $this->phpci->log(
            'Codeception XML path: '.$this->phpci->buildPath.$this->path.'report.xml',
            Loglevel::DEBUG
        );

        $xml = file_get_contents($this->phpci->buildPath.$this->path.'report.xml', false);
        $parser = new Parser($this->phpci, $xml);
        $output = $parser->parse();

        $meta = array(
            'tests' => $parser->getTotalTests(),
            'timetaken' => $parser->getTotalTimeTaken(),
            'failures' => $parser->getTotalFailures(),
        );

        $this->build->storeMeta('codeception-meta', $meta);
        $this->build->storeMeta('codeception-data', $output);
        $this->build->storeMeta('codeception-errors', $parser->getTotalFailures());
        $this->phpci->logExecOutput(true);
        if ($this->chromeDriverStartStop == true && $this->chromeDriverPath != '') {
            $cmdStop = 'if [ -f "chromedriver.pid" ]; then kill `cat chromedriver.pid` && rm chromedriver.pid; fi';
            $successStop = $this->phpci->executeCommand($cmdStop);
            $this->phpci->log(
                'Codeception Stop Server: '.$cmdStop,
                Loglevel::DEBUG
            );
        }

        return $success;
    }
}
