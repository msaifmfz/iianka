import { parseBusinessDate } from '@/lib/dates';
import type { ScheduleType } from '@/lib/schedule-types';

export type { ScheduleType };

export type ScheduleTypeFilter = 'all' | ScheduleType;

export type ScheduleIndexFilters = {
    range: 'today' | 'week' | 'month';
    type: ScheduleType[];
    date: string;
    starts_on: string;
    ends_on: string;
    user_ids: number[];
};

export type CalendarDay = {
    date: string;
    count: number;
    construction_count: number;
    business_count: number;
    internal_notice_count: number;
    cleaning_duty_count: number;
};

export const scheduleTypes: ScheduleType[] = [
    'construction',
    'business',
    'internal_notice',
    'cleaning_duty',
];

export function allScheduleTypesSelected(types: ScheduleType[]) {
    return scheduleTypes.every((type) => types.includes(type));
}

export function toggleScheduleType(
    selectedTypes: ScheduleType[],
    type: ScheduleTypeFilter,
) {
    if (type === 'all') {
        return scheduleTypes;
    }

    const nextTypes = selectedTypes.includes(type)
        ? selectedTypes.filter((selectedType) => selectedType !== type)
        : [...selectedTypes, type];

    return nextTypes.length > 0 ? nextTypes : [type];
}

export function scheduleQuery(
    filters: ScheduleIndexFilters,
    query: Partial<ScheduleIndexFilters>,
) {
    return {
        range: query.range ?? filters.range,
        date: query.date ?? filters.date,
        type: query.type ?? filters.type,
        user_ids: query.user_ids ?? filters.user_ids,
    };
}

const scheduleDateFormatter = new Intl.DateTimeFormat('ja-JP', {
    month: 'long',
    day: 'numeric',
    weekday: 'short',
    timeZone: 'Asia/Tokyo',
});

export function formatScheduleDate(date: string) {
    return scheduleDateFormatter.format(parseBusinessDate(date));
}
