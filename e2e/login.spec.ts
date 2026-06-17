import { expect, test } from '@playwright/test';

const password = 'password';
const loginId = 'e2e-login';

test.describe('login', () => {
    test('allows a verified user to sign in with their login id', async ({
        page,
    }) => {
        await page.goto('/login');

        await expect(page.getByText('必須')).toHaveCount(2);

        await page.getByLabel('ログインID').fill(loginId);
        await page.getByLabel('パスワード').fill(password);
        await page.getByRole('button', { name: 'ログイン' }).click();

        await expect(page).toHaveURL(/\/schedule-overview(?:\?.*)?$/);
        await expect(
            page.getByRole('heading', { name: /^\d{4}年\d{1,2}月$/ }),
        ).toBeVisible();
    });
});
