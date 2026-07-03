<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FlashesReceptionCaseToasts;
use App\Http\Requests\UpdateReceptionCaseWorkMemoRequest;
use App\Models\ReceptionCase;
use Illuminate\Http\RedirectResponse;

class ReceptionCaseWorkMemoController extends Controller
{
    use FlashesReceptionCaseToasts;

    public function __invoke(UpdateReceptionCaseWorkMemoRequest $request, ReceptionCase $receptionCase): RedirectResponse
    {
        $receptionCase->update($request->validated());

        $this->flashCaseToast($receptionCase, 'work_memo_updated', '作業メモを保存しました。');

        return back();
    }
}
