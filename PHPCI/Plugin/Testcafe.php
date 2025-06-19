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

    protected $with_video = false;

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
        if (isset($options['browsers'])) {
            $this->browsers = (array)$options['browsers'];
        }
        else{
            $this->browsers = ["chrome:headless"];
        }
        if (isset($options['args'])) {
            $this->args = (string)$options['args'];
        }
        if (isset($options['path'])) {
            $this->path = $this->phpci->interpolate($options['path']);
        }
        if (isset($options['outputpath'])) {
            $this->outputpath = $this->phpci->interpolate($options['outputpath']);
        } else {
            $this->outputpath = $this->phpci->interpolate("_output/");
        }
        if (!empty($options['logging'])) {
            $this->logging = $options['logging'];
        }
        if (isset($options['with_video'])) {
            $this->with_video = (boolean)$options['with_video'];
        }
    }

    /**
     * Runs Testcafe tests
     */
    public function execute()
    {
        //if (is_dir($this->path) == false) {
        //    throw new \Exception("No tests folder found");
       // }
        if($this->with_video){
            // Run any config files first. This can be either a single value or an array.
            return $this->run2();
        }
        else{
            // Run any config files first. This can be either a single value or an array.
            return $this->run();
        }

    }

    /**
     * Run tests from a Testcafe config file.
     * @param $configPath
     * @return bool|mixed
     * @throws \Exception
     */
    protected function run()
    {
        $this->phpci->logExecOutput($this->logging);
        if(file_exists($this->phpci->buildPath."testcafe.config.json") == false){
            @copy($this->phpci->buildPath."testcafe.config.json.dist", $this->phpci->buildPath."testcafe.config.json");
        }
        $testcafe = $this->phpci->findBinary('testcafe');

        if (!$testcafe) {
            $this->phpci->logFailure(Lang::get('could_not_find', 'testcafe'));

            return false;
        }
        $testcafePath = $this->phpci->buildPath.$this->outputpath;

        if (is_dir($testcafePath) == false) {
            $this->phpci->log(
                'Testcafe mkdir('.$testcafePath.')',
                Loglevel::DEBUG
            );
            mkdir($testcafePath, 0777, true);
        }


        $this->args.=' -r xunit:'.$this->phpci->buildPath.$this->outputpath.'report.xml';
        $idCmd = 'id && '.$testcafe.' --version';
        $success = $this->phpci->executeCommand($idCmd, $this->phpci->buildPath);
        $this->phpci->log(
            'Testcafe cmd: '.$idCmd.' === '.$success ,
            Loglevel::CRITICAL
        );
        $cmd = 'cd "%s" && export NVM_DIR="$HOME/.nvm" && echo $NVM_DIR && . $NVM_DIR/nvm.sh && node -v ';
        foreach($this->browsers as $browser){
            $cmd.=' && node '.$testcafe.' '.$browser.' '.$this->args.' '.$this->path;
        }

        $this->phpci->log(
            'Testcafe cmd: '.$cmd,
            Loglevel::CRITICAL
        );

        $success = $this->phpci->executeCommand($cmd, $this->phpci->buildPath);

        $this->phpci->log(
            'Testcafe XML path: '.$this->phpci->buildPath.$this->outputpath.'report.xml',
            Loglevel::CRITICAL
        );
        if(file_exists($this->phpci->buildPath.$this->outputpath.'report.xml')){
            $xml = file_get_contents($this->phpci->buildPath.$this->outputpath.'report.xml', false);
            $this->phpci->log(
                'Testcafe exec: '.$success.', XML file content: '.$xml,
                Loglevel::CRITICAL
            );
            $parser = new Parser($this->phpci, $xml);
            $output = $parser->parse();
            $this->phpci->log(
                'Testcafe XML path: '.$this->phpci->buildPath.$this->outputpath.'report.xml: '.print_r($output, true)."; ".$xml,
                Loglevel::CRITICAL
            );


            $meta = array(
                'tests' => $parser->getTotalTests(),
                'timetaken' => $parser->getTotalTimeTaken(),
                'failures' => $parser->getTotalFailures(),
            );
            $this->phpci->log(
                'Testcafe tests: '.$parser->getTotalTests()
            );

            $this->phpci->log(
                'Testcafe time: '.$parser->getTotalTimeTaken()
            );
            $this->phpci->log(
                'Testcafe failed tests: '.$parser->getTotalFailures()
            );

            $this->build->storeMeta('testcafe-meta', $meta);
            $this->build->storeMeta('testcafe-data', $output);
            $this->build->storeMeta('testcafe-errors', $parser->getTotalFailures());
            $this->phpci->logExecOutput(true);
        }


        return $success;
    }
    protected function run2()
    {
        $this->phpci->logExecOutput($this->logging);

        if (!file_exists($this->phpci->buildPath . "testcafe.config.json")) {
            @copy($this->phpci->buildPath . "testcafe.config.json.dist", $this->phpci->buildPath . "testcafe.config.json");
        }

        $testcafe = $this->phpci->findBinary('testcafe');
        if (!$testcafe) {
            $this->phpci->logFailure(Lang::get('could_not_find', 'testcafe'));
            return false;
        }


        $buildPath = rtrim($this->phpci->buildPath, '/');
        $outputDir = $buildPath . '/' . ltrim($this->outputpath, '/');

        $webDir = null;
        if (is_dir($buildPath . '/web')) {
            $webDir = $buildPath . '/web';
        } elseif (is_dir($buildPath . '/public')) {
            $webDir = $buildPath . '/public';
        } else {
            // Keines vorhanden – fallback anlegen
            $webDir = $buildPath . '/web';
            mkdir($webDir, 0777, true);
            $this->phpci->log("Warnung: Weder 'web/' noch 'public/' gefunden. Fallback-Verzeichnis 'web/' wurde angelegt.", LogLevel::WARNING);
        }

        $videoFile = $webDir . '/recording.mp4';

        // Konfiguration für Aufnahme
        $width = 1280;
        $height = 700;
        $cropTop = 150;
        $cropBottom = 20;
        $recordHeight = $height + $cropTop + $cropBottom;

        // Xvfb starten
        $display = null;
        for ($d = 99; $d < 199; $d++) {
            $check = shell_exec("xdpyinfo -display :$d 2>/dev/null");
            if (empty($check)) {
                $display = ":$d";
                break;
            }
        }
        if (!$display) {
            $this->phpci->logFailure('Kein freier DISPLAY gefunden');
            return false;
        }
        putenv("DISPLAY=$display");

        $this->phpci->log("Starte Xvfb auf DISPLAY $display");
        $xvfbCmd = "Xvfb $display -screen 0 {$width}x{$recordHeight}x24 & echo $!";
        $xvfbPid = (int)shell_exec($xvfbCmd);
        sleep(2);

        // ffmpeg starten
        if (file_exists($videoFile)) {
            unlink($videoFile);
        }

        $cropFilter = "crop={$width}:{$height}:0:{$cropTop}";
        $ffmpegCmd = "ffmpeg -y -f x11grab -video_size {$width}x{$recordHeight} -i $display -filter:v \"$cropFilter\" \"$videoFile\" & echo $!";
        $this->phpci->log("Starte ffmpeg: $ffmpegCmd", LogLevel::DEBUG);
        $ffmpegPid = (int)shell_exec($ffmpegCmd);
        sleep(3);

        // TestCafe ausführen
        $this->args .= ' -r xunit:' . $outputDir . 'report.xml';
        $cmd = 'cd "%s" && export NVM_DIR="$HOME/.nvm" && . $NVM_DIR/nvm.sh && node -v';
        foreach ($this->browsers as $browser) {
            $browserArgs = "$browser --no-sandbox --disable-gpu '--window-size={$width},{$recordHeight}'";
            $cmd .= ' && node ' . $testcafe . ' ' . $browserArgs . ' ' . $this->args . ' ' . $this->path;
        }

        $this->phpci->log('Testcafe CMD: ' . $cmd, LogLevel::CRITICAL);
        $success = $this->phpci->executeCommand($cmd, $this->phpci->buildPath);

        // ffmpeg & Xvfb beenden
        if ($ffmpegPid) {
            $this->phpci->log("Beende ffmpeg (PID $ffmpegPid)");
            posix_kill($ffmpegPid, SIGINT);
            sleep(2);
        }
        if ($xvfbPid) {
            $this->phpci->log("Beende Xvfb (PID $xvfbPid)");
            posix_kill($xvfbPid, SIGTERM);
            sleep(1);
        }

        // XML parsen
        if (file_exists($outputDir . 'report.xml')) {
            $xml = file_get_contents($outputDir . 'report.xml', false);
            $parser = new Parser($this->phpci, $xml);
            $output = $parser->parse();

            $meta = [
                'tests' => $parser->getTotalTests(),
                'timetaken' => $parser->getTotalTimeTaken(),
                'failures' => $parser->getTotalFailures(),
            ];

            $this->build->storeMeta('testcafe-meta', $meta);
            $this->build->storeMeta('testcafe-data', $output);
            $this->build->storeMeta('testcafe-errors', $parser->getTotalFailures());
        }

        return $success;
    }

}
