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
    site: Pick<ConstructionSite, 'id' | 'name' | 'address'> | null;
    assigned_users: ConstructionUser[];
    guide_files: SiteGuideFile[];
    selected_site_guide_file_ids: number[];
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

export type ScheduleEvent = ConstructionSchedule | BusinessSchedule;
