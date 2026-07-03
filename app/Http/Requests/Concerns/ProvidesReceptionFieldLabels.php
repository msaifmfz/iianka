<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * The Japanese attribute labels shared by every reception request, used to
 * humanize validation messages. Kept separate from field validation so requests
 * that only carry a memo/assignee (assign, start, handover, complete) can reuse
 * the labels without pulling in the intake-field validation machinery.
 */
trait ProvidesReceptionFieldLabels
{
    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_name' => '会社名',
            'site_name' => '現場名',
            'reception_document_type_id' => '案件書類',
            'reception_content' => '受付内容',
            'due_on' => '期限',
            'scheduled_on' => '予定日',
            'priority' => '優先度',
            'assigned_user_id' => '担当者',
            'memo' => '作業メモ',
            'name' => '案件書類名',
            'sort_order' => '表示順',
            'is_active' => '有効状態',
        ];
    }
}
