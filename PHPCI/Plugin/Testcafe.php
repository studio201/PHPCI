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
 * Testcafe Plugin - Enables full acceptance, unit, and functional testing.
 * @author       Thomas Krahmer <tk@studio201.de>
 * @package      PHPCI
 * @subpackage   Plugins
 */
class Testcafe implements \PHPCI\Plugin, \PHPCI\ZeroConfigPlugin
{
     /** @var string */
    protected $browsers = '';

    /** @var string */
    protected $args = '';

    /** @var Builder */
    protected $phpci;

    /** @var Build */
    protected $build;

    /**
     * @var string $ymlConfigFile The path of a yml config for Testcafe
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
     * @var string $path The path to the testcafe tests folder.
     */
    protected $path;

    /**
     * @var string $outputpath The output path to the testcafe tests folder.
     */
    protected $outputpath;

    /**
     * @var bool
     */
    protected $logging = false;

    /**
     * @param $stage
     * @param Builder $builder
     * @param Build $build
     * @return bool
     */
    public static function canExecute($stage, Builder $builder, Build $build)
    {
        return $stage == 'test';
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

        //$this->testcafePath = '/usr/local/bin/testcafe';


        if (isset($options['args'])) {
            $this->args = (string)$options['args'];
        }
        if (isset($options['path'])) {
            $this->path = $this->phpci->interpolate($options['path']);
        }
        if (isset($options['outputpath'])) {
            $this->outputpath = $this->phpci->interpolate($options['outputpath']);
        } else {
            $this->outputpath = $this->phpci->interpolate("output/");
        }
        if (!empty($options['logging'])) {
            $this->logging = $options['logging'];
        }
    }

    /**
     * Runs Codeception tests
     */
    public function execute()
    {
        if (is_dir($this->path) == false) {
            throw new \Exception("No tests folder found");
        }

        // Run any config files first. This can be either a single value or an array.
        return $this->run();
    }

    /**
     * Run tests from a Codeception config file.
     * @param $configPath
     * @return bool|mixed
     * @throws \Exception
     */
    protected function run()
    {
        $this->phpci->logExecOutput($this->logging);

        $testcafe = $this->phpci->findBinary('testcafe');

        if (!$testcafe) {
            $this->phpci->logFailure(Lang::get('could_not_find', 'testcafe'));

            return false;
        }
        $testcafePath = $this->phpci->buildPath.$this->outputpath;

        if (is_dir($testcafePath) == false) {
            $this->phpci->log(
                'Codeception mkdir('.$testcafePath.')',
                Loglevel::DEBUG
            );
            mkdir($testcafePath, 0777, true);
        }


        if ($this->args == "") {
            $this->args = "chrome";
        }

        $this->args.=' -r xunit:'.$this->phpci->buildPath.$this->outputpath.'/report.xml"';

        $cmd = 'cd "%s" && '.$testcafe.' '.$this->args.' '.$this->path;
        $this->phpci->log(
            'Testcafe cmd: '.$cmd,
            Loglevel::DEBUG
        );

        $success = $this->phpci->executeCommand($cmd, $this->phpci->buildPath);

        $this->phpci->log(
            'Testcafe XML path: '.$this->phpci->buildPath.$this->outputpath.'report.xml',
            Loglevel::DEBUG
        );

        $xml = file_get_contents($this->phpci->buildPath.$this->outputpath.'report.xml', false);
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

        return $success;
    }
}
