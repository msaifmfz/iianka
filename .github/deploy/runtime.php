<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;

try {
    $release = $argv[1] ?? '';
    $root = $argv[2] ?? '';
    if (! is_dir($release) || ! is_dir($root)) {
        throw new RuntimeException('Invalid application paths.');
    }

    require $release.'/vendor/autoload.php';
    $application = require $release.'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    $database = $application['config']->get('database.connections.sqlite.database');
    $expectedDatabase = realpath($root.'/shared/database/database.sqlite');
    if ($application['config']->get('database.default') !== 'sqlite'
        || $application['config']->get('database.connections.sqlite.url')
        || ! is_string($database)
        || $expectedDatabase === false
        || realpath($database) !== $expectedDatabase) {
        throw new RuntimeException('Deployment requires the reviewed environment-specific SQLite path.');
    }

    if (realpath($application->storagePath()) !== realpath($root.'/shared/storage')
        || $application['config']->get('app.maintenance.driver', 'file') !== 'file') {
        throw new RuntimeException('Deployment requires the reviewed shared storage and file maintenance driver.');
    }

    if (! $application['config']->get('app.key')) {
        throw new RuntimeException('Application key is missing.');
    }

    $kernel = $application->make(HttpKernel::class);
    $request = Request::create('/up');
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException('Application health route failed.');
    }
} catch (Throwable $exception) {
    // Runtime config, SQL and exception messages may contain secrets; never print them.
    fwrite(STDERR, 'Release boot check failed ('.$exception::class.").\n");
    exit(1);
}
