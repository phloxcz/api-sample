<?php

declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;

final class Bootstrap
{
    public static function boot(): Configurator
    {
        $root = dirname(__DIR__);

        $configurator = new Configurator();
        $configurator->setDebugMode(false);
        $configurator->setTempDirectory($root . '/temp');
        $configurator->addStaticParameters([
            'appDir' => __DIR__,
            'dataDir' => $root . '/data',
        ]);
        $configurator->addConfig($root . '/config/config.neon');

        return $configurator;
    }
}
