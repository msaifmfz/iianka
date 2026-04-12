import { rmSync } from 'node:fs';

const e2eDatabasePath = '.playwright/database/e2e.sqlite';

export default function globalTeardown(): void {
    if (process.env.E2E_KEEP_DATABASE === '1') {
        return;
    }

    for (const path of [
        e2eDatabasePath,
        `${e2eDatabasePath}-shm`,
        `${e2eDatabasePath}-wal`,
    ]) {
        rmSync(path, { force: true });
    }
}
