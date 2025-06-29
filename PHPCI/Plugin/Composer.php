<?php
/**
 * PHPCI - Continuous Integration for PHP
 *
 * @copyright    Copyright 2014, Block 8 Limited.
 * @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
 * @link         https://www.phptesting.org/
 */

namespace PHPCI\Plugin;

use PHPCI;
use PHPCI\Builder;
use PHPCI\Model\Build;

/**
 * Composer Plugin - Provides access to Composer functionality.
 * @author       Dan Cryer <dan@block8.co.uk>
 * @package      PHPCI
 * @subpackage   Plugins
 */
class Composer implements PHPCI\Plugin, PHPCI\ZeroConfigPlugin
{
    protected $directory;
    protected $action;
    protected $preferDist;
    protected $preferSource;
    protected $phpci;
    protected $build;
    protected $nodev;
    protected $phpPath;
    protected $version;
    protected $branch;

    /**
     * Check if this plugin can be executed.
     * @param $stage
     * @param Builder $builder
     * @param Build $build
     * @return bool
     */
    public static function canExecute($stage, Builder $builder, Build $build)
    {
        $path = $builder->buildPath . DIRECTORY_SEPARATOR . 'composer.json';

        if (file_exists($path) && $stage == 'setup') {
            return true;
        }

        return false;
    }

    /**
     * Set up the plugin, configure options, etc.
     * @param Builder $phpci
     * @param Build $build
     * @param array $options
     */
    public function __construct(Builder $phpci, Build $build, array $options = array())
    {
        $path = $phpci->buildPath;
        $this->phpci = $phpci;
        $this->build = $build;
        $this->directory = $path;
        $this->action = 'install';
        $this->preferDist = false;
        $this->preferSource = false;
        $this->nodev = false;
        $this->phpPath = null;
        $this->version = "1";
        $this->branch = $build->getBranch();

        $buildSettings = $phpci->getConfig('build_settings');
        if (isset($buildSettings['php'])) {
            $php = $buildSettings['php'];
            $this->phpPath = $php['path'];
            if(file_exists($this->phpPath) == false){
                $this->phpPath = $this->phpci->findBinary(array(basename($php['path'])));
            }
        }


        if (array_key_exists('directory', $options)) {
            $this->directory = $path . DIRECTORY_SEPARATOR . $options['directory'];
        }

        if (in_array($this->branch, ["master", "develop"]) || strpos(" ".$this->branch,"feature")>0) {
            $this->action = "update";
        } else {
            $this->action = "install";
        }

        if (array_key_exists('version', $options)) {
            $this->version = $options['version'];
        }

        if (array_key_exists('prefer_dist', $options)) {
            $this->preferDist = (bool)$options['prefer_dist'];
        }

        if (array_key_exists('prefer_source', $options)) {
            $this->preferDist = false;
            $this->preferSource = (bool)$options['prefer_source'];
        }

        if (array_key_exists('no_dev', $options)) {
            $this->nodev = (bool)$options['no_dev'];
        }

        if (array_key_exists('php_path', $options)) {
            $this->phpPath = (bool)$options['php_path'];
        }


    }

    /**
     * Executes Composer and runs a specified command (e.g. install / update)
     */
    public function execute()
    {
        /*if ($this->version == "1") {
            $composerLocation = $this->phpci->findBinary(array('composer', 'composer.phar'));
        } else {*/
            $composerLocation = $this->phpci->findBinary(array('composer2', 'composer2.phar'));
        //}
        $cmd = '';

        if (IS_WIN) {
            $cmd = 'php ';
        }
        if ($this->phpPath == null) {
            $this->phpPath = 'php';
        }
        $cmd = $this->phpPath . ' -d memory_limit=-1 ';
        if($this->action == "update"){
            if(file_exists($this->directory.DIRECTORY_SEPARATOR."composer-dev.json")){
                $cmd = "COMPOSER=composer-dev.json ".$cmd;
            }
            else{
                $this->phpci->log('composer-dev.json does not exist, using composer.json');
            }

        }


        $cmd .= $composerLocation . ' --no-ansi --no-interaction ';

        if ($this->preferDist) {
            $this->phpci->log('Using --prefer-dist flag');
            $cmd .= ' --prefer-dist';
        }

        if ($this->preferSource) {
            $this->phpci->log('Using --prefer-source flag');
            $cmd .= ' --prefer-source';
        }

        if ($this->nodev) {
            $this->phpci->log('Using --no-dev flag');
            $cmd .= ' --no-dev';
        }

        $cmd .= ' --working-dir="%s" %s';
        $this->phpci->log('Running (for branch ' . $this->branch . '): ' . sprintf($cmd, $this->directory, $this->action));

        return $this->phpci->executeCommand($cmd, $this->directory, $this->action);
    }
}
