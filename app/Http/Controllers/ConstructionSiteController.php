<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConstructionSiteRequest;
use App\Http\Requests\UpdateConstructionSiteRequest;
use App\Models\SiteGuideFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
            'canManage' => $request->user()?->canManageContent() === true,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('manage-content');

        return Inertia::render('construction-sites/form', [
            'guideFile' => null,
        ]);
    }

    public function store(StoreConstructionSiteRequest $request): RedirectResponse
    {
        /** @var array{name: string, guide_file: UploadedFile} $validated */
        $validated = $request->validated();
        $file = $validated['guide_file'];

        $siteGuideFile = SiteGuideFile::query()->create([
            'name' => $validated['name'],
            'disk' => 'local',
            'path' => $file->store('site-guides', 'local'),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        $this->auditSuccess('site_guide_files.created', 'A site guide file was created.', $siteGuideFile, [
            'mime_type' => $siteGuideFile->mime_type,
            'size' => $siteGuideFile->size,
        ]);

        $this->flashToast('現場案内図を追加しました。', resource: [
            'type' => 'site_guide_file',
            'id' => $siteGuideFile->id,
            'action' => 'created',
            'label' => $siteGuideFile->name,
        ]);

        return redirect()
            ->route('construction-sites.index');
    }

    public function show(SiteGuideFile $siteGuideFile): Response
    {
        return Inertia::render('construction-sites/show', [
            'guideFile' => $this->guideFilePayload(collect([$siteGuideFile]))->first(),
            'canManage' => request()->user()?->canManageContent() === true,
        ]);
    }

    public function edit(Request $request, SiteGuideFile $siteGuideFile): Response
    {
        Gate::authorize('manage-content');

        return Inertia::render('construction-sites/form', [
            'guideFile' => $this->guideFilePayload(collect([$siteGuideFile]))->first(),
        ]);
    }

    public function update(UpdateConstructionSiteRequest $request, SiteGuideFile $siteGuideFile): RedirectResponse
    {
        $attributes = $request->safe()->only('name');
        $previousDisk = null;
        $previousPath = null;

        if ($request->hasFile('guide_file')) {
            $file = $request->file('guide_file');
            $previousDisk = $siteGuideFile->disk;
            $previousPath = $siteGuideFile->path;

            $attributes = [
                ...$attributes,
                'disk' => 'local',
                'path' => $file->store('site-guides', 'local'),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        $siteGuideFile->update($attributes);

        // Remove the replaced file only after the row points at the new one.
        if ($previousDisk !== null && $previousPath !== null) {
            Storage::disk($previousDisk)->delete($previousPath);
        }

        $this->auditSuccess('site_guide_files.updated', 'A site guide file was updated.', $siteGuideFile, [
            'changed' => array_values(array_diff(array_keys($siteGuideFile->getChanges()), ['updated_at'])),
            'replaced_file' => $request->hasFile('guide_file'),
        ]);

        $this->flashToast('現場案内図を修正しました。', resource: [
            'type' => 'site_guide_file',
            'id' => $siteGuideFile->id,
            'action' => 'updated',
            'label' => $siteGuideFile->name,
        ]);

        return redirect()
            ->route('construction-sites.show', $siteGuideFile);
    }

    public function destroy(Request $request, SiteGuideFile $siteGuideFile): RedirectResponse
    {
        Gate::authorize('manage-content');

        $this->auditSuccess('site_guide_files.deleted', 'A site guide file was deleted.', $siteGuideFile);

        $siteGuideFile->delete();

        $this->flashToast('現場案内図を削除しました。');

        return redirect()
            ->route('construction-sites.index');
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
