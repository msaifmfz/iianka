<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConstructionSiteRequest;
use App\Http\Requests\UpdateConstructionSiteRequest;
use App\Models\ConstructionSite;
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
        $sites = ConstructionSite::query()
            ->with('guideFiles')
            ->orderBy('name')
            ->get()
            ->map(fn (ConstructionSite $site) => [
                'id' => $site->id,
                'name' => $site->name,
                'address' => $site->address,
                'notes' => $site->notes,
                'guide_files' => $this->guideFilePayload($site->guideFiles),
            ]);

        return Inertia::render('construction-sites/index', [
            'sites' => $sites,
            'canManage' => $request->user()?->is_admin === true,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return Inertia::render('construction-sites/form', [
            'site' => null,
        ]);
    }

    public function store(StoreConstructionSiteRequest $request): RedirectResponse
    {
        $site = ConstructionSite::create($request->safe()->except('guide_files'));
        $this->storeGuideFiles($site, $request->file('guide_files', []));

        return redirect()
            ->route('construction-sites.index')
            ->with('status', '現場案内図を作成しました。');
    }

    public function show(ConstructionSite $constructionSite): Response
    {
        $constructionSite->load('guideFiles');

        return Inertia::render('construction-sites/show', [
            'site' => [
                'id' => $constructionSite->id,
                'name' => $constructionSite->name,
                'address' => $constructionSite->address,
                'notes' => $constructionSite->notes,
                'guide_files' => $this->guideFilePayload($constructionSite->guideFiles),
            ],
            'canManage' => request()->user()?->is_admin === true,
        ]);
    }

    public function edit(Request $request, ConstructionSite $constructionSite): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        $constructionSite->load('guideFiles');

        return Inertia::render('construction-sites/form', [
            'site' => [
                'id' => $constructionSite->id,
                'name' => $constructionSite->name,
                'address' => $constructionSite->address,
                'notes' => $constructionSite->notes,
                'guide_files' => $this->guideFilePayload($constructionSite->guideFiles),
            ],
        ]);
    }

    public function update(UpdateConstructionSiteRequest $request, ConstructionSite $constructionSite): RedirectResponse
    {
        $constructionSite->update($request->safe()->except('guide_files'));
        $this->storeGuideFiles($constructionSite, $request->file('guide_files', []));

        return redirect()
            ->route('construction-sites.show', $constructionSite)
            ->with('status', '現場案内図を更新しました。');
    }

    public function destroy(Request $request, ConstructionSite $constructionSite): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $constructionSite->delete();

        return redirect()
            ->route('construction-sites.index')
            ->with('status', '現場案内図を削除しました。');
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function storeGuideFiles(ConstructionSite $site, array $files): void
    {
        foreach ($files as $file) {
            $site->guideFiles()->create([
                'name' => $file->getClientOriginalName(),
                'disk' => 'public',
                'path' => $file->store('site-guides', 'public'),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    /**
     * @param  Collection<int, SiteGuideFile>  $files
     * @return Collection<int, array<string, mixed>>
     */
    private function guideFilePayload(Collection $files): Collection
    {
        return $files->map(fn (SiteGuideFile $file) => [
            'id' => $file->id,
            'name' => $file->name,
            'url' => $file->url(),
            'mime_type' => $file->mime_type,
        ])->values();
    }
}
