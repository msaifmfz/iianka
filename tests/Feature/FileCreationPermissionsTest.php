<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

test('stored uploads are not readable by other host users', function (): void {
    Storage::fake('local');

    Storage::disk('local')->put('crm-documents/1/quote.pdf', '%PDF-1.4');

    expect(fileperms(Storage::disk('local')->path('crm-documents/1/quote.pdf')) & 0007)->toBe(0);
});

test('compiled cache files are not readable by other host users', function (): void {
    $path = storage_path('framework/testing/compiled-'.uniqid().'.php');

    (new Filesystem)->replace($path, '<?php return [];');

    try {
        expect(fileperms($path) & 0007)->toBe(0);
    } finally {
        @unlink($path);
    }
});
