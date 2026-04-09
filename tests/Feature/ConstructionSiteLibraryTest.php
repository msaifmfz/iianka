<?php

declare(strict_types=1);

use App\Models\ConstructionSite;
use App\Models\SiteGuideFile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('construction site library index shows site and file counts', function (): void {
    $admin = User::factory()->admin()->create();
    $site = ConstructionSite::factory()->create([
        'name' => '新宿ビル建設現場',
        'notes' => '北口集合',
    ]);

    SiteGuideFile::factory()->count(2)->create([
        'construction_site_id' => $site->id,
    ]);

    $this->actingAs($admin)
        ->get(route('construction-sites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-sites/index')
            ->where('canManage', true)
            ->has('sites', 1)
            ->where('sites.0.name', '新宿ビル建設現場')
            ->where('sites.0.notes', '北口集合')
            ->has('sites.0.guide_files', 2)
        );
});

test('construction site library detail shows uploaded files', function (): void {
    $user = User::factory()->create();
    $site = ConstructionSite::factory()->create([
        'name' => '渋谷駅前改修現場',
    ]);
    $guideFile = SiteGuideFile::factory()->create([
        'construction_site_id' => $site->id,
        'name' => '案内図A.pdf',
    ]);

    $this->actingAs($user)
        ->get(route('construction-sites.show', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-sites/show')
            ->where('canManage', false)
            ->where('site.name', '渋谷駅前改修現場')
            ->has('site.guide_files', 1)
            ->where('site.guide_files.0.id', $guideFile->id)
            ->where('site.guide_files.0.name', '案内図A.pdf')
            ->where('site.guide_files.0.url', route('site-guide-files.show', $guideFile))
        );
});

test('admins can open the construction site create form', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('construction-sites.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-sites/form')
            ->where('site', null)
        );
});

test('admins can open the construction site edit form', function (): void {
    $admin = User::factory()->admin()->create();
    $site = ConstructionSite::factory()->create([
        'name' => '大阪駅前新築現場',
        'address' => '大阪府大阪市北区',
        'notes' => '西口集合',
    ]);
    $guideFile = SiteGuideFile::factory()->create([
        'construction_site_id' => $site->id,
        'name' => '案内図B.png',
    ]);

    $this->actingAs($admin)
        ->get(route('construction-sites.edit', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('construction-sites/form')
            ->where('site.id', $site->id)
            ->where('site.name', '大阪駅前新築現場')
            ->where('site.address', '大阪府大阪市北区')
            ->where('site.notes', '西口集合')
            ->has('site.guide_files', 1)
            ->where('site.guide_files.0.id', $guideFile->id)
            ->where('site.guide_files.0.name', '案内図B.png')
        );
});
