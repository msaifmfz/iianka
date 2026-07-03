<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ReceptionCase;
use App\ReceptionCaseStatus;
use App\Services\BusinessDate;
use App\Services\ReceptionCasePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceptionHomeController extends Controller
{
    public function index(Request $request, ReceptionCasePresenter $presenter): Response
    {
        $user = $request->user();

        $drafts = ReceptionCase::query()
            ->withListRelations($user->id)
            ->where('status', ReceptionCaseStatus::Draft->value)
            ->where('receptor_user_id', $user->id)
            ->latest('updated_at')
            ->get();

        $assignedTasks = ReceptionCase::query()
            ->withListRelations($user->id)
            ->where('assigned_user_id', $user->id)
            ->where('status', ReceptionCaseStatus::InProgress->value)
            ->orderByRaw('case when due_on < ? then 0 else 1 end', [BusinessDate::today()->toDateString()])
            ->highPriorityFirst()
            ->orderBy('due_on')
            ->latest('updated_at')
            ->get();

        return Inertia::render('reception/home', [
            'drafts' => $presenter->cases($drafts, $user),
            'assignedTasks' => $presenter->cases($assignedTasks, $user),
        ]);
    }
}
