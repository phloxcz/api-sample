<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// The API takes over in Application::$onStartup, once everything has started.
// Requests elsewhere go on to the application, as usual.
App\Bootstrap::boot()
    ->createContainer()
    ->getByType(Nette\Application\Application::class)
    ->run();
