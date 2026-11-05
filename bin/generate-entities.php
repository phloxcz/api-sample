<?php

declare(strict_types=1);

// Generates app/Entity from the database and config/entities.neon. Run after changing either.

use Phlox\EntityDatabase\Generator\EntityGenerator;

require __DIR__ . '/../vendor/autoload.php';

$container = App\Bootstrap::boot()->createContainer();

foreach ($container->getByType(EntityGenerator::class)->generate() as $file) {
    echo "{$file}\n";
}
