import { expect, test } from '@playwright/test';

const password = 'password';
const loginId = 'e2e-login';

test.describe('login', () => {
    test('allows a verified user to sign in with their login id', async ({
        page,
    }) => {
        await page.goto('/login');
        await page.getByLabel('ログインID').fill(loginId);
        await page.locator('input[name="password"]').fill(password);
        await page.getByRole('button', { name: 'ログイン' }).click();

        await expect(page).toHaveURL(/\/construction-schedules(?:\?.*)?$/);
        await expect(
            page.getByRole('heading', { name: '予定表' }),
        ).toBeVisible();
    });
});
