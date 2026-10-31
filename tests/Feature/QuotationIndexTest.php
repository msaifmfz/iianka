<?php

declare(strict_types=1);

use App\Http\Controllers\QuotationController;
use App\Http\Controllers\QuotationPdfController;
use App\Models\Quotation;
use App\Models\User;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

mutates(QuotationController::class, QuotationPdfController::class);

test('quotation search trims input and matches client project or quotation number', function (string $field): void {
    $editor = User::factory()->create(['role' => UserRole::Editor]);
    $match = Quotation::factory()->for($editor, 'creator')->create([$field => 'prefix-needle-suffix']);
    Quotation::factory()->for($editor, 'creator')->create();

    $this->actingAs($editor)->get(route('quotations.index', ['search' => '  needle  ']))
        ->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->component('quotations/index')->where('search', 'needle')->where('canManage', true)
        ->has('quotations.data', 1)->where('quotations.total', 1)
        ->where('quotations.data.0', [
            'id' => $match->id, 'number' => $match->number, 'client_name' => $match->client_name,
            'project_name' => $match->project_name, 'issued_on' => '2026-10-29',
            'total' => '15000', 'item_count' => 1,
        ]));
})->with(['client_name', 'project_name', 'number']);

test('quotation search limits the normalized query to 120 characters', function (): void {
    $editor = User::factory()->create(['role' => UserRole::Editor]);
    $query = str_repeat('見', 120);
    $match = Quotation::factory()->for($editor, 'creator')->create(['client_name' => $query]);

    $this->actingAs($editor)->get(route('quotations.index', ['search' => '  '.$query.'余分  ']))
        ->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->where('search', $query)->has('quotations.data', 1)->where('quotations.data.0.id', $match->id));
});

test('quotation pages contain twenty newest matches and retain their search query', function (): void {
    $editor = User::factory()->create(['role' => UserRole::Editor]);
    $quotes = Quotation::factory()->count(21)->for($editor, 'creator')->create(['project_name' => 'needle']);

    $this->actingAs($editor)->get(route('quotations.index', ['search' => 'needle']))
        ->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->has('quotations.data', 20)->where('quotations.total', 21)->where('quotations.per_page', 20)
        ->where('quotations.data.0.id', $quotes[20]->id)->where('quotations.data.19.id', $quotes[1]->id)
        ->where('quotations.next_page_url', route('quotations.index', ['search' => 'needle', 'page' => 2])));
});

test('quotation management controls reflect the signed-in role', function (UserRole $role, bool $canManage): void {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('quotations.index'))
        ->assertOk()->assertInertia(fn (Assert $page): Assert => $page->where('canManage', $canManage));
})->with([
    'viewer' => [UserRole::Viewer, false],
    'editor' => [UserRole::Editor, true],
    'admin' => [UserRole::Admin, true],
]);

test('quotation edit forms use historical prices and the current editor defaults', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00:00+09:00'));
    $editor = User::factory()->create(['role' => UserRole::Editor, 'name' => '編集担当']);
    $quote = Quotation::factory()->for($editor, 'creator')->create();
    $snapshot = $quote->catalog_snapshot;
    $snapshot['0001']['price'] = '987.65';
    $quote->update(['catalog_snapshot' => $snapshot]);

    $this->actingAs($editor)->get(route('quotations.edit', $quote))
        ->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->component('quotations/form')->where('quotation.id', $quote->id)
        ->where('catalog.0.price', '987.65')->where('today', '2026-10-30')->where('defaultPerson', '編集担当')
        ->has('categories', 4)->where('categories.0', 'アンカー工事'));
});

test('quotation read endpoints enforce the configured viewing ability', function (Closure $url): void {
    $editor = User::factory()->create(['role' => UserRole::Editor]);
    $quote = Quotation::factory()->for($editor, 'creator')->create();
    Gate::define('view-all-content', fn (User $user): bool => false);

    $this->actingAs($editor)->get($url($quote))->assertForbidden();
})->with([
    'index' => [fn (Quotation $quote): string => route('quotations.index')],
    'show' => [fn (Quotation $quote): string => route('quotations.show', $quote)],
    'PDF' => [fn (Quotation $quote): string => route('quotations.pdf', [$quote, 'quotation'])],
]);
