import { rmSync } from 'node:fs';

const e2eDatabasePaths = ['chromium', 'firefox', 'webkit', 'production'].map(
    (browser) => `.playwright/database/e2e-${browser}.sqlite`,
);

export default function globalTeardown(): void {
    if (process.env.E2E_KEEP_DATABASE === '1') {
        return;
    }

    for (const databasePath of e2eDatabasePaths) {
        for (const path of [
            databasePath,
            `${databasePath}-shm`,
            `${databasePath}-wal`,
        ]) {
            rmSync(path, { force: true });
        }
    }
}
