<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FlashesReceptionCaseToasts;
use App\Http\Requests\CompleteReceptionCaseRequest;
use App\Models\ReceptionCase;
use App\Services\ReceptionCaseWorkflow;
use Illuminate\Http\RedirectResponse;

class ReceptionCaseCompletionController extends Controller
{
    use FlashesReceptionCaseToasts;

    public function __construct(private readonly ReceptionCaseWorkflow $workflow) {}

    public function __invoke(CompleteReceptionCaseRequest $request, ReceptionCase $receptionCase): RedirectResponse
    {
        $applied = $this->workflow->complete($receptionCase, $request->user());

        return $this->respondToTransition(
            $applied,
            $receptionCase,
            'completed',
            '受付案件を完了しました。',
            redirect()->route('reception.archive.index'),
        );
    }
}
