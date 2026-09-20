<?php

use App\Application\Crm\AttachmentTypeGuard;

/**
 * The HEIF header fallback only runs on hosts whose libmagic cannot name
 * HEIC/HEIF, so it is exercised here directly rather than through an upload.
 */
function heifHeader(string $brand): string
{
    return "\x00\x00\x00\x18ftyp".$brand."\x00\x00\x00\x00".$brand;
}

function writeTemporaryFile(string $contents): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'guard');
    file_put_contents($path, $contents);

    return $path;
}

test('every HEIF brand an iPhone writes is recognised from the file header', function (string $brand): void {
    $path = writeTemporaryFile(heifHeader($brand).str_repeat("\x00", 64));

    expect(new AttachmentTypeGuard()->looksLikeHeif($path))->toBeTrue();
})->with(['heic', 'heix', 'heim', 'heis', 'hevc', 'hevx', 'hevm', 'hevs', 'mif1', 'msf1']);

test('anything without a HEIF brand is not mistaken for one', function (string $contents): void {
    $path = writeTemporaryFile($contents);

    expect(new AttachmentTypeGuard()->looksLikeHeif($path))->toBeFalse();
})->with([
    'a Windows executable' => ['MZ'.str_repeat("\x00", 128)],
    'HTML' => ['<html><script>alert(1)</script></html>'],
    'an MP4, which shares the ftyp box' => ["\x00\x00\x00\x18ftypisom\x00\x00\x00\x00isomiso2"],
    'a truncated header' => ["\x00\x00\x00\x18ftyp"],
    'an empty file' => [''],
]);

test('a missing file is not accepted', function (): void {
    expect(new AttachmentTypeGuard()->looksLikeHeif('/nonexistent/none.heic'))->toBeFalse();
});
