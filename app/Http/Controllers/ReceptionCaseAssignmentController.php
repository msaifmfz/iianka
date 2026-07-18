<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Reception\ReceptionCaseWorkflow;
use App\Http\Controllers\Concerns\FlashesReceptionCaseToasts;
use App\Http\Requests\AssignReceptionCaseRequest;
use App\Http\Requests\StartReceptionCaseRequest;
use App\Models\ReceptionCase;
use Illuminate\Http\RedirectResponse;

class ReceptionCaseAssignmentController extends Controller
{
    use FlashesReceptionCaseToasts;

    public function __construct(private readonly ReceptionCaseWorkflow $workflow) {}

    public function assign(AssignReceptionCaseRequest $request, ReceptionCase $receptionCase): RedirectResponse
    {
        $applied = $this->workflow->assign(
            $receptionCase,
            $request->user(),
            (int) $request->validated('assigned_user_id'),
            $request->validated('memo'),
        );

        return $this->respondToTransition($applied, $receptionCase, 'assigned', '担当者を設定しました。');
    }

    public function start(StartReceptionCaseRequest $request, ReceptionCase $receptionCase): RedirectResponse
    {
        $applied = $this->workflow->start($receptionCase, $request->user(), $request->validated('memo'));

        return $this->respondToTransition($applied, $receptionCase, 'started', '対応を開始しました。');
    }
}
