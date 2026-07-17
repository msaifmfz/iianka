import type { SetDataAction } from '@inertiajs/react';

type ScheduleTimeFields = {
    starts_at: string;
    ends_at: string;
    time_note: string;
};

/**
 * Shared start/end/time-note field logic for the schedule forms: picking a
 * time-note preset clears the concrete times, and setting a concrete time
 * clears a preset note (hand-written notes are kept).
 */
export function useScheduleTimeFields<T extends ScheduleTimeFields>(
    setData: SetDataAction<T>,
    timeNotePresets: readonly string[],
) {
    function selectTimeNotePreset(timeNote: string) {
        setData((values) => ({
            ...values,
            starts_at: '',
            ends_at: '',
            time_note: timeNote,
        }));
    }

    function clearPresetNote(timeNote: string) {
        return timeNotePresets.includes(timeNote) ? '' : timeNote;
    }

    function setStartTime(startsAt: string) {
        setData((values) => ({
            ...values,
            starts_at: startsAt,
            time_note: clearPresetNote(values.time_note),
        }));
    }

    function setEndTime(endsAt: string) {
        setData((values) => ({
            ...values,
            ends_at: endsAt,
            time_note: clearPresetNote(values.time_note),
        }));
    }

    function setTimeRange(startsAt: string, endsAt: string) {
        setData((values) => ({
            ...values,
            starts_at: startsAt,
            ends_at: endsAt,
            time_note: clearPresetNote(values.time_note),
        }));
    }

    return { selectTimeNotePreset, setStartTime, setEndTime, setTimeRange };
}
