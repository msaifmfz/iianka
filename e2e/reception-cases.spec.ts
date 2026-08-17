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

test.describe('reception cases', () => {
    test('uploading an attachment first creates a draft and submits with the case', async ({
        page,
    }) => {
        await login(page, editorLoginId);

        await page.goto('/reception/cases/create');

        const fileChooserPromise = page.waitForEvent('filechooser');
        await page.getByRole('button', { name: /ファイルを追加/ }).click();
        const fileChooser = await fileChooserPromise;

        await fileChooser.setFiles({
            name: 'e2e-attachment.pdf',
            mimeType: 'application/pdf',
            buffer: Buffer.from('%PDF-1.4\n% E2E attachment\n'),
        });

        await expect(page.getByText('e2e-attachment')).toBeVisible();
        await expect(page.getByText('未発行')).toHaveCount(0);

        await page.getByLabel('会社名').fill('E2E 添付会社');
        await page.getByLabel('現場名').fill('E2E 添付現場');
        await page.getByLabel('案件書類').selectOption({ label: '見積依頼' });
        await page.getByLabel('受付内容').fill('添付から始めた受付');
        await page.getByLabel('期限').fill('2026-07-10');
        await page.getByRole('button', { name: '受付完了' }).click();

        await expect(
            page
                .getByRole('status')
                .filter({ hasText: '受付を完了しました。' }),
        ).toBeVisible();
        await expect(page.getByText('e2e-attachment')).toHaveCount(0);
    });

    test('submitting the intake form opens a blank create form', async ({
        page,
    }) => {
        await login(page, editorLoginId);

        await page.goto('/reception/cases/create');
        await page.getByLabel('会社名').fill('E2E 受付会社');
        await page.getByLabel('現場名').fill('E2E 受付現場');
        await page.getByLabel('案件書類').selectOption({ label: '見積依頼' });
        await page.getByLabel('優先度').selectOption({ label: '高' });
        await page.getByLabel('受付内容').fill('E2E 受付内容');
        await page.getByLabel('期限').fill('2026-07-10');

        const submitButton = page.getByRole('button', { name: '受付完了' });

        await expect(submitButton).toBeEnabled();
        await submitButton.click();

        await expect(
            page
                .getByRole('status')
                .filter({ hasText: '受付を完了しました。' }),
        ).toBeVisible();
        await expect(page.getByLabel('会社名')).toHaveValue('');
        await expect(page.getByLabel('現場名')).toHaveValue('');
        await expect(page.getByLabel('案件書類')).toHaveValue('');
        await expect(page.getByLabel('優先度')).toHaveValue('normal');
        await expect(page.getByLabel('受付内容')).toHaveValue('');
        await expect(page.getByLabel('期限')).toHaveValue('');
        await expect(page.getByText('未発行')).toBeVisible();
    });

    test('review assignment is only saved from the explicit button', async ({
        page,
    }) => {
        const companyName = `E2E Review Assignment ${Date.now()}`;

        await login(page, editorLoginId);

        await page.goto('/reception/cases/create');
        await page.getByLabel('会社名').fill(companyName);
        await page.getByLabel('現場名').fill('E2E Review 現場');
        await page.getByLabel('案件書類').selectOption({ label: '見積依頼' });
        await page.getByLabel('受付内容').fill('E2E review assignment flow');
        await page.getByLabel('期限').fill('2026-07-10');
        await page.getByRole('button', { name: '受付完了' }).click();
        await expect(
            page
                .getByRole('status')
                .filter({ hasText: '受付を完了しました。' }),
        ).toBeVisible();

        await page.goto('/reception/cases');

        const caseItem = page
            .locator('[data-reception-case-item="true"]')
            .filter({ hasText: companyName })
            .first();

        await expect(caseItem).toBeVisible();
        await expect(caseItem).toContainText('担当者未設定');
        await caseItem.getByLabel(/優先度/).selectOption({ label: '高' });
        await expect(
            page
                .getByRole('status')
                .filter({ hasText: '優先度を更新しました。' }),
        ).toBeVisible();
        await expect(caseItem).toContainText('高');
        await expect(
            caseItem.getByRole('button', { name: '対応開始' }),
        ).toBeDisabled();

        await caseItem
            .getByLabel('担当者')
            .selectOption({ label: 'E2E Timeline Worker' });
        await expect(caseItem).toContainText('担当者未設定');

        await page.reload();
        await expect(caseItem).toContainText('担当者未設定');

        await caseItem
            .getByLabel('担当者')
            .selectOption({ label: 'E2E Timeline Worker' });
        await caseItem
            .getByRole('button', { name: '担当者を設定する' })
            .click();

        await expect(
            page
                .getByRole('status')
                .filter({ hasText: '担当者を設定しました。' }),
        ).toBeVisible();
        await expect(caseItem).toContainText('E2E Timeline Worker');

        await caseItem.getByRole('button', { name: '対応開始' }).click();

        await expect(
            page
                .getByRole('status')
                .filter({ hasText: '対応を開始しました。' }),
        ).toBeVisible();
        await expect(caseItem).toContainText('対応中');

        await caseItem.getByRole('link', { name: '開く' }).click();
        await expect(page).toHaveURL(/\/reception\/cases\/\d+$/);
        await expect(page.getByText('活動履歴')).toBeVisible();
        await expect(page.getByText('下書き作成')).toHaveCount(0);
        await expect(
            page.getByText('担当: 未設定 → E2E Timeline Worker', {
                exact: true,
            }),
        ).toBeVisible();
        await expect(
            page.getByText('担当: E2E Timeline Worker → E2E Timeline Worker'),
        ).toHaveCount(0);
    });

    test('creates construction and business schedules from reception with review and quick detail', async ({
        page,
    }) => {
        test.setTimeout(60_000);

        const uniqueSuffix = Date.now();
        const companyName = `E2E 予定作成会社 ${uniqueSuffix}`;
        const siteName = `E2E 予定作成現場 ${uniqueSuffix}`;
        const receptionContent = `E2E 受付内容 ${uniqueSuffix}`;
        const scheduledOn = '2026-08-20';

        await login(page, editorLoginId);

        await page.goto('/reception/cases/create');
        await page.getByLabel('会社名').fill(companyName);
        await page.getByLabel('現場名').fill(siteName);
        await page.getByLabel('案件書類').selectOption({ label: '見積依頼' });
        await page.getByLabel('受付内容').fill(receptionContent);
        await page.getByLabel('期限').fill('2026-08-18');
        await page.getByLabel('予定日').fill(scheduledOn);
        await page.getByRole('button', { name: '受付完了' }).click();

        await expect(
            page
                .getByRole('status')
                .filter({ hasText: '受付を完了しました。' }),
        ).toBeVisible();

        await page.goto('/reception/cases');

        const caseItem = page
            .locator('[data-reception-case-item="true"]')
            .filter({ hasText: companyName })
            .first();

        await caseItem.getByRole('link', { name: '開く' }).click();
        await expect(page).toHaveURL(/\/reception\/cases\/\d+$/);
        await expect(page.getByText('関連予定')).toBeVisible();
        await expect(
            page.getByText('この受付から作成した予定はまだありません。'),
        ).toBeVisible();

        await page.getByRole('link', { name: '工事予定を作成' }).click();
        await expect(page).toHaveURL(
            /\/construction-schedules\/create\?.*reception_case_id=/,
        );
        await expect(page.getByText('受付から作成')).toBeVisible();
        await expect(page.getByLabel('日付')).toHaveValue(scheduledOn);
        await expect(page.getByLabel('現場名')).toHaveValue(siteName);
        await expect(page.getByLabel('ゼネコン会社')).toHaveValue(companyName);
        await expect(page.getByLabel('内容（任意）')).toHaveText(
            receptionContent,
        );

        await page.getByRole('button', { name: '工事予定を作成' }).click();
        await expect(page).toHaveURL(/\/reception\/cases\/\d+$/);
        await expect(
            page
                .getByRole('status')
                .filter({ hasText: '工事予定を作成しました。' }),
        ).toBeVisible();

        const constructionRow = page
            .locator('[data-reception-linked-schedule="true"]')
            .filter({ hasText: siteName });

        await expect(constructionRow).toBeVisible();
        await constructionRow
            .getByRole('button', { name: /工事.*をすぐ確認/ })
            .click();
        await expect(page.getByRole('dialog')).toContainText(siteName);
        await expect(page.getByRole('dialog')).toContainText(receptionContent);
        await page.getByRole('button', { name: 'Close' }).click();

        const constructionLink = constructionRow.getByRole('link', {
            name: /工事.*の詳細を開く/,
        });
        await constructionLink.dispatchEvent('pointerdown', {
            button: 0,
            clientX: 10,
            clientY: 10,
        });
        await page.waitForTimeout(550);
        await expect(page.getByRole('dialog')).toContainText(siteName);
        await page.getByRole('button', { name: 'Close' }).click();

        await page.getByRole('link', { name: '業務予定を作成' }).click();
        await expect(page).toHaveURL(
            /\/business-schedules\/create\?.*reception_case_id=/,
        );
        await expect(page.getByText('受付から作成')).toBeVisible();
        await expect(page.getByLabel('日付')).toHaveValue(scheduledOn);
        await expect(page.getByLabel('場所')).toHaveValue(siteName);
        await expect(page.getByLabel('ゼネコン会社')).toHaveValue(companyName);
        await expect(page.getByRole('combobox', { name: '内容' })).toHaveValue(
            '見積依頼',
        );
        await expect(
            page.getByRole('textbox', { name: 'メモ', exact: true }),
        ).toHaveValue(receptionContent);

        await page.getByRole('button', { name: '業務予定を作成' }).click();
        await expect(page).toHaveURL(/\/reception\/cases\/\d+$/);
        await expect(
            page
                .getByRole('status')
                .filter({ hasText: '業務予定を作成しました。' }),
        ).toBeVisible();
        await expect(
            page.locator('[data-reception-linked-schedule="true"]'),
        ).toHaveCount(2);
        await expect(page.getByText('予定作成', { exact: true })).toHaveCount(
            2,
        );
    });

    test('archive keeps the same width when result list is empty on narrow screens', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 430, height: 900 });
        await login(page, editorLoginId);

        const archivePage = page.locator(
            '[data-reception-archive-page="true"]',
        );

        await page.goto(
            '/reception/archive?keyword=E2E%20Archive%20Width%20Company',
        );
        await expect(archivePage).toBeVisible();
        const populatedWidth = await archivePage.evaluate(
            (element) => element.getBoundingClientRect().width,
        );

        await page.goto('/reception/archive?keyword=no-archive-width-results');
        await expect(
            page.getByText('完了した受付案件はありません。'),
        ).toBeVisible();
        const emptyWidth = await archivePage.evaluate(
            (element) => element.getBoundingClientRect().width,
        );

        expect(emptyWidth).toBe(populatedWidth);
    });
});
