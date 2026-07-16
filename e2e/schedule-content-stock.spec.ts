import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

const password = 'password';
const editorLoginId = 'e2e-editor';
const stockName = 'E2E養生テープ';

async function login(page: Page, userLoginId: string) {
    await page.goto('/login');
    await page.getByLabel('ログインID').fill(userLoginId);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: 'ログイン' }).click();
    await expect(page).toHaveURL(/\/schedule-overview(?:\?.*)?$/);
}

async function openContentEditor(page: Page): Promise<Locator> {
    await page.goto('/construction-schedules/create');

    const editor = page.locator('[aria-labelledby="schedule-content-label"]');

    await editor.click();

    return editor;
}

function stockListbox(page: Page): Locator {
    return page.getByRole('listbox', { name: '在庫を選択' });
}

test.describe('schedule content stock picker', () => {
    test('slash right after text opens the picker and inserts a spaced mention', async ({
        page,
    }) => {
        await login(page, editorLoginId);
        const editor = await openContentEditor(page);

        await editor.pressSequentially('作業/');
        await expect(stockListbox(page)).toBeVisible();

        await editor.pressSequentially('養生');
        await stockListbox(page)
            .getByRole('option', { name: new RegExp(stockName) })
            .click();

        await expect(stockListbox(page)).toBeHidden();
        // The space before the mention keeps it recognizable by the
        // backend parser's word-boundary rules.
        await expect(editor).toContainText(`作業 ${stockName}`);
    });

    test('slashes in a URL scheme do not open the picker', async ({ page }) => {
        await login(page, editorLoginId);
        const editor = await openContentEditor(page);

        await editor.pressSequentially('https://');
        await page.waitForTimeout(300);

        await expect(stockListbox(page)).toBeHidden();
    });
});
