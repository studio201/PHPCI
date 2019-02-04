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

/**
 * Copy Build Plugin - Copies the entire build to another directory.
 * @author       Dan Cryer <dan@block8.co.uk>
 * @package      PHPCI
 * @subpackage   Plugins
 */
class CopyBuild implements \PHPCI\Plugin
{
    protected $directory;
    protected $ignore;
    protected $wipe;
    protected $phpci;
    protected $build;

    /**
     * Set up the plugin, configure options, etc.
     * @param Builder $phpci
     * @param Build $build
     * @param array $options
     */
    public function __construct(Builder $phpci, Build $build, array $options = array())
    {
        $path = $phpci->buildPath;
        if (!isset($options['directory'])) {
            $options['directory'] = "../../../public/lastbuilds/%PROJECT%/";
        }
        $this->phpci = $phpci;
        $this->build = $build;
        $this->directory = isset($options['directory']) ? $this->phpci->interpolate($options['directory']) : $path;
        $this->wipe = isset($options['wipe']) ? (bool)$options['wipe'] : false;
        $this->ignore = isset($options['respect_ignore']) ? (bool)$options['respect_ignore'] : false;
    }

    /**
     * Copies files from the root of the build directory into the target folder
     */
    public function execute()
    {

        $build = $this->phpci->buildPath;
        $this->phpci->log('CopyBuild execute('.$build.')');
        if ($this->directory == $build) {
            return false;
        }
        if ($build."" === "".DIRECTORY_SEPARATOR) {
            $this->phpci->log('CopyBuild builddir '.$build);
        } else {
            $this->phpci->log('CopyBuild builddir not equal '.($build." === ".DIRECTORY_SEPARATOR));
            
        }

        $this->wipeExistingDirectory();
        $cmdMkdir = 'mkdir -p "%s"';
        if (IS_WIN) {
            $cmdMkdir = 'mkdir -p "%s"';
        }
        $this->phpci->log('CopyBuild execute cmd: '.(sprintf($cmdMkdir, $this->directory)));
        $success = $this->phpci->executeCommand($cmdMkdir, $this->directory);
        
        
        
        $cmd = ' cp -r -d %s* %s';
        if (IS_WIN) {
            $cmd = 'xcopy /E "%s" "%s"';
        }
        $this->phpci->log('CopyBuild execute cmd: '.(sprintf($cmd, $build, $this->directory)));
        $success = $this->phpci->executeCommand($cmd, $build, $this->directory);
        
        
        $this->phpci->log('CopyBuild execute deleteIgnoredFiles');
        $this->deleteIgnoredFiles();
        $this->phpci->log('CopyBuild execute done '.$success);

        return $success;
    }

    /**
     * Wipe the destination directory if it already exists.
     * @throws \Exception
     */
    protected function wipeExistingDirectory()
    {
        if ($this->wipe === true && $this->directory != '/' && is_dir($this->directory)) {
            $cmd = 'rm -Rf "%s*"';
            $success = $this->phpci->executeCommand($cmd, $this->directory);

            if (!$success) {
                throw new \Exception(Lang::get('failed_to_wipe', $this->directory));
            }
        }
    }

    /**
     * Delete any ignored files from the build prior to copying.
     */
    protected function deleteIgnoredFiles()
    {
        if ($this->ignore) {
            foreach ($this->phpci->ignore as $file) {
                $this->phpci->log('CopyBuild remove ignored '.$file);
                $cmd = 'rm -Rf "%s/%s"';
                if (IS_WIN) {
                    $cmd = 'rmdir /S /Q "%s\%s"';
                }
                $this->phpci->executeCommand($cmd, $this->directory, $file);
            }
        }
    }
}
