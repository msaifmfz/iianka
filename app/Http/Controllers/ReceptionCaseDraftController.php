<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreReceptionDraftRequest;
use App\Http\Requests\UpdateReceptionDraftRequest;
use App\Models\ReceptionCase;
use App\Models\ReceptionCaseActivity;
use App\ReceptionCaseStatus;
use App\Services\ReceptionCaseNumberGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReceptionCaseDraftController extends Controller
{
    public function store(StoreReceptionDraftRequest $request, ReceptionCaseNumberGenerator $caseNumberGenerator): JsonResponse
    {
        // Each attempt runs in its own transaction. The number generator's
        // firstOrCreate on today's sequence row can lose a race the first time a
        // date is used (two concurrent inserts, one hits the unique constraint);
        // that attempt rolls back cleanly and the retry finds the committed row.
        $attempt = 0;

        while (true) {
            try {
                $case = DB::transaction(fn (): ReceptionCase => $this->createDraft($request, $caseNumberGenerator));

                return response()->json([
                    'id' => $case->id,
                    'case_number' => $case->case_number,
                ], 201);
            } catch (QueryException $exception) {
                if ($attempt === 1) {
                    throw $exception;
                }

                $attempt++;
            }
        }
    }

    private function createDraft(StoreReceptionDraftRequest $request, ReceptionCaseNumberGenerator $caseNumberGenerator): ReceptionCase
    {
        $case = ReceptionCase::query()->create([
            'case_number' => $caseNumberGenerator->next(),
            'status' => ReceptionCaseStatus::Draft,
            'receptor_user_id' => $request->user()->id,
            // last_activity_at stays null for drafts (private pre-submission);
            // it is first set on 受付完了.
            ...$request->receptionCaseAttributes(),
        ]);

        $case->recordActivity($request->user(), ReceptionCaseActivity::TYPE_CREATED_DRAFT, bumpsActivity: false);

        $this->auditSuccess('reception_cases.created', 'A reception draft was created.', $case);

        return $case;
    }

    public function update(UpdateReceptionDraftRequest $request, ReceptionCase $receptionCase): JsonResponse
    {
        abort_unless($receptionCase->status === ReceptionCaseStatus::Draft, 404);

        $receptionCase->update($request->receptionCaseAttributes());

        return response()->json([
            'saved_at' => now()->toISOString(),
        ]);
    }
}
