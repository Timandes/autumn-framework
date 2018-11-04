<?php

namespace Autumn\Framework\Context;

use \RuntimeExcecption;

class ComposerLoader
{
    private $rootDir = '';

    public function __construct($rootDir)
    {
        $this->rootDir = $rootDir;
    }

    public function load()
    {
        $composerFile = 'composer.json';
        $rootDir = $this->rootDir;
        $composerPath = $rootDir . DIRECTORY_SEPARATOR . $composerFile;
        if (!file_exists($composerPath)) {
            throw new RuntimeExcecption("Cannot find composer file in {$composerPath}");
        }

        $composerJSON = file_get_contents($composerPath);
        $composerMeta = @json_decode($composerJSON, true);
        if (!$composerMeta) {
            throw new RuntimeExcecption("Fail to parse composer file {$composerPath}");
        }

        return $composerMeta;
    }

}