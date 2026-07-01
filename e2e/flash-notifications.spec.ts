import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const password = 'password';
const editorLoginId = 'e2e-editor';

async function login(page: Page, userLoginId: string) {
    await page.goto('/login');
    await page.getByLabel('ログインID').fill(userLoginId);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: 'ログイン' }).click();
    await expect(page).toHaveURL(/\/schedule-overview(?:\?.*)?$/);
}

test.describe('flash notifications', () => {
    test('shows a dismissible success toast after saving profile settings', async ({
        page,
    }) => {
        await login(page, editorLoginId);

        await page.goto('/settings/profile');
        await page.getByLabel('名前').fill(`E2E Editor User ${Date.now()}`);
        await page.getByRole('button', { name: 'プロフィールを保存' }).click();

        const toast = page.getByRole('status').filter({
            hasText: 'プロフィールを保存しました。',
        });

        await expect(toast).toBeVisible();
        await toast.getByRole('button', { name: '通知を閉じる' }).click();
        await expect(toast).toBeHidden();
    });
});
