import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const password = 'password';
const loginId = 'e2e-login';

async function login(page: Page) {
    await page.goto('/login');
    await page.getByLabel('ログインID').fill(loginId);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: 'ログイン' }).click();
    await expect(page).toHaveURL(/\/schedule-overview(?:\?.*)?$/);
}

test.describe('schedule search', () => {
    test('filters construction and business schedules by content', async ({
        page,
    }) => {
        await login(page);
        await page.goto('/schedule-search');

        await page.getByLabel('内容').fill('Overlapping timeline');

        await expect
            .poll(() => new URL(page.url()).searchParams.get('content'))
            .toBe('Overlapping timeline');
        await expect(
            page.getByRole('button', { name: /E2E Overlap Early/ }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: /E2E Overlap Late/ }),
        ).toBeVisible();
        await expect(page.locator('[data-search-result-key]')).toHaveCount(2);
    });
});
