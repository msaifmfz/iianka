import { defineConfig, devices } from '@playwright/test';

const databasePath = '.playwright/database/e2e-production.sqlite';
const envPrefix = Object.entries({
    APP_ENV: 'testing',
    APP_URL: 'http://localhost:8011',
    APP_DEBUG: 'false',
    BCRYPT_ROUNDS: '4',
    BROADCAST_CONNECTION: 'null',
    CACHE_STORE: 'array',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: databasePath,
    DB_URL: '',
    MAIL_MAILER: 'array',
    QUEUE_CONNECTION: 'sync',
    SESSION_DRIVER: 'database',
    VITE_HOT_FILE: '.playwright/production.hot',
})
    .map(([key, value]) => `${key}=${value}`)
    .join(' ');

export default defineConfig({
    testDir: './e2e',
    testMatch: ['quotations.spec.ts', 'crm-map.spec.ts'],
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    workers: 1,
    reporter: 'list',
    use: {
        baseURL: 'http://localhost:8011',
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'chromium-production', use: { ...devices['Desktop Chrome'] } },
    ],
    webServer: [
        {
            command: [
                'npm run build',
                'mkdir -p .playwright/database',
                `rm -f ${databasePath} .playwright/production.hot`,
                `touch ${databasePath}`,
                `${envPrefix} php artisan migrate:fresh --force --seed --seeder=E2eSeeder --no-interaction`,
                `${envPrefix} php artisan serve --host=127.0.0.1 --port=8010 --no-reload`,
            ].join(' && '),
            url: 'http://127.0.0.1:8010/up',
            timeout: 120_000,
            reuseExistingServer: false,
        },
        {
            command:
                'nginx -e stderr -p "$PWD/" -c e2e/nginx.production.conf -g "daemon off;"',
            url: 'http://localhost:8011/up',
            reuseExistingServer: false,
        },
    ],
    globalTeardown: './e2e/global-teardown.ts',
});
