<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCleaningDutyRuleRequest;
use App\Http\Requests\UpdateCleaningDutyRuleRequest;
use App\Models\CleaningDutyRule;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CleaningDutyRuleController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        $rules = CleaningDutyRule::query()
            ->with('assignedUsers:id,name,email')
            ->orderBy('weekday')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('cleaning-duty-rules/index', [
            'rules' => $this->rulePayload($rules),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return Inertia::render('cleaning-duty-rules/form', [
            'rule' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreCleaningDutyRuleRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $rule = CleaningDutyRule::create($this->ruleAttributes($validated));
        $rule->assignedUsers()->sync($request->input('assigned_user_ids', []));

        return redirect()
            ->route('cleaning-duty-rules.index')
            ->with('status', '掃除当番設定を作成しました。');
    }

    public function show(CleaningDutyRule $cleaningDutyRule): RedirectResponse
    {
        return redirect()->route('cleaning-duty-rules.edit', $cleaningDutyRule);
    }

    public function edit(Request $request, CleaningDutyRule $cleaningDutyRule): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        $cleaningDutyRule->load('assignedUsers:id,name,email');

        return Inertia::render('cleaning-duty-rules/form', [
            'rule' => $this->rulePayload(collect([$cleaningDutyRule]))->first(),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateCleaningDutyRuleRequest $request, CleaningDutyRule $cleaningDutyRule): RedirectResponse
    {
        $validated = $request->validated();
        $cleaningDutyRule->update($this->ruleAttributes($validated));
        $cleaningDutyRule->assignedUsers()->sync($request->input('assigned_user_ids', []));

        return redirect()
            ->route('cleaning-duty-rules.index')
            ->with('status', '掃除当番設定を更新しました。');
    }

    public function destroy(Request $request, CleaningDutyRule $cleaningDutyRule): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $cleaningDutyRule->delete();

        return redirect()
            ->route('cleaning-duty-rules.index')
            ->with('status', '掃除当番設定を削除しました。');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'weekdayOptions' => collect(CleaningDutyRule::WEEKDAY_LABELS)
                ->map(fn (string $label, int $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function ruleAttributes(array $validated): array
    {
        return [
            'weekday' => $validated['weekday'],
            'label' => $validated['label'] ?? '掃除当番',
            'location' => $validated['location'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ];
    }

    /**
     * @param  Collection<int, CleaningDutyRule>  $rules
     * @return Collection<int, array<string, mixed>>
     */
    private function rulePayload(Collection $rules): Collection
    {
        return $rules->map(fn (CleaningDutyRule $rule): array => [
            'id' => $rule->id,
            'weekday' => $rule->weekday,
            'weekday_label' => $rule->weekdayLabel(),
            'label' => $rule->label,
            'location' => $rule->location,
            'notes' => $rule->notes,
            'is_active' => $rule->is_active,
            'sort_order' => $rule->sort_order,
            'assigned_users' => $rule->assignedUsers->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values(),
        ])->values();
    }
}
