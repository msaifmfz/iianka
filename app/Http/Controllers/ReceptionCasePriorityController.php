<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FlashesReceptionCaseToasts;
use App\Http\Controllers\Concerns\RecordsReceptionCaseChanges;
use App\Http\Requests\UpdateReceptionCasePriorityRequest;
use App\Models\ReceptionCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ReceptionCasePriorityController extends Controller
{
    use FlashesReceptionCaseToasts;
    use RecordsReceptionCaseChanges;

    public function __invoke(UpdateReceptionCasePriorityRequest $request, ReceptionCase $receptionCase): RedirectResponse
    {
        DB::transaction(function () use ($request, $receptionCase): void {
            $receptionCase->update(['priority' => $request->priority()]);

            $this->recordReceptionCaseUpdate($receptionCase, $request->user(), 'A reception case priority was updated.');
        });

        $this->flashCaseToast($receptionCase, 'updated', '優先度を更新しました。');

        return back();
    }
}
