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

function stockMention(editor: Locator): Locator {
    return editor.locator('.stock-mention').filter({ hasText: stockName });
}

async function backgroundColor(locator: Locator): Promise<string> {
    return locator.evaluate(
        (element) => getComputedStyle(element).backgroundColor,
    );
}

function expectVisibleBackground(color: string): void {
    expect(color).not.toBe('transparent');
    expect(color).not.toBe('rgba(0, 0, 0, 0)');
}

test.describe('schedule content stock picker', () => {
    test('slash right after text inserts a highlighted mention on create and edit', async ({
        page,
    }, testInfo) => {
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

        const createMention = stockMention(editor);
        await expect(createMention).toHaveText(stockName);
        expectVisibleBackground(await backgroundColor(createMention));

        await editor.pressSequentially('1');

        const location = `E2E stock highlight ${testInfo.project.name}`;
        await page.getByLabel('現場名').fill(location);
        await page.getByRole('button', { name: '工事予定を作成' }).click();
        await page
            .getByRole('link', {
                name: `${location}の予定詳細を見る`,
            })
            .click();
        await page.getByRole('link', { name: '編集' }).first().click();

        const editEditor = page.locator(
            '[aria-labelledby="schedule-content-label"]',
        );
        const editMention = stockMention(editEditor);

        await expect(editMention).toHaveText(stockName);

        const lightBackground = await backgroundColor(editMention);
        expectVisibleBackground(lightBackground);

        await page.evaluate(() => {
            document.documentElement.classList.add('dark');
        });

        await expect
            .poll(() => backgroundColor(editMention))
            .not.toBe(lightBackground);
        expectVisibleBackground(await backgroundColor(editMention));
    });

    test('slashes in a URL scheme do not open the picker', async ({ page }) => {
        await login(page, editorLoginId);
        const editor = await openContentEditor(page);

        await editor.pressSequentially('https://');
        await page.waitForTimeout(300);

        await expect(stockListbox(page)).toBeHidden();
    });
});
