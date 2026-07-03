<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FlashesReceptionCaseToasts;
use App\Http\Controllers\Concerns\RecordsReceptionCaseChanges;
use App\Http\Requests\SubmitReceptionCaseRequest;
use App\Http\Requests\UpdateReceptionCaseRequest;
use App\Models\ReceptionCase;
use App\Models\ReceptionDocumentType;
use App\Models\User;
use App\ReceptionCaseStatus;
use App\Services\BusinessDate;
use App\Services\ReceptionCasePresenter;
use App\Services\ReceptionCaseWorkflow;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReceptionCaseController extends Controller
{
    use FlashesReceptionCaseToasts;
    use RecordsReceptionCaseChanges;

    public function index(Request $request, ReceptionCasePresenter $presenter): Response
    {
        $this->authorize('viewAny', ReceptionCase::class);

        $user = $request->user();

        $reviewCases = collect();

        if ($user->canManageContent()) {
            $reviewCases = ReceptionCase::query()
                ->withListRelations($user->id)
                ->whereIn('status', [
                    ReceptionCaseStatus::Received->value,
                    ReceptionCaseStatus::Handover->value,
                ])
                ->highPriorityFirst()
                ->orderBy('due_on')
                ->latest('updated_at')
                ->get()
                ->sortBy(fn (ReceptionCase $case): int => $case->hasBeenSeenBy($user) ? 1 : 0)
                ->values();
        }

        $inProgressCases = ReceptionCase::query()
            ->withListRelations($user->id)
            ->where('status', ReceptionCaseStatus::InProgress->value)
            ->orderByRaw('case when due_on < ? then 0 else 1 end', [BusinessDate::today()->toDateString()])
            ->highPriorityFirst()
            ->orderBy('due_on')
            ->latest('updated_at')
            ->get();

        return Inertia::render('reception/cases/index', [
            'reviewCases' => $presenter->cases($reviewCases, $user),
            'inProgressCases' => $presenter->cases($inProgressCases, $user),
            'assigneeOptions' => $user->canManageContent() ? $this->assigneeOptions($presenter) : [],
        ]);
    }

    public function create(ReceptionCasePresenter $presenter): Response
    {
        $this->authorize('create', ReceptionCase::class);

        return Inertia::render('reception/cases/form', [
            'documentTypes' => $presenter->documentTypes($this->activeDocumentTypes()),
            'attachmentConstraints' => $presenter->attachmentConstraints(),
        ]);
    }

    public function show(Request $request, ReceptionCase $receptionCase, ReceptionCasePresenter $presenter): Response
    {
        $this->authorize('view', $receptionCase);

        $user = $request->user();

        $receptionCase->markSeenBy($user);

        $receptionCase = ReceptionCase::query()
            ->withListRelations($user->id, withActivities: true, withAttachments: true)
            ->findOrFail($receptionCase->id);

        return Inertia::render('reception/cases/show', [
            'caseData' => $presenter->case($receptionCase, $user),
            'documentTypes' => $presenter->documentTypes($this->documentTypesFor($receptionCase)),
            'assigneeOptions' => $user->canManageContent() ? $this->assigneeOptions($presenter) : [],
            'attachmentConstraints' => $presenter->attachmentConstraints(),
            'returnTo' => $this->returnTo($request),
        ]);
    }

    public function update(UpdateReceptionCaseRequest $request, ReceptionCase $receptionCase): RedirectResponse
    {
        DB::transaction(function () use ($request, $receptionCase): void {
            $receptionCase->update($request->receptionCaseAttributes());

            // Drafts are private pre-submission: keep them quiet (no activity,
            // no last_activity_at bump, no audit entry).
            if ($receptionCase->status === ReceptionCaseStatus::Draft) {
                return;
            }

            $this->recordReceptionCaseUpdate($receptionCase, $request->user(), 'A reception case was updated.');
        });

        $this->flashCaseToast($receptionCase, 'updated', '受付案件を更新しました。');

        return back();
    }

    public function submit(SubmitReceptionCaseRequest $request, ReceptionCase $receptionCase, ReceptionCaseWorkflow $workflow): RedirectResponse
    {
        $applied = $workflow->submit($receptionCase, $request->user(), $request->receptionCaseAttributes());

        return $this->respondToTransition(
            $applied,
            $receptionCase,
            'submitted',
            '受付を完了しました。',
            redirect()->route('reception.cases.create'),
        );
    }

    public function destroyDraft(Request $request, ReceptionCase $receptionCase): RedirectResponse
    {
        $this->authorize('deleteDraft', $receptionCase);

        DB::transaction(function () use ($receptionCase): void {
            $this->auditSuccess('reception_cases.draft_deleted', 'A reception draft was deleted.', $receptionCase);

            // Delete last: the model's deleting event cascades to the stored
            // attachment files, so no statement after this can roll the row back
            // while the files are already gone.
            $receptionCase->delete();
        });

        $this->flashToast('受付下書きを削除しました。');

        return redirect()->route('reception.home');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function assigneeOptions(ReceptionCasePresenter $presenter): array
    {
        return User::query()
            ->assignableAsReceptionHandler()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'login_id', 'is_hidden_from_workers'])
            ->map(fn (User $user): array => $presenter->user($user))
            ->values()
            ->all();
    }

    private function activeDocumentTypes(): EloquentCollection
    {
        return ReceptionDocumentType::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function documentTypesFor(ReceptionCase $receptionCase): EloquentCollection
    {
        return ReceptionDocumentType::query()
            ->where(fn ($query) => $query
                ->active()
                ->when(
                    $receptionCase->reception_document_type_id !== null,
                    fn ($query) => $query->orWhere('id', $receptionCase->reception_document_type_id),
                ))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{source: 'archive', filters: array{keyword: string, completed_from: string, completed_to: string}}|array{source: 'cases'}|array{source: 'home'}
     */
    private function returnTo(Request $request): array
    {
        $source = $request->string('from')->trim()->toString();

        if ($source === 'home') {
            return ['source' => 'home'];
        }

        if ($source !== 'archive') {
            return ['source' => 'cases'];
        }

        return [
            'source' => 'archive',
            'filters' => [
                'keyword' => $request->string('keyword')->trim()->toString(),
                'completed_from' => $request->string('completed_from')->trim()->toString(),
                'completed_to' => $request->string('completed_to')->trim()->toString(),
            ],
        ];
    }
}
