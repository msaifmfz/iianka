<?php

declare(strict_types=1);

namespace App\Application\Reception;

use App\Domain\Reception\Enums\ReceptionCaseActivityType;
use App\Models\BusinessSchedule;
use App\Models\ConstructionSchedule;
use App\Models\ReceptionCase;
use App\Models\User;
use App\Services\BusinessDate;
use Illuminate\Http\Request;

/**
 * Resolves a schedule's reception source, maps intake data into create-form
 * defaults, and records the cross-domain creation activity.
 */
class ReceptionScheduleSource
{
    public function fromRequest(Request $request): ?ReceptionCase
    {
        $sourceId = $request->input('reception_case_id');

        if ($sourceId === null || $sourceId === '') {
            return null;
        }

        abort_unless(is_numeric($sourceId) && (int) $sourceId > 0, 404);

        return $this->find((int) $sourceId);
    }

    public function find(?int $sourceId): ?ReceptionCase
    {
        if ($sourceId === null) {
            return null;
        }

        return ReceptionCase::query()
            ->with([
                // Only the name is read, and only by the business-schedule form.
                'documentType:id,name',
            ])
            ->findOrFail($sourceId);
    }

    /**
     * @return array<string, mixed>
     */
    public function constructionFormValues(?ReceptionCase $source): array
    {
        if (! $source instanceof ReceptionCase) {
            return ['sourceReceptionCase' => null];
        }

        return [
            ...$this->sharedFormValues($source),
            'initialContent' => $source->reception_content,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function businessFormValues(?ReceptionCase $source): array
    {
        if (! $source instanceof ReceptionCase) {
            return ['sourceReceptionCase' => null];
        }

        return [
            ...$this->sharedFormValues($source),
            // Business schedules pick their content from a fixed option list, so
            // the document type maps onto it and the free-text intake note falls
            // through to the memo instead.
            'initialContent' => $source->documentType?->name,
            'initialMemo' => $source->reception_content,
        ];
    }

    /**
     * The create-form defaults both schedule types map identically.
     *
     * @return array<string, mixed>
     */
    private function sharedFormValues(ReceptionCase $source): array
    {
        return [
            'sourceReceptionCase' => $this->payload($source),
            'initialScheduledOn' => $this->scheduledOn($source),
            'initialAssignedUserIds' => $this->assignedUserIds($source),
            'initialLocation' => $source->site_name,
            'initialGeneralContractor' => $source->company_name,
        ];
    }

    /**
     * @return array{id: int, case_number: string, status: string, status_label: string, company_name: string|null, site_name: string|null}|null
     */
    public function payload(?ReceptionCase $source): ?array
    {
        if (! $source instanceof ReceptionCase) {
            return null;
        }

        return [
            'id' => $source->id,
            'case_number' => $source->case_number,
            'status' => $source->status->value,
            'status_label' => $source->status->label(),
            'company_name' => $source->company_name,
            'site_name' => $source->site_name,
        ];
    }

    public function recordCreated(
        ReceptionCase $source,
        User $actor,
        ConstructionSchedule|BusinessSchedule $schedule,
    ): void {
        $scheduleType = $schedule instanceof ConstructionSchedule ? '工事予定' : '業務予定';

        // No status transition: creating a schedule leaves the case where it is.
        $source->recordActivity($actor, ReceptionCaseActivityType::ScheduleCreated, [
            'memo' => "{$scheduleType}「{$schedule->location}」を作成しました。",
        ]);
    }

    private function scheduledOn(ReceptionCase $source): string
    {
        return $source->scheduled_on?->toDateString()
            ?? $source->due_on?->toDateString()
            ?? BusinessDate::today()->toDateString();
    }

    /**
     * @return list<int>
     */
    private function assignedUserIds(ReceptionCase $source): array
    {
        return $source->assigned_user_id === null ? [] : [$source->assigned_user_id];
    }
}
