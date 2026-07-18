<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Reception\Enums\ReceptionCaseStatus;
use App\Http\Presenters\Reception\ReceptionCasePresenter;
use App\Models\ReceptionCase;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceptionArchiveController extends Controller
{
    public function index(Request $request, ReceptionCasePresenter $presenter): Response
    {
        $this->authorize('viewArchive', ReceptionCase::class);

        $keyword = $request->string('keyword')->trim()->toString();
        $completedFrom = $request->string('completed_from')->trim()->toString();
        $completedTo = $request->string('completed_to')->trim()->toString();

        $cases = ReceptionCase::query()
            ->withListRelations($request->user()->id)
            ->where('status', ReceptionCaseStatus::Completed->value)
            ->when($keyword !== '', fn ($query) => $query->where(function ($query) use ($keyword): void {
                $query->where('case_number', 'like', "%{$keyword}%")
                    ->orWhere('company_name', 'like', "%{$keyword}%")
                    ->orWhere('site_name', 'like', "%{$keyword}%")
                    ->orWhere('reception_content', 'like', "%{$keyword}%");
            }))
            ->when($completedFrom !== '', fn ($query) => $query->whereDate('completed_at', '>=', $completedFrom))
            ->when($completedTo !== '', fn ($query) => $query->whereDate('completed_at', '<=', $completedTo))
            ->latest('completed_at')
            ->get();

        return Inertia::render('reception/archive/index', [
            'cases' => $presenter->cases($cases, $request->user()),
            'filters' => [
                'keyword' => $keyword,
                'completed_from' => $completedFrom,
                'completed_to' => $completedTo,
            ],
        ]);
    }
}
