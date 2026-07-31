import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const password = 'password';
const adminLoginId = 'e2e-admin';

function escapeRegExp(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

async function login(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('ログインID').fill(adminLoginId);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: 'ログイン' }).click();
    await expect(page).toHaveURL(/\/schedule-overview(?:\?.*)?$/);
}

test.describe('admin stock management', () => {
    test('edits future purchases and memos and preserves them across term navigation', async ({
        page,
    }, testInfo) => {
        const stockName = `E2E在庫管理-${testInfo.project.name}`;
        const currentMemo = `${testInfo.project.name} 今月度引継ぎメモ`;
        const futureMemo = `${testInfo.project.name} 予定月度メモ`;

        await login(page);
        await page.goto('/admin/stocks/create');
        await page.getByLabel('在庫名').fill(stockName);
        await page.getByRole('button', { name: '在庫を追加' }).click();
        await expect(page).toHaveURL(/\/admin\/stocks(?:\?.*)?$/);

        const visibleTables = page.locator('table:visible');
        const selectedTable = visibleTables.nth(0);
        const nextTable = visibleTables.nth(1);

        await expect(visibleTables).toHaveCount(2);
        await expect(page.getByRole('link', { name: '翌月度' })).toBeVisible();
        await expect(
            page.getByText(
                '予定月度の仕入も、追加した時点で現在庫に反映されます。',
            ),
        ).toBeVisible();

        const additionInput = selectedTable.getByLabel(
            `${stockName} の追加仕入数`,
        );
        const editableRow = additionInput.locator('xpath=ancestor::tr');

        await editableRow
            .getByRole('button', {
                name: `${stockName} の仕入を訂正`,
            })
            .click();
        const correctionDialog = page.getByRole('dialog', {
            name: `${stockName} の仕入訂正`,
        });
        await expect(
            correctionDialog.getByText(
                '現在の仕入計は0です。入力した数量分がマイナスの仕入計として記録されます。',
            ),
        ).toBeVisible();
        await correctionDialog.getByLabel('差し引く数量').fill('3');
        await correctionDialog
            .getByRole('button', { name: '訂正を確定' })
            .click();
        await expect(correctionDialog).toBeHidden();
        await expect(editableRow).toContainText(/仕入計\s*-3/);

        await editableRow
            .getByRole('button', {
                name: `${stockName} の仕入を訂正`,
            })
            .click();
        await expect(
            correctionDialog.getByText(
                '現在の仕入計はすでにマイナスです。入力した数量分だけさらに減少します。',
            ),
        ).toBeVisible();
        await correctionDialog.getByLabel('差し引く数量').fill('2');
        await correctionDialog
            .getByRole('button', { name: '訂正を確定' })
            .click();
        await expect(correctionDialog).toBeHidden();
        await expect(editableRow).toContainText(/仕入計\s*-5/);

        await additionInput.fill('4');
        await additionInput.press('Tab');
        await page.waitForTimeout(300);

        await expect(additionInput).toHaveValue('4');
        await expect(editableRow).toContainText(/仕入計\s*-5/);

        await editableRow
            .getByRole('button', {
                name: `${stockName} に仕入を追加`,
            })
            .click();
        await expect(additionInput).toHaveValue('');
        await expect(editableRow).toContainText(/仕入計\s*-1/);

        await additionInput.fill('4');
        await editableRow
            .getByRole('button', {
                name: `${stockName} に仕入を追加`,
            })
            .click();
        await expect(additionInput).toHaveValue('');
        await expect(editableRow).toContainText(/仕入計\s*3/);

        const memoButtonName = new RegExp(
            `${escapeRegExp(stockName)} の.*メモを編集`,
        );

        await selectedTable
            .getByRole('button', { name: memoButtonName })
            .click();
        const currentMemoDialog = page.getByRole('dialog', {
            name: `${stockName} の月度メモ`,
        });
        await currentMemoDialog
            .getByRole('textbox', { name: /月度メモ$/ })
            .fill(currentMemo);
        await currentMemoDialog
            .getByRole('button', { name: 'メモを保存' })
            .click();
        await expect(currentMemoDialog.getByRole('status')).toHaveText(
            '保存しました',
        );
        await currentMemoDialog.getByRole('button', { name: '閉じる' }).click();
        await expect(currentMemoDialog).toBeHidden();

        const futureAdditionInput = nextTable.getByLabel(
            `${stockName} の追加仕入数`,
        );
        const futureRow = futureAdditionInput.locator('xpath=ancestor::tr');

        await futureRow
            .getByRole('button', {
                name: `${stockName} の仕入を訂正`,
            })
            .click();
        await expect(
            correctionDialog.getByText(
                '現在の仕入計は0です。入力した数量分がマイナスの仕入計として記録されます。',
            ),
        ).toBeVisible();
        await correctionDialog.getByLabel('差し引く数量').fill('3');
        await correctionDialog
            .getByRole('button', { name: '訂正を確定' })
            .click();
        await expect(correctionDialog).toBeHidden();
        await expect(futureRow).toContainText(/仕入計\s*-3/);

        await futureAdditionInput.fill('5');
        await futureRow
            .getByRole('button', {
                name: `${stockName} に仕入を追加`,
            })
            .click();
        await expect(futureAdditionInput).toHaveValue('');
        await expect(futureRow).toContainText(/仕入計\s*2/);

        await nextTable.getByRole('button', { name: memoButtonName }).click();
        const futureMemoDialog = page.getByRole('dialog', {
            name: `${stockName} の月度メモ`,
        });
        await expect(futureMemoDialog.getByText('参照のみ')).toBeVisible();
        await expect(futureMemoDialog.getByText(currentMemo)).toBeVisible();
        await expect(
            futureMemoDialog.getByRole('textbox', { name: /月度メモ$/ }),
        ).toHaveValue('');
        await futureMemoDialog
            .getByRole('textbox', { name: /月度メモ$/ })
            .fill(futureMemo);
        await futureMemoDialog
            .getByRole('button', { name: 'メモを保存' })
            .click();
        await expect(futureMemoDialog.getByRole('status')).toHaveText(
            '保存しました',
        );
        await futureMemoDialog.getByRole('button', { name: '閉じる' }).click();
        await expect(futureMemoDialog).toBeHidden();
        await expect(futureRow).toContainText(futureMemo);

        await page.getByRole('link', { name: '翌月度' }).click();
        await expect(
            page.getByRole('link', { name: '今月度へ戻る' }),
        ).toBeVisible();

        const selectedFutureTable = page.locator('table:visible').nth(0);
        const selectedFutureRow = selectedFutureTable
            .getByLabel(`${stockName} の追加仕入数`)
            .locator('xpath=ancestor::tr');

        await expect(selectedFutureRow).toContainText(/仕入計\s*2/);
        await expect(selectedFutureRow).toContainText(futureMemo);

        const selectedFutureUrl = page.url();

        await page.getByRole('link', { name: '翌月度' }).click();
        await expect(page).not.toHaveURL(selectedFutureUrl);
        await expect(page.getByText('予定月度').first()).toBeVisible();

        await page.getByRole('link', { name: '前月度' }).click();
        await expect(page).toHaveURL(selectedFutureUrl);
        await expect(selectedFutureRow).toContainText(/仕入計\s*2/);
        await expect(selectedFutureRow).toContainText(futureMemo);

        await page.getByRole('link', { name: '今月度へ戻る' }).click();
        await expect(
            page.getByRole('link', { name: '今月度へ戻る' }),
        ).toBeHidden();

        const persistedNextRow = page
            .locator('table:visible')
            .nth(1)
            .getByLabel(`${stockName} の追加仕入数`)
            .locator('xpath=ancestor::tr');

        await expect(persistedNextRow).toContainText(/仕入計\s*2/);
        await expect(persistedNextRow).toContainText(futureMemo);
    });
});
