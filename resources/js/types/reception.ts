export type ReceptionCaseStatus =
    | 'draft'
    | 'received'
    | 'in_progress'
    | 'handover'
    | 'completed';

export type ReceptionCasePriority = 'normal' | 'middle' | 'high';

export type ReceptionPriorityOption = {
    value: ReceptionCasePriority;
    label: string;
};

export type ReceptionMeta = {
    statusLabels: Record<ReceptionCaseStatus, string>;
    priorityLabels: Record<ReceptionCasePriority, string>;
    priorityOptions: ReceptionPriorityOption[];
};

export type ReceptionCaseAttachmentKind =
    | 'document'
    | 'image'
    | 'audio'
    | 'video';

export type ReceptionCaseAttachmentSource = 'upload' | 'capture' | 'recording';

export type ReceptionCaseAttachmentPreviewMode =
    | 'image'
    | 'pdf'
    | 'audio'
    | 'video'
    | 'download';

export type ReceptionUser = {
    id: number;
    name: string;
    email: string | null;
    login_id: string;
    is_hidden_from_workers: boolean;
};

export type ReceptionDocumentType = {
    id: number;
    name: string;
    sort_order: number;
    is_active: boolean;
    reception_cases_count?: number | null;
    created_at: string | null;
    updated_at: string | null;
};

export type ReceptionCaseCan = {
    view: boolean;
    update: boolean;
    attach_files: boolean;
    update_priority: boolean;
    update_work_memo: boolean;
    delete_draft: boolean;
    submit: boolean;
    assign: boolean;
    start: boolean;
    handover: boolean;
    complete: boolean;
};

export type ReceptionCaseActivity = {
    id: number;
    type:
        | 'created_draft'
        | 'submitted'
        | 'updated'
        | 'assigned'
        | 'started'
        | 'handover_requested'
        | 'completed'
        | 'attachment_added'
        | 'attachment_deleted';
    memo: string | null;
    from_status: ReceptionCaseStatus | null;
    to_status: ReceptionCaseStatus | null;
    from_assigned_user: ReceptionUser | null;
    to_assigned_user: ReceptionUser | null;
    user: ReceptionUser | null;
    created_at: string | null;
};

export type ReceptionAttachmentConstraints = {
    max_attachments: number;
    max_recordings: number;
    max_recording_seconds: number;
    max_file_kilobytes: number;
    accept: string;
};

export type ReceptionCaseAttachment = {
    id: number;
    kind: ReceptionCaseAttachmentKind;
    kind_label: string;
    source: ReceptionCaseAttachmentSource;
    source_label: string;
    name: string;
    url: string;
    download_url: string;
    preview_mode: ReceptionCaseAttachmentPreviewMode;
    mime_type: string | null;
    extension: string | null;
    size: number | null;
    duration_seconds: number | null;
    uploaded_by: ReceptionUser | null;
    created_at: string | null;
};

export type ReceptionCase = {
    id: number;
    case_number: string;
    status: ReceptionCaseStatus;
    status_label: string;
    priority: ReceptionCasePriority;
    company_name: string | null;
    site_name: string | null;
    reception_document_type_id: number | null;
    document_type: ReceptionDocumentType | null;
    reception_content: string | null;
    work_memo: string | null;
    due_on: string | null;
    scheduled_on: string | null;
    receptor: ReceptionUser | null;
    assigned_user: ReceptionUser | null;
    assigned_user_handover_chain: ReceptionUser[];
    completed_at: string | null;
    completed_by: ReceptionUser | null;
    last_activity_at: string | null;
    last_seen_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    is_unseen: boolean;
    attachments: ReceptionCaseAttachment[];
    activities: ReceptionCaseActivity[];
    can: ReceptionCaseCan;
};
