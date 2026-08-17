<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EscapesLikeWildcards;
use App\Http\Presenters\SiteGuide\GuideFileSchedulePreviews;
use App\Http\Requests\StoreConstructionSiteRequest;
use App\Http\Requests\UpdateConstructionSiteRequest;
use App\Models\SiteGuideFile;
use Illuminate\Database\Eloquent\Builder;
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
    use EscapesLikeWildcards;

    public function index(Request $request, GuideFileSchedulePreviews $schedulePreviews): Response
    {
        $search = $request->string('search')->trim()->toString();

        return Inertia::render('construction-sites/index', [
            // Closures, so the partial reload that fetches the previews below
            // does not rebuild the listing and throw it away.
            'guideFiles' => fn (): array => $this->guideFilePayload(
                $this->guideFileQuery($search)->withCount('constructionSchedules')->get()
            ),
            'filters' => [
                'search' => $search,
            ],
            // The unfiltered size of the library, so the count tile can say
            // "shown / total" rather than silently reporting the filtered count.
            'totalCount' => fn (): int => SiteGuideFile::query()->count(),
            // Requested by the page the first time a card is held; most visits
            // never open one.
            'usageSchedules' => Inertia::optional(fn (): array => $schedulePreviews->forGuideFiles(
                $this->guideFileQuery($search)->pluck('id')
            )),
            'canManage' => $request->user()?->canManageContent() === true,
        ]);
    }

    public function create(): Response
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

    public function show(Request $request, SiteGuideFile $siteGuideFile, GuideFileSchedulePreviews $schedulePreviews): Response
    {
        $siteGuideFile->loadCount('constructionSchedules');

        return Inertia::render('construction-sites/show', [
            'guideFile' => $this->singleGuideFilePayload($siteGuideFile),
            // One file's usage list is the page's reason to exist, so it ships
            // with it rather than behind the optional prop the index uses.
            'usageSchedules' => $schedulePreviews->forGuideFile($siteGuideFile),
            'canManage' => $request->user()?->canManageContent() === true,
        ]);
    }

    public function edit(SiteGuideFile $siteGuideFile): Response
    {
        Gate::authorize('manage-content');

        // Loaded here too: replacing the file behind a guide changes every
        // schedule using it, so the form has to be able to say how many.
        $siteGuideFile->loadCount('constructionSchedules');

        return Inertia::render('construction-sites/form', [
            'guideFile' => $this->singleGuideFilePayload($siteGuideFile),
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

    public function destroy(SiteGuideFile $siteGuideFile): RedirectResponse
    {
        Gate::authorize('manage-content');

        $this->auditSuccess('site_guide_files.deleted', 'A site guide file was deleted.', $siteGuideFile);

        $siteGuideFile->delete();

        $this->flashToast('現場案内図を削除しました。');

        return redirect()
            ->route('construction-sites.index');
    }

    /**
     * @return Builder<SiteGuideFile>
     */
    private function guideFileQuery(string $search): Builder
    {
        return SiteGuideFile::query()
            ->when($search !== '', fn (Builder $query): Builder => $query
                ->whereRaw("name like ? escape '\\'", ['%'.$this->escapeLike($search).'%']))
            ->orderBy('name');
    }

    /**
     * Every caller loads the count first — via `withCount` on the listing or
     * `loadCount` on a single record. The null coalesce only covers the create
     * form, which has no guide file to count at all.
     *
     * @param  Collection<int, SiteGuideFile>  $files
     * @return list<array{id: int, name: string, url: string, mime_type: string|null, schedules_count: int}>
     */
    private function guideFilePayload(Collection $files): array
    {
        return $files->map(fn (SiteGuideFile $file): array => [
            'id' => $file->id,
            'name' => $file->name,
            'url' => $file->url(),
            'mime_type' => $file->mime_type,
            'schedules_count' => (int) ($file->construction_schedules_count ?? 0),
        ])->values()->all();
    }

    /**
     * @return array{id: int, name: string, url: string, mime_type: string|null, schedules_count: int}
     */
    private function singleGuideFilePayload(SiteGuideFile $file): array
    {
        return $this->guideFilePayload(collect([$file]))[0];
    }
}
