export type ClientPlaceKind = 'office' | 'site' | 'other';

export type ClientSummary = {
    id: number;
    name: string;
    short_label: string;
    color: string;
};

export type ClientListItem = ClientSummary & {
    places_count: number;
    last_logged_at: string | null;
};

export type ClientContact = {
    id: number;
    name: string;
    title: string | null;
    phone: string | null;
    email: string | null;
    note: string | null;
};

export type ClientPlace = {
    id: number;
    client_id: number;
    kind: ClientPlaceKind;
    kind_label: string;
    name: string;
    address: string | null;
    lat: number;
    lng: number;
    archived_at: string | null;
    last_logged_at: string | null;
};

export type ClientDetail = ClientSummary & {
    note: string | null;
    contacts: ClientContact[];
    places: ClientPlace[];
};

export type CrmOption = { value: string; label: string };

export type MapPin = {
    id: number;
    client_id: number;
    kind: ClientPlaceKind;
    name: string;
    lat: number;
    lng: number;
    last_logged_at: string | null;
    archived_at: string | null;
};

export type ClientPlaceLogAttachment = {
    id: number;
    kind: 'image' | 'audio' | 'document';
    name: string;
    extension: string | null;
    url: string;
    opens_inline: boolean;
    duration_seconds: number | null;
};

export type ClientReaction = 'positive' | 'neutral' | 'negative';

export type ClientPlaceLog = {
    id: number;
    place: Pick<ClientPlace, 'id' | 'name' | 'kind_label' | 'archived_at'>;
    type: string;
    type_label: string;
    occurred_at: string;
    summary: string;
    reaction: ClientReaction | null;
    reaction_label: string | null;
    user: { id: number; name: string } | null;
    contact: { id: number; name: string } | null;
    attachments: ClientPlaceLogAttachment[];
    can_edit: boolean;
};

export type SelectedPlace = {
    place: ClientPlace;
    client: ClientSummary;
    contacts: { id: number; name: string; title: string | null }[];
    /** The newest page of the client-wide timeline. */
    logs: ClientPlaceLog[];
    logs_total: number;
    /** How many older entries are not in `logs` yet. */
    older_logs_count: number;
    /** The newest documents filed on this place or the whole client. */
    documents: ClientDocument[];
    documents_total: number;
};

export type ClientDocument = {
    id: number;
    name: string;
    extension: string | null;
    size: number | null;
    /** `YYYY-MM-DD`, the date printed on the document. */
    issued_on: string | null;
    created_at: string;
    url: string;
    /** PDFs and images open in the browser; Office files download. */
    opens_inline: boolean;
    place: { id: number; name: string } | null;
    uploader: { id: number; name: string } | null;
    can_delete: boolean;
};

/** Upload limits mirrored from ClientDocument. */
export type ClientDocumentLimits = {
    max_file_bytes: number;
    extensions: string[];
};

/** Attachment ceilings mirrored from ClientPlaceLogAttachment. */
export type CrmAttachmentLimits = {
    max_per_log: number;
    max_file_bytes: number;
    max_recording_seconds: number;
    image_extensions: string[];
    document_extensions: string[];
};
