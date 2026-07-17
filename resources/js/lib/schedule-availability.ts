import type { AttendanceLeaveRecord, ScheduleAvailability } from '@/types';

export const preferredTimeSlots = [
    ['08:00', '10:00'],
    ['10:00', '12:00'],
    ['13:00', '15:00'],
    ['15:00', '17:00'],
    ['08:00', '12:00'],
    ['13:00', '17:00'],
    ['08:00', '17:00'],
] as const;

export function timeToMinutes(time: string) {
    const [hours, minutes] = time.slice(0, 5).split(':').map(Number);

    return hours * 60 + minutes;
}

export function timesOverlap(
    startsAt: string,
    endsAt: string,
    existingStartsAt: string,
    existingEndsAt: string,
) {
    return (
        timeToMinutes(startsAt) < timeToMinutes(existingEndsAt) &&
        timeToMinutes(endsAt) > timeToMinutes(existingStartsAt)
    );
}

export function conflictsWithSchedules(
    startsAt: string,
    endsAt: string,
    schedules: ScheduleAvailability[],
) {
    if (
        !startsAt ||
        !endsAt ||
        timeToMinutes(startsAt) >= timeToMinutes(endsAt)
    ) {
        return false;
    }

    return schedules.some((schedule) =>
        timesOverlap(startsAt, endsAt, schedule.starts_at, schedule.ends_at),
    );
}

export function matchingBusySchedules(
    schedules: ScheduleAvailability[],
    scheduledOn: string,
    assignedUserIds: number[],
) {
    if (assignedUserIds.length === 0) {
        return [];
    }

    return schedules.filter(
        (schedule) =>
            schedule.scheduled_on === scheduledOn &&
            schedule.user_ids.some((userId) =>
                assignedUserIds.includes(userId),
            ),
    );
}

export function matchingLeaveRecords(
    records: AttendanceLeaveRecord[],
    scheduledOn: string,
    assignedUserIds: number[],
) {
    if (assignedUserIds.length === 0) {
        return [];
    }

    return records.filter(
        (record) =>
            record.work_date === scheduledOn &&
            assignedUserIds.includes(record.user_id),
    );
}

export function availableTimeSlots(schedules: ScheduleAvailability[]) {
    return preferredTimeSlots.filter(
        ([startsAt, endsAt]) =>
            !conflictsWithSchedules(startsAt, endsAt, schedules),
    );
}
