<?php

declare(strict_types=1);

namespace App\Domain\Reception\Enums;

enum ReceptionCaseActivityType: string
{
    case CreatedDraft = 'created_draft';
    case Submitted = 'submitted';
    case Updated = 'updated';
    case Assigned = 'assigned';
    case Started = 'started';
    case HandoverRequested = 'handover_requested';
    case Completed = 'completed';
    case AttachmentAdded = 'attachment_added';
    case AttachmentDeleted = 'attachment_deleted';
}
