<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConstructionSiteRequest;
use App\Http\Requests\UpdateConstructionSiteRequest;
use App\Models\SiteGuideFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ConstructionSiteController extends Controller
{
    public function index(Request $request): Response
    {
        $guideFiles = SiteGuideFile::query()
            ->orderBy('name')
            ->get();

        return Inertia::render('construction-sites/index', [
            'guideFiles' => $this->guideFilePayload($guideFiles),
            'canManage' => $request->user()?->is_admin === true,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return Inertia::render('construction-sites/form', [
            'guideFile' => null,
        ]);
    }

    public function store(StoreConstructionSiteRequest $request): RedirectResponse
    {
        /** @var array{name: string, guide_file: UploadedFile} $validated */
        $validated = $request->validated();
        $file = $validated['guide_file'];

        SiteGuideFile::query()->create([
            'name' => $validated['name'],
            'disk' => 'local',
            'path' => $file->store('site-guides', 'local'),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return redirect()
            ->route('construction-sites.index')
            ->with('status', '現場案内図を追加しました。');
    }

    public function show(SiteGuideFile $siteGuideFile): Response
    {
        return Inertia::render('construction-sites/show', [
            'guideFile' => $this->guideFilePayload(collect([$siteGuideFile]))->first(),
            'canManage' => request()->user()?->is_admin === true,
        ]);
    }

    public function edit(Request $request, SiteGuideFile $siteGuideFile): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return Inertia::render('construction-sites/form', [
            'guideFile' => $this->guideFilePayload(collect([$siteGuideFile]))->first(),
        ]);
    }

    public function update(UpdateConstructionSiteRequest $request, SiteGuideFile $siteGuideFile): RedirectResponse
    {
        $attributes = $request->safe()->only('name');

        if ($request->hasFile('guide_file')) {
            $file = $request->file('guide_file');

            $attributes = [
                ...$attributes,
                'disk' => 'local',
                'path' => $file->store('site-guides', 'local'),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        $siteGuideFile->update($attributes);

        return redirect()
            ->route('construction-sites.show', $siteGuideFile)
            ->with('status', '現場案内図を更新しました。');
    }

    public function destroy(Request $request, SiteGuideFile $siteGuideFile): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $siteGuideFile->delete();

        return redirect()
            ->route('construction-sites.index')
            ->with('status', '現場案内図を削除しました。');
    }

    /**
     * @param  Collection<int, SiteGuideFile>  $files
     * @return Collection<int, array<string, mixed>>
     */
    private function guideFilePayload(Collection $files): Collection
    {
        return $files->map(fn (SiteGuideFile $file): array => [
            'id' => $file->id,
            'name' => $file->name,
            'url' => $file->url(),
            'mime_type' => $file->mime_type,
        ])->values();
    }
}
