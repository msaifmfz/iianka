<?php

declare(strict_types=1);

it('has a well-formed changelog', function (): void {
    $path = dirname(__DIR__, 2).'/CHANGELOG.md';

    expect($path)->toBeReadableFile();

    preg_match_all('/^## \[(\d+\.\d+\.\d+)\]/m', (string) file_get_contents($path), $matches);

    $versions = $matches[1];

    // Guards the --prepend duplicate-section hazard on rc.2+.
    expect($versions)->not->toBeEmpty()
        ->and($versions)->toEqual(array_values(array_unique($versions)));

    $sorted = $versions;
    usort($sorted, static fn (string $a, string $b): int => version_compare($b, $a));

    expect($versions)->toEqual($sorted); // newest first
});
