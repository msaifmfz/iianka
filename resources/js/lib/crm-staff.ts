import { STAFF_COLOR_PALETTE } from '@/lib/crm-colors';
import type { CrmMapPin, CrmActivitySummary } from '@/types';

/** Stable across map filters, staff renames, and newly added staff. */
export function staffColor(userId: number | null): string {
    return userId === null
        ? '#6b7280'
        : STAFF_COLOR_PALETTE[(userId - 1) % STAFF_COLOR_PALETTE.length];
}

export function displayedActivity(
    pin: CrmMapPin,
    staffId: number | null,
): CrmActivitySummary | null {
    return staffId === null
        ? pin.latest_activity
        : (pin.staff_activities.find(
              (activity) => activity.user?.id === staffId,
          ) ?? null);
}

export function staffName(activity: CrmActivitySummary | null): string {
    return activity === null
        ? '記録なし'
        : (activity.user?.name ?? '担当者不明');
}

export function sortedStaff(
    activities: CrmActivitySummary[],
): CrmActivitySummary[] {
    return [...activities].sort((a, b) =>
        staffName(a).localeCompare(staffName(b), 'ja'),
    );
}
