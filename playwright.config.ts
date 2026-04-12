import { defineConfig, devices } from '@playwright/test';

const appUrl = 'http://127.0.0.1:8010';
const viteUrl = 'http://127.0.0.1:5174';
const e2eDatabasePath = '.playwright/database/e2e.sqlite';
const e2eEnv = {
    APP_ENV: 'testing',
    APP_URL: appUrl,
    BCRYPT_ROUNDS: '4',
    BROADCAST_CONNECTION: 'null',
    CACHE_STORE: 'array',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: e2eDatabasePath,
    DB_URL: '',
    MAIL_MAILER: 'array',
    QUEUE_CONNECTION: 'sync',
    SESSION_DRIVER: 'database',
    VITE_DEV_SERVER_HOST: '127.0.0.1',
    VITE_DEV_SERVER_PORT: '5174',
};
const e2eEnvPrefix = Object.entries(e2eEnv)
    .map(([key, value]) => `${key}=${value}`)
    .join(' ');

/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
// import dotenv from 'dotenv';
// import path from 'path';
// dotenv.config({ path: path.resolve(__dirname, '.env') });

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
    testDir: './e2e',
    /* Run tests in files in parallel */
    fullyParallel: true,
    /* Fail the build on CI if you accidentally left test.only in the source code. */
    forbidOnly: !!process.env.CI,
    /* Retry on CI only */
    retries: process.env.CI ? 2 : 0,
    /* Opt out of parallel tests on CI. */
    workers: process.env.CI ? 1 : undefined,
    /* Reporter to use. See https://playwright.dev/docs/test-reporters */
    reporter: 'html',
    /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
    use: {
        /* Base URL to use in actions like `await page.goto('')`. */
        baseURL: appUrl,

        /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
        trace: 'on-first-retry',
    },

    /* Configure projects for major browsers */
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },

        {
            name: 'firefox',
            use: { ...devices['Desktop Firefox'] },
        },

        {
            name: 'webkit',
            use: { ...devices['Desktop Safari'] },
        },

        /* Test against mobile viewports. */
        // {
        //   name: 'Mobile Chrome',
        //   use: { ...devices['Pixel 5'] },
        // },
        // {
        //   name: 'Mobile Safari',
        //   use: { ...devices['iPhone 12'] },
        // },

        /* Test against branded browsers. */
        // {
        //   name: 'Microsoft Edge',
        //   use: { ...devices['Desktop Edge'], channel: 'msedge' },
        // },
        // {
        //   name: 'Google Chrome',
        //   use: { ...devices['Desktop Chrome'], channel: 'chrome' },
        // },
    ],

    /* Run your local dev server before starting the tests */
    webServer: [
        {
            command: [
                'mkdir -p .playwright/database',
                `rm -f ${e2eDatabasePath}`,
                `touch ${e2eDatabasePath}`,
                `${e2eEnvPrefix} php artisan config:clear --no-interaction`,
                `${e2eEnvPrefix} php artisan migrate:fresh --force --seed --seeder=E2eSeeder --no-interaction`,
                `${e2eEnvPrefix} php artisan serve --host=127.0.0.1 --port=8010`,
            ].join(' && '),
            url: appUrl,
            reuseExistingServer: false,
        },
        {
            command: `${e2eEnvPrefix} npm run dev -- --host 127.0.0.1 --port 5174 --strictPort`,
            url: `${viteUrl}/@vite/client`,
            reuseExistingServer: false,
        },
    ],
    globalTeardown: './e2e/global-teardown.ts',
});
