export type ConstructionUser = {
    id: number;
    name: string;
    email: string | null;
    is_hidden_from_workers?: boolean;
};

export type ConstructionSubcontractor = {
    id: number;
    name: string;
    phone: string;
};

export type SiteGuideFile = {
    id: number;
    name: string;
    url: string;
    mime_type: string | null;
};

export type ConstructionScheduleStatus =
    | 'scheduled'
    | 'confirmed'
    | 'postponed'
    | 'canceled';

export type ConstructionSchedule = {
    id: number;
    type: 'construction';
    scheduled_on: string;
    schedule_number: number | null;
    time: string;
    starts_at: string | null;
    ends_at: string | null;
    time_note: string | null;
    status: ConstructionScheduleStatus;
    meeting_place: string | null;
    personnel: string | null;
    location: string;
    general_contractor: string | null;
    person_in_charge: string | null;
    content: string | null;
    navigation_address: string | null;
    google_maps_url: string | null;
    voucher_checked: boolean;
    voucher_checked_at: string | null;
    voucher_checked_by: ConstructionUser | null;
    voucher_note: string | null;
    assigned_users: ConstructionUser[];
    subcontractors: ConstructionSubcontractor[];
    guide_files: SiteGuideFile[];
    selected_site_guide_file_ids: number[];
};

export type VoucherConfirmationSchedule = {
    id: number;
    scheduled_on: string;
    time: string;
    starts_at: string | null;
    location: string;
    general_contractor: string | null;
    person_in_charge: string | null;
    content: string | null;
    voucher_checked: boolean;
    voucher_checked_at: string | null;
    voucher_checked_by: ConstructionUser | null;
    voucher_note: string | null;
    assigned_users: ConstructionUser[];
};

export type BusinessSchedule = {
    id: number;
    type: 'business';
    scheduled_on: string;
    schedule_number: number | null;
    time: string;
    starts_at: string | null;
    ends_at: string | null;
    time_note: string | null;
    personnel: string | null;
    location: string;
    general_contractor: string | null;
    person_in_charge: string | null;
    content: string;
    memo: string | null;
    assigned_users: ConstructionUser[];
};

export type InternalNotice = {
    id: number;
    type: 'internal_notice';
    scheduled_on: string;
    time: string;
    starts_at: string | null;
    ends_at: string | null;
    time_note: string | null;
    title: string;
    location: string | null;
    content: string;
    memo: string | null;
    assigned_users: ConstructionUser[];
};

export type CleaningDutyRule = {
    id: number;
    weekday: number;
    weekday_label: string;
    label: string;
    location: string | null;
    notes: string | null;
    is_active: boolean;
    sort_order: number;
    assigned_users: ConstructionUser[];
};

export type CleaningDutySchedule = {
    id: number;
    type: 'cleaning_duty';
    scheduled_on: string;
    time: string;
    starts_at: null;
    ends_at: null;
    time_note: string;
    title: string;
    location: string | null;
    content: string;
    memo: string | null;
    rule_id: number;
    weekday: number;
    weekday_label: string;
    assigned_users: ConstructionUser[];
};

export type ScheduleEvent =
    | ConstructionSchedule
    | BusinessSchedule
    | InternalNotice
    | CleaningDutySchedule;

export type ScheduleAvailability = {
    id: number;
    type: 'construction' | 'business' | 'internal_notice';
    title: string;
    scheduled_on: string;
    starts_at: string;
    ends_at: string;
    time: string;
    user_ids: number[];
    user_names: string[];
};

export type AttendanceStatus = 'working' | 'leave';

export type AttendanceRecord = {
    id: number;
    user_id: number;
    work_date: string;
    status: AttendanceStatus;
    note: string | null;
    user: ConstructionUser;
};

export type AttendanceLeaveRecord = {
    id: number;
    user_id: number;
    user_name: string;
    work_date: string;
    note: string | null;
};
