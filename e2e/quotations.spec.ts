import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

async function login(page: Page) {
    await page.goto('/login');
    await page.getByLabel('ログインID').fill('e2e-editor');
    await page.locator('input[name="password"]').fill('password');
    await page.getByRole('button', { name: 'ログイン', exact: true }).click();
    await expect(page).toHaveURL(/schedule-overview/);
    await page.goto('/quotations');
    await page.getByRole('link', { name: '見積書を作成' }).click();
}

async function selectItem(page: Page, code: string) {
    await page.getByRole('button', { name: '品目を選択', exact: true }).click();
    await page.getByRole('textbox', { name: '品目を検索' }).fill(code);
    await page
        .getByRole('dialog')
        .getByRole('button')
        .filter({ hasText: code })
        .click();
}

test('creates a quotation with item-aware inputs and downloads both Japanese PDFs', async ({
    page,
}, testInfo) => {
    await login(page);
    await expect(
        page.getByLabel('真空ポンプ使用', { exact: true }),
    ).toBeDisabled();
    await expect(
        page.getByRole('button', { name: 'PDFを確認', exact: true }),
    ).toBeDisabled();
    await page.getByLabel(/^得意先/).fill('株式会社 山田建設');
    await page.getByLabel(/^工事名/).fill('栗東倉庫 アンカー取付工事');
    await selectItem(page, '0001');
    await page.getByLabel(/^数量（本）/).fill('100');
    await page.getByLabel('施工寸法（mm）', { exact: true }).fill('300');
    await page.getByLabel('デッキ貫通', { exact: true }).check();
    await expect(
        page.locator('aside').getByText('¥15,000', { exact: true }).first(),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: '見積を発行' }),
    ).toBeEnabled();
    await page.screenshot({
        path: testInfo.outputPath('quotation-editor-desktop.png'),
        fullPage: true,
    });
    await page.getByRole('button', { name: 'PDFを確認', exact: true }).click();
    const dialog = page.getByRole('dialog');
    await expect(
        dialog.getByRole('img', { name: '見積書 1ページ', exact: true }),
    ).toHaveAttribute('data-rendered', 'true');
    await expect(dialog.getByText(/株式会社 山田建設 御中/)).toBeAttached();
    const canvas = dialog.getByRole('img', {
        name: '見積書 1ページ',
        exact: true,
    });
    expect(
        await canvas.evaluate((element: HTMLCanvasElement) => {
            const pixels = element
                .getContext('2d')!
                .getImageData(0, 0, element.width, element.height).data;

            for (let i = 0; i < pixels.length; i += 4) {
                if (
                    pixels[i + 3] > 0 &&
                    pixels[i] < 180 &&
                    pixels[i + 1] < 180 &&
                    pixels[i + 2] < 180
                ) {
                    return true;
                }
            }

            return false;
        }),
    ).toBe(true);
    await page.screenshot({
        path: testInfo.outputPath('quotation-pdf-preview.png'),
    });
    const widthBefore = await canvas.evaluate(
        (element: HTMLCanvasElement) => element.width,
    );
    await dialog.getByRole('button', { name: '拡大', exact: true }).click();
    await expect
        .poll(() =>
            canvas.evaluate((element: HTMLCanvasElement) => element.width),
        )
        .toBeGreaterThan(widthBefore);
    await dialog
        .getByRole('button', { name: '幅に合わせる', exact: true })
        .click();
    await expect
        .poll(() =>
            canvas.evaluate((element: HTMLCanvasElement) => element.width),
        )
        .toBe(widthBefore);
    await dialog.getByRole('button', { name: '内訳書', exact: true }).click();
    await expect(
        dialog.getByRole('img', { name: '内訳書 1ページ', exact: true }),
    ).toHaveAttribute('data-rendered', 'true');
    await expect(page).toHaveURL(/\/quotations\/create$/);
    await page.keyboard.press('Escape');
    await expect(page.getByLabel(/^数量（本）/)).toHaveValue('100');
    await page.getByRole('button', { name: '見積を発行' }).click();
    await expect(page).toHaveURL(/\/quotations\/\d+$/);
    await expect(page.getByText('D10（有効長=300L）')).toBeVisible();

    for (const [label, file] of [
        ['見積書PDF', 'quotation.pdf'],
        ['内訳書PDF', 'breakdown.pdf'],
    ]) {
        const downloadPromise = page.waitForEvent('download');
        await page.getByRole('link', { name: label, exact: true }).click();
        const download = await downloadPromise;
        await download.saveAs(testInfo.outputPath(file));
        expect(await download.failure()).toBeNull();
    }

    const detailUrl = page.url();
    await page.getByRole('link', { name: '一覧へ', exact: true }).click();
    const card = page
        .locator('article')
        .filter({ hasText: '株式会社 山田建設' });

    for (const label of ['見積書PDF', '内訳書PDF']) {
        const downloadPromise = page.waitForEvent('download');
        await card.getByRole('link', { name: label, exact: true }).click();
        expect(await (await downloadPromise).failure()).toBeNull();
        await expect(page).toHaveURL(/\/quotations$/);
    }

    await page.goto(detailUrl);
    await page.getByRole('link', { name: '編集', exact: true }).click();
    await expect(
        page.getByLabel('施工寸法（mm）', { exact: true }),
    ).toHaveValue('300');
    await expect(page.getByLabel('デッキ貫通', { exact: true })).toBeChecked();
});

test('rounds line amounts and expenses to whole yen while preserving rates through preview and issuance', async ({
    page,
}, testInfo) => {
    await login(page);
    await page.getByLabel(/^得意先/).fill('端数確認株式会社');
    await page.getByLabel(/^工事名/).fill('円単位の見積確認');
    await page.getByLabel(/^入力方法/).selectOption('free');
    await page.getByLabel(/^品名/).fill('端数材料');
    await page.getByLabel(/^単価（税別・円）/).fill('123.45');
    await page.getByLabel(/^数量/).fill('2.5');
    const sidebar = page.locator('aside');
    const roundingNote = '明細金額・諸経費は1円未満を四捨五入しています。';

    await expect(
        sidebar.getByText('¥309', { exact: true }).first(),
    ).toBeVisible();
    await expect(page.getByLabel(/^単価（税別・円）/)).toHaveValue('123.45');
    await expect(
        sidebar.getByText(roundingNote, { exact: true }),
    ).toBeVisible();
    await page.getByLabel('交通費・諸経費（円）', { exact: true }).fill('12.5');
    await expect(
        sidebar.getByText('¥322', { exact: true }).first(),
    ).toBeVisible();
    await expect(sidebar.getByText('¥13', { exact: true })).toBeVisible();
    await page.screenshot({
        path: testInfo.outputPath('quotation-yen-rounding-editor.png'),
        fullPage: true,
    });
    await page.getByRole('button', { name: 'PDFを確認', exact: true }).click();
    const dialog = page.getByRole('dialog');
    await expect(
        dialog.getByRole('img', { name: '見積書 1ページ', exact: true }),
    ).toHaveAttribute('data-rendered', 'true');
    await expect(dialog.getByText(/￥322/).first()).toBeAttached();
    await expect(dialog.getByText(/123\.45/)).toBeAttached();
    await expect(dialog.getByText(roundingNote)).toBeAttached();
    await page.screenshot({
        path: testInfo.outputPath('quotation-yen-rounding-pdf.png'),
    });
    await page.keyboard.press('Escape');
    await page.getByRole('button', { name: '見積を発行', exact: true }).click();
    await expect(page).toHaveURL(/\/quotations\/\d+$/);
    await expect(
        page
            .locator('section')
            .filter({ hasText: '見積合計（税別）' })
            .getByText('¥322', { exact: true }),
    ).toBeVisible();
    await expect(page.getByText(roundingNote, { exact: true })).toBeVisible();
    const material = page.getByRole('row').filter({ hasText: '端数材料' });
    await expect(material.getByText('¥123.45', { exact: true })).toBeVisible();
    await expect(material.getByText('¥309', { exact: true })).toBeVisible();
});

test('bundled and unrelated conditions are disabled and reset when changing input mode on mobile', async ({
    page,
}, testInfo) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);
    await selectItem(page, '0025');
    await expect(page.getByLabel('デッキ貫通', { exact: true })).toBeDisabled();
    await expect(page.getByLabel('高所作業', { exact: true })).toBeDisabled();
    await expect(
        page.getByLabel('施工寸法（mm）', { exact: true }),
    ).toBeDisabled();
    await page.getByLabel('上向き施工', { exact: true }).check();
    await expect(
        page.locator('aside').getByText('¥290', { exact: true }).first(),
    ).toBeVisible();
    await page.screenshot({
        path: testInfo.outputPath('quotation-editor-mobile.png'),
        fullPage: true,
    });
    await page.getByLabel(/^入力方法/).selectOption('free');
    await expect(page.getByLabel('上向き施工', { exact: true })).toBeDisabled();
    await page.getByLabel(/^品名/).fill('納品材料');
    await page.getByLabel(/^単価（税別・円）/).fill('123.45');
    await expect(
        page.locator('aside').getByText('¥123', { exact: true }).first(),
    ).toBeVisible();
    await expect(
        page.getByLabel('少量施工を適用する', { exact: false }),
    ).toBeDisabled();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
});

test('hover prefetch is silent and only clicking navigation prompts about unsaved input', async ({
    page,
}) => {
    await login(page);
    await page.getByLabel(/^得意先/).fill('変更あり');
    const messages: string[] = [];
    page.on('dialog', async (dialog) => {
        messages.push(dialog.message());
        await dialog.dismiss();
    });
    const link = page.getByRole('link', { name: '予定検索', exact: true });
    const href = await link.getAttribute('href');
    const prefetch = page.waitForResponse(
        (response) =>
            response.url().endsWith(href!) &&
            response.request().headers()['purpose'] === 'prefetch',
    );
    await link.hover();
    await prefetch;
    expect(messages).toEqual([]);
    await link.click();
    await expect.poll(() => messages.length).toBe(1);
    expect(messages[0]).toContain('未保存の変更');
    await expect(page).toHaveURL(/\/quotations\/create$/);
    await expect(page.getByLabel(/^得意先/)).toHaveValue('変更あり');
});

test('recalculation keeps totals and line details in place while blocking stale PDF previews', async ({
    page,
}) => {
    await login(page);
    await page.getByLabel(/^得意先/).fill('表示確認株式会社');
    await page.getByLabel(/^工事名/).fill('更新中の表示');
    await selectItem(page, '0001');
    await page.getByLabel(/^数量（本）/).fill('100');
    const sidebar = page.locator('aside');
    await expect(
        sidebar.getByText('¥12,000', { exact: true }).first(),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'PDFを確認', exact: true }),
    ).toBeEnabled();
    const before = await page.evaluate(() => ({
        height: document.documentElement.scrollHeight,
        panelHeight: document.querySelector('aside')!.getBoundingClientRect()
            .height,
    }));
    let release!: () => void;
    const paused = new Promise<void>((resolve) => {
        release = resolve;
    });
    await page.route('**/quotations/preview', async (route) => {
        await paused;
        await route.continue();
    });
    await page.getByLabel(/^数量（本）/).fill('110');
    await expect(sidebar.getByText('計算中…', { exact: true })).toBeVisible();
    await expect(
        sidebar.getByText('¥12,000', { exact: true }).first(),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'PDFを確認', exact: true }),
    ).toBeDisabled();
    const during = await page.evaluate(() => ({
        height: document.documentElement.scrollHeight,
        panelHeight: document.querySelector('aside')!.getBoundingClientRect()
            .height,
    }));
    expect(during).toEqual(before);
    release();
    await expect(
        sidebar.getByText('¥13,200', { exact: true }).first(),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'PDFを確認', exact: true }),
    ).toBeEnabled();
});

test('a mobile multipage breakdown renders every page without issuing the quotation', async ({
    page,
}, testInfo) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);
    await page.getByLabel(/^得意先/).fill('ページ確認株式会社');
    await page.getByLabel(/^工事名/).fill('複数ページ確認');

    for (let i = 0; i < 13; i++) {
        if (i > 0) {
            await page
                .getByRole('button', { name: '項目を追加', exact: true })
                .first()
                .click();
        }

        await selectItem(page, '0001');
    }

    await page.getByRole('button', { name: 'PDFを確認', exact: true }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByRole('button', { name: '内訳書', exact: true }).click();
    await expect(
        dialog.getByRole('img', { name: '内訳書 1ページ', exact: true }),
    ).toHaveAttribute('data-rendered', 'true');
    await expect(
        dialog.getByText('1 / 2 ページ', { exact: true }),
    ).toBeVisible();
    await dialog
        .getByRole('button', { name: '次のページ', exact: true })
        .click();
    await expect(
        dialog.getByRole('img', { name: '内訳書 2ページ', exact: true }),
    ).toHaveAttribute('data-rendered', 'true');
    await expect(
        dialog.getByRole('button', { name: '次のページ', exact: true }),
    ).toBeDisabled();
    await expect(dialog.getByText(/合計（税別・法定福利費含）/)).toBeAttached();
    await page.screenshot({
        path: testInfo.outputPath('quotation-pdf-preview-mobile.png'),
    });
    expect(
        await dialog.evaluate(
            (element) =>
                element.getBoundingClientRect().right <= window.innerWidth,
        ),
    ).toBe(true);
    await dialog
        .getByRole('button', { name: '前のページ', exact: true })
        .click();
    await expect(
        dialog.getByRole('img', { name: '内訳書 1ページ', exact: true }),
    ).toHaveAttribute('data-rendered', 'true');
    await page.keyboard.press('Escape');
    await expect(page).toHaveURL(/\/quotations\/create$/);
});
