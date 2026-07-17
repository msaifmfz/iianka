export function formatMinutesSeconds(seconds: number): string {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;

    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
}

export function formatBytes(size: number | null): string {
    if (size === null) {
        return '-';
    }

    if (size < 1024 * 1024) {
        return `${Math.max(1, Math.round(size / 1024))}KB`;
    }

    return `${(size / 1024 / 1024).toFixed(1)}MB`;
}
