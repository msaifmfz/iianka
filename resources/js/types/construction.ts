export type ConstructionUser = {
    id: number;
    name: string;
    email: string;
};

export type SiteGuideFile = {
    id: number;
    name: string;
    url: string;
    mime_type: string | null;
};

export type ConstructionSite = {
    id: number;
    name: string;
    address: string | null;
    notes?: string | null;
    guide_files: SiteGuideFile[];
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
    time: string;
    starts_at: string | null;
    ends_at: string | null;
    time_note: string | null;
    status: ConstructionScheduleStatus;
    meeting_place: string;
    personnel: string | null;
    location: string;
    general_contractor: string | null;
    person_in_charge: string | null;
    content: string;
    navigation_address: string;
    google_maps_url: string;
    voucher_checked: boolean;
    voucher_checked_at: string | null;
    voucher_checked_by: ConstructionUser | null;
    voucher_note: string | null;
    site: Pick<ConstructionSite, 'id' | 'name' | 'address'> | null;
    assigned_users: ConstructionUser[];
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
    content: string;
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
