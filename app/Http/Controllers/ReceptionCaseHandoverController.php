<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FlashesReceptionCaseToasts;
use App\Http\Requests\HandoverReceptionCaseRequest;
use App\Models\ReceptionCase;
use App\Services\ReceptionCaseWorkflow;
use Illuminate\Http\RedirectResponse;

class ReceptionCaseHandoverController extends Controller
{
    use FlashesReceptionCaseToasts;

    public function __construct(private readonly ReceptionCaseWorkflow $workflow) {}

    public function __invoke(HandoverReceptionCaseRequest $request, ReceptionCase $receptionCase): RedirectResponse
    {
        $applied = $this->workflow->handover($receptionCase, $request->user(), $request->validated('memo'));

        return $this->respondToTransition($applied, $receptionCase, 'handed_over', '引継ぎを依頼しました。');
    }
}
