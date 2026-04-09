<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SiteGuideFile;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SiteGuideFileController extends Controller
{
    public function show(Request $request, SiteGuideFile $siteGuideFile): BinaryFileResponse
    {
        abort_unless($request->user() !== null, 403);

        return response()->file(
            $siteGuideFile->absolutePath(),
            [
                'Content-Disposition' => 'inline; filename="'.addslashes($siteGuideFile->name).'"',
            ],
        );
    }
}
