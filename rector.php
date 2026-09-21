<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use RectorLaravel\Rector\ClassMethod\MigrateToSimplifiedAttributeRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/bootstrap/providers.php',
        __DIR__.'/config',
        __DIR__.'/database/factories',
        __DIR__.'/database/migrations',
        __DIR__.'/database/seeders',
        __DIR__.'/rector.php',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php85: true)
    ->withSets([
        LaravelSetList::LARAVEL_130,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
        carbon: true,
        phpunitCodeQuality: true,
    )
    ->withComposerBased(phpunit: true, laravel: true)
    ->withSkip([
        MigrateToSimplifiedAttributeRector::class,
    ])
    ->withImportNames(removeUnusedImports: true)
    ->withCache(__DIR__.'/storage/framework/cache/rector')
    ->withParallel();
