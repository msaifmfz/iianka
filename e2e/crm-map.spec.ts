import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const password = 'password';
const seededClient = 'E2E 西日本商事';
const seededOffice = 'E2E 本社';

test.use({
    launchOptions: {
        args: [
            '--use-fake-device-for-media-stream',
            '--use-fake-ui-for-media-stream',
        ],
    },
    permissions: ['microphone'],
});

async function login(page: Page, userLoginId: string): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('ログインID').fill(userLoginId);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: 'ログイン' }).click();
    await expect(page).toHaveURL(/\/schedule-overview(?:\?.*)?$/);
}

/**
 * Map tiles come from the GSI and OSM servers; the specs never need them,
 * so they are blocked to keep runs offline and fast.
 */
async function blockMapTiles(page: Page): Promise<void> {
    await page.route(
        /cyberjapandata\.gsi\.go\.jp|tile\.openstreetmap\.org/,
        (route) => route.abort(),
    );
}

async function addLog(page: Page, summary: string): Promise<void> {
    const panel = page.getByRole('complementary', { name: '地点の記録' });

    await panel
        .getByRole('button', { name: '記録を追加', exact: true })
        .click();
    await panel.getByRole('button', { name: '好感触', exact: true }).click();
    await panel.getByLabel('内容').fill(summary);
    await panel.getByRole('button', { name: '記録する', exact: true }).click();

    await expect(panel.getByText(summary)).toBeVisible();
    await expect(
        panel.getByRole('button', { name: '記録を追加', exact: true }),
    ).toBeVisible();
}

async function openSeededOffice(page: Page): Promise<void> {
    // Other specs add places, so mobile map pins may legitimately be clustered.
    await page.goto('/crm/clients');
    await page.getByRole('link').filter({ hasText: seededClient }).click();
    await page.getByRole('link').filter({ hasText: seededOffice }).click();
    await expect(
        page.getByRole('complementary', { name: '地点の記録' }),
    ).toBeVisible();
}

test.describe('CRM map', () => {
    test.beforeEach(async ({ page }) => {
        await blockMapTiles(page);
    });

    test.afterEach(async ({ page }) => {
        const errors = await page.pageErrors();
        expect(errors.map((error) => error.message)).toEqual([]);
    });

    test('an admin adds a client, drops a place on the map and logs a visit', async ({
        page,
    }) => {
        const clientName = `E2E 神戸物産 ${Date.now()}`;

        await login(page, 'e2e-admin');
        await page.goto('/crm/clients/create');

        // The seeded client holds red, so the form suggests another color.
        // (Which one depends on clients other browsers create in parallel.)
        await expect(
            page.getByRole('button', { name: '#dc2626（使用中）' }),
        ).toHaveAttribute('aria-pressed', 'false');
        await expect(
            page.getByRole('button', { pressed: true, name: /^#/ }),
        ).toHaveCount(1);

        await page.getByLabel('顧客名').fill(clientName);
        await page.getByLabel(/略称/).fill('神');
        await page.getByLabel('その他の色').fill('#ffffff');
        await page
            .getByRole('button', { name: '顧客を追加', exact: true })
            .click();
        await expect(page).toHaveURL(/\/crm\/clients\/\d+$/);

        // Long-press (right-click on desktop) the map to add a place there.
        await page.goto('/crm/map');
        await page
            .locator('.leaflet-container')
            .click({ button: 'right', position: { x: 500, y: 300 } });
        await page
            .getByRole('dialog', { name: 'ここに地点を追加' })
            .getByRole('link', { name: clientName })
            .click();

        await expect(page).toHaveURL(/\/places\/create\?lat=.*&lng=/);
        await page.getByRole('button', { name: '事務所', exact: true }).click();
        await page.getByLabel('地点名').fill('E2E 三宮事務所');
        await expect(page.getByText(/^\d+\.\d{6}, \d+\.\d{6}$/)).toBeVisible();
        await page
            .getByRole('button', { name: '地点を追加', exact: true })
            .click();

        await expect(page).toHaveURL(/\/crm\/map\?place=\d+/);
        const panel = page.getByRole('complementary', { name: '地点の記録' });
        await expect(panel.getByText('E2E 三宮事務所').first()).toBeVisible();
        await expect(
            page.locator('.crm-pin--selected .crm-pin__label'),
        ).toHaveCSS('color', 'rgb(0, 0, 0)');
        await expect(
            panel.getByText(
                'まだ記録がありません。最初の記録を追加しましょう。',
            ),
        ).toBeVisible();

        await addLog(page, 'E2E 新規挨拶。来週再訪問の約束。');
        await expect(panel.getByText('好感触')).toBeVisible();
    });

    test('a viewer taps a pin, reads its history and logs a visit on a phone', async ({
        page,
    }, testInfo) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page, 'e2e-login');
        await page.goto('/crm/map');

        await page.getByTitle(`${seededClient} ${seededOffice}`).click();

        await expect(page).toHaveURL(/\/crm\/map\?place=\d+/);
        const panel = page.getByRole('complementary', { name: '地点の記録' });
        await expect(panel.getByText(seededOffice).first()).toBeVisible();
        await expect(
            panel.getByText('E2E 初回訪問。見積もりを依頼された。'),
        ).toBeVisible();
        await expect(panel.getByText('山田 太郎')).toBeVisible();
        await page.screenshot({
            path: testInfo.outputPath('phone-history.png'),
            animations: 'disabled',
        });

        // Viewers log history but do not manage places.
        await expect(
            panel.getByRole('link', { name: '地点を編集' }),
        ).toHaveCount(0);

        await addLog(page, 'E2E 閲覧者の訪問記録');

        // The deep link keeps the panel open across a reload.
        await page.reload();
        await expect(panel.getByText('E2E 閲覧者の訪問記録')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(panel).toHaveCount(0);
    });

    test('focusing a client greys out everyone else', async ({ page }) => {
        await login(page, 'e2e-admin');
        await page.goto('/crm/map');

        await expect(
            page.locator('[title="E2E 東大阪工業 E2E 東大阪事務所"] .crm-pin'),
        ).toHaveCSS('opacity', '1');

        await page.getByPlaceholder('顧客を探す').fill('西日本');
        // Exact: map pins now carry "<client> <place>" as their accessible
        // name, so a loose match would also hit the markers behind the search.
        await page
            .getByRole('button', { name: seededClient, exact: true })
            .first()
            .click();

        await expect(
            page.getByRole('button', { name: '絞り込みを解除', exact: true }),
        ).toBeVisible();
        await expect(
            page.locator(`[title="${seededClient} ${seededOffice}"] .crm-pin`),
        ).not.toHaveClass(/crm-pin--dimmed/);
        await expect(
            page.locator('[title="E2E 東大阪工業 E2E 東大阪事務所"] .crm-pin'),
        ).toHaveClass(/crm-pin--dimmed/);
    });

    test('archive visibility stays in sync when toggled on and off', async ({
        page,
    }, testInfo) => {
        await login(page, 'e2e-admin');
        await page.goto('/crm/map');
        const toggle = page.getByRole('checkbox', { name: 'アーカイブも表示' });
        await expect(
            page.locator('.leaflet-marker-icon').first(),
        ).toBeVisible();
        const layersToggle = page.getByRole('button', { name: 'Layers' });
        const iconLoaded = await layersToggle.evaluate(async (element) => {
            const background = getComputedStyle(element).backgroundImage;
            const url = background.match(/^url\(["']?(.*?)["']?\)$/)?.[1];

            if (!url) {
                return false;
            }

            const image = new Image();
            image.src = url;

            return image.decode().then(
                () => image.naturalWidth > 0,
                () => false,
            );
        });
        expect(iconLoaded).toBe(true);
        await expect(
            page.locator('.leaflet-zoom-anim, .leaflet-cluster-anim'),
        ).toHaveCount(0);
        await expect
            .poll(() =>
                page.locator('.leaflet-marker-icon').evaluateAll((markers) =>
                    markers.every((marker) => {
                        const bounds = marker.getBoundingClientRect();

                        return marker.contains(
                            document.elementFromPoint(
                                bounds.x + bounds.width / 2,
                                bounds.y + bounds.height / 2,
                            ),
                        );
                    }),
                ),
            )
            .toBe(true);
        await page.screenshot({
            path: testInfo.outputPath('desktop-map.png'),
            animations: 'disabled',
        });
        await toggle.click();
        await expect(toggle).toBeChecked();
        await expect(page).toHaveURL(/archived=1/);
        await toggle.click();
        await expect(toggle).not.toBeChecked();
        await expect(page).not.toHaveURL(/archived=1/);
    });

    test('photo selection respects the limit before upload and can be cancelled', async ({
        page,
    }, testInfo) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page, 'e2e-admin');
        await page.goto('/crm/map');
        await page.getByTitle(`${seededClient} ${seededOffice}`).click();
        const panel = page.getByRole('complementary', { name: '地点の記録' });
        await panel
            .getByRole('button', { name: '記録を追加', exact: true })
            .click();
        await expect(
            panel.getByRole('button', { name: '縮小' }),
        ).toHaveAttribute('aria-expanded', 'true');
        const image = Buffer.from(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aBz0AAAAASUVORK5CYII=',
            'base64',
        );
        await panel.locator('input[type="file"]').setInputFiles(
            Array.from({ length: 11 }, (_, index) => ({
                name: `photo-${index}.png`,
                mimeType: 'image/png',
                buffer: image,
            })),
        );
        await page.screenshot({
            path: testInfo.outputPath('phone-log-form.png'),
            animations: 'disabled',
        });
        await expect(
            panel.getByRole('button', { name: '添付を外す' }),
        ).toHaveCount(10);
        await expect(panel.getByText('添付は10件までです。')).toBeVisible();
        await panel
            .getByRole('button', { name: 'キャンセル', exact: true })
            .click();
        await expect(
            panel.getByRole('button', { name: '記録を追加', exact: true }),
        ).toBeVisible();
    });

    test('the add-place button supports address search and clears outdated candidates', async ({
        page,
    }, testInfo) => {
        await login(page, 'e2e-admin');
        await page.goto('/crm/map');
        await expect(
            page.locator('.leaflet-marker-icon').first(),
        ).toBeVisible();
        await page
            .getByRole('button', { name: '地点を追加', exact: true })
            .click();
        const dialog = page.getByRole('dialog', { name: 'ここに地点を追加' });
        await dialog.getByLabel('地点を追加する顧客を探す').fill('西日本');
        await dialog.getByRole('link', { name: seededClient }).click();
        await page.getByLabel('地点名').fill(`E2E 住所検索 ${testInfo.testId}`);
        await page.route('**/crm/geocode?*', async (route) => {
            const address = new URL(route.request().url()).searchParams.get(
                'address',
            );
            await route.fulfill({
                json: {
                    candidates:
                        address === '大阪'
                            ? [
                                  { label: '大阪候補A', lat: 34.7, lng: 135.5 },
                                  { label: '大阪候補B', lat: 34.8, lng: 135.6 },
                              ]
                            : [{ label: '京都', lat: 35.0116, lng: 135.7681 }],
                },
            });
        });
        await page.getByLabel('住所', { exact: true }).fill('大阪');
        await page.getByRole('button', { name: '検索', exact: true }).click();
        await expect(
            page.getByRole('button', { name: '大阪候補A' }),
        ).toBeVisible();
        await page.getByLabel('住所', { exact: true }).fill('京都');
        await expect(
            page.getByRole('button', { name: '大阪候補A' }),
        ).toHaveCount(0);
        await page.getByRole('button', { name: '検索', exact: true }).click();
        await expect(page.getByText('35.011600, 135.768100')).toBeVisible();
        await page.screenshot({
            path: testInfo.outputPath('place-form.png'),
            animations: 'disabled',
        });
        await page
            .getByRole('button', { name: '地点を追加', exact: true })
            .click();
        await expect(
            page.getByRole('complementary', { name: '地点の記録' }),
        ).toBeVisible();
    });

    test('recording stops, previews, uploads and can be started again in Strict Mode', async ({
        page,
    }, testInfo) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page, 'e2e-admin');
        await openSeededOffice(page);
        const panel = page.getByRole('complementary', { name: '地点の記録' });
        await panel
            .getByRole('button', { name: '記録を追加', exact: true })
            .click();
        await panel.getByLabel('内容').fill('E2E 録音の保存確認');

        for (let index = 0; index < 2; index++) {
            await panel
                .getByRole('button', { name: '録音', exact: true })
                .click();
            await expect(
                panel.getByRole('button', { name: /^停止 0:0[1-9]$/ }),
            ).toBeVisible();
            await expect(
                panel.getByRole('button', { name: '記録する', exact: true }),
            ).toBeDisabled();
            await panel.getByRole('button', { name: /^停止 / }).click();
            await expect(
                panel.getByRole('button', { name: '記録する', exact: true }),
            ).toBeEnabled();
            await expect(panel.getByLabel('録音の確認')).toHaveCount(index + 1);
        }

        const previews = panel.getByLabel('録音の確認');
        await expect
            .poll(() =>
                previews
                    .first()
                    .evaluate((audio: HTMLAudioElement) => audio.readyState),
            )
            .toBeGreaterThan(0);
        await page.screenshot({
            path: testInfo.outputPath('recording-ready.png'),
            animations: 'disabled',
        });
        await panel
            .getByRole('button', { name: '記録する', exact: true })
            .click();
        const entry = panel
            .getByRole('listitem')
            .filter({ hasText: 'E2E 録音の保存確認' });
        await expect(entry.locator('audio')).toHaveCount(2);
        const audioUrl = await entry
            .locator('audio')
            .first()
            .getAttribute('src');
        expect(audioUrl).toBeTruthy();
        const response = await page.request.get(audioUrl!);
        expect(response.ok()).toBe(true);
        expect((await response.body()).byteLength).toBeGreaterThan(0);
    });

    test('a denied microphone leaves the draft usable and allows retrying', async ({
        page,
    }) => {
        await page.addInitScript(() => {
            navigator.mediaDevices.getUserMedia = () =>
                Promise.reject(
                    new DOMException('Permission denied', 'NotAllowedError'),
                );
        });
        await login(page, 'e2e-admin');
        await page.goto('/crm/map');
        await page.getByTitle(`${seededClient} ${seededOffice}`).click();
        const panel = page.getByRole('complementary', { name: '地点の記録' });
        await panel
            .getByRole('button', { name: '記録を追加', exact: true })
            .click();
        await panel.getByLabel('内容').fill('マイクなしでも記録できる');

        for (let attempt = 0; attempt < 2; attempt++) {
            await panel
                .getByRole('button', { name: '録音', exact: true })
                .click();
            await expect(
                panel.getByText(
                    '録音を開始できませんでした。マイクの使用許可と接続を確認してください。',
                ),
            ).toBeVisible();
            await expect(
                panel.getByRole('button', { name: '録音', exact: true }),
            ).toBeEnabled();
            await expect(
                panel.getByRole('button', { name: '記録する', exact: true }),
            ).toBeEnabled();
        }

        await expect(panel.getByLabel('内容')).toHaveValue(
            'マイクなしでも記録できる',
        );
        await panel
            .getByRole('button', { name: '記録する', exact: true })
            .click();
        await expect(panel.getByText('マイクなしでも記録できる')).toBeVisible();
    });

    test('contact creation preserves a draft and help never performs the action', async ({
        page,
    }, testInfo) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page, 'e2e-admin');
        await openSeededOffice(page);
        const panel = page.getByRole('complementary', { name: '地点の記録' });
        await panel
            .getByRole('button', { name: '記録を追加の使い方', exact: true })
            .click();
        await expect(
            page.getByRole('dialog', { name: '記録を追加', exact: true }),
        ).toContainText('写真・音声');
        await expect(panel.getByLabel('内容')).toHaveCount(0);
        await page
            .getByRole('button', { name: 'わかりました', exact: true })
            .click();
        await panel
            .getByRole('button', { name: '記録を追加', exact: true })
            .click();
        await panel.getByLabel('内容').fill('E2E 新しい担当者と次回訪問を相談');
        await panel
            .getByRole('button', { name: '担当者を追加', exact: true })
            .click();
        const dialog = page.getByRole('dialog', {
            name: '担当者を追加',
            exact: true,
        });
        const contactName = `E2E 佐藤 花子 ${testInfo.testId}`;
        await dialog.getByLabel('氏名').fill(contactName);
        await dialog.getByLabel('役職').fill('現場主任');
        await dialog
            .getByRole('button', { name: '登録して選択', exact: true })
            .click();
        await expect(dialog).toHaveCount(0);
        await expect(panel.getByLabel('担当者(客)')).toContainText(contactName);
        await expect(
            panel.getByLabel('担当者(客)').locator('option:checked'),
        ).toContainText(contactName);
        await expect(panel.getByLabel('内容')).toHaveValue(
            'E2E 新しい担当者と次回訪問を相談',
        );
        await panel
            .getByRole('button', { name: '好感触', exact: true })
            .click();
        await expect(
            panel.getByRole('button', { name: '好感触', exact: true }),
        ).toContainText('😊');
        await page.screenshot({
            path: testInfo.outputPath('contact-selected.png'),
            animations: 'disabled',
        });
        await panel
            .getByRole('button', { name: '記録する', exact: true })
            .click();
        await expect(
            panel
                .getByRole('listitem')
                .filter({ hasText: 'E2E 新しい担当者と次回訪問を相談' }),
        ).toContainText(contactName);
    });

    test('OSM is the default and returning from the list restores the map state', async ({
        page,
    }, testInfo) => {
        await login(page, 'e2e-admin');
        await page.goto('/crm/map');
        await expect(page.locator('.leaflet-tile').first()).toHaveAttribute(
            'src',
            /tile\.openstreetmap\.org/,
        );
        await page.locator('.leaflet-control-layers').hover();
        await page
            .getByRole('radio', { name: '地理院 標準', exact: true })
            .check();
        await page.getByLabel('顧客を探す', { exact: true }).fill('西日本');
        await page
            .getByRole('button', { name: seededClient, exact: true })
            .first()
            .click();
        await page.getByRole('checkbox', { name: 'アーカイブも表示' }).click();
        await expect(
            page.getByRole('checkbox', { name: 'アーカイブも表示' }),
        ).toBeChecked();
        await page.getByTitle(`${seededClient} ${seededOffice}`).click();
        await expect(
            page.getByRole('complementary', { name: '地点の記録' }),
        ).toBeVisible();
        await page.locator('.leaflet-control-zoom-in').click();
        await expect(
            page.locator('.leaflet-zoom-anim, .leaflet-cluster-anim'),
        ).toHaveCount(0);
        const memory = () =>
            page.evaluate(
                () =>
                    Object.entries(sessionStorage).find(([key]) =>
                        key.startsWith('crm-map:'),
                    )?.[1],
            );
        const saved = await memory();
        expect(saved).toBeTruthy();
        expect(JSON.parse(saved!).focusClientId).not.toBeNull();
        expect(JSON.parse(saved!).viewport.layer).toBe('地理院 標準');
        await page.getByRole('link', { name: '一覧', exact: true }).click();
        await expect(page).toHaveURL(/\/crm\/clients$/);
        await page
            .getByRole('link', { name: '地図に戻る', exact: true })
            .click();
        await expect(page).toHaveURL(/[?&]restore=1/);
        await expect(
            page.getByRole('complementary', { name: '地点の記録' }),
        ).toContainText(seededOffice);
        await expect(
            page.getByRole('checkbox', { name: 'アーカイブも表示' }),
        ).toBeChecked();
        await expect(
            page.getByRole('button', { name: '絞り込みを解除', exact: true }),
        ).toBeVisible();
        await expect(page.locator('.leaflet-tile').first()).toHaveAttribute(
            'src',
            /\/xyz\/std\//,
        );
        await expect.poll(memory).toBe(saved);
        await page.getByRole('link', { name: '一覧', exact: true }).click();
        await page.getByRole('link').filter({ hasText: seededClient }).click();
        await expect(
            page.getByRole('region', { name: `${seededClient}の顧客情報` }),
        ).toContainText('顧客詳細');
        await page.screenshot({
            path: testInfo.outputPath('client-identity.png'),
            animations: 'disabled',
        });
    });

    test('desktop help is embedded, hoverable and never performs the action', async ({
        page,
    }) => {
        await login(page, 'e2e-admin');
        await openSeededOffice(page);
        const panel = page.getByRole('complementary', { name: '地点の記録' });
        const action = panel.getByRole('button', {
            name: '記録を追加',
            exact: true,
        });
        const help = panel.getByRole('button', {
            name: '記録を追加の使い方',
            exact: true,
        });
        await expect(action).toBeVisible();
        await expect(help).toBeVisible();
        const actionBox = await action.boundingBox();
        const helpBox = await help.boundingBox();
        expect(actionBox).toBeTruthy();
        expect(helpBox).toBeTruthy();
        expect(helpBox!.x).toBeGreaterThan(actionBox!.x);
        expect(helpBox!.x + helpBox!.width).toBeLessThanOrEqual(
            actionBox!.x + actionBox!.width,
        );
        await help.hover();
        await expect(page.getByRole('tooltip')).toContainText(
            'この地点での訪問',
        );
        await expect(panel.getByLabel('内容')).toHaveCount(0);
        await page.keyboard.press('Escape');
        await expect(page.getByRole('tooltip')).toHaveCount(0);
        await action.click();
        await expect(panel.getByLabel('担当者(客)')).toBeVisible();
        await expect(
            panel.getByRole('button', {
                name: /(?:キャンセル|記録する|記録を編集|記録を削除)の使い方/,
            }),
        ).toHaveCount(0);
    });

    test('client-wide history shows its original place and author and edits stay on that place', async ({
        page,
    }, testInfo) => {
        await login(page, 'e2e-admin');
        await page.goto('/crm/clients');
        await page.getByRole('link').filter({ hasText: seededClient }).click();
        await page
            .getByRole('link')
            .filter({ hasText: 'E2E 神戸現場' })
            .click();
        await addLog(page, 'E2E 別地点の工程打ち合わせ');
        await openSeededOffice(page);
        const panel = page.getByRole('complementary', { name: '地点の記録' });
        const otherEntry = panel
            .getByRole('listitem')
            .filter({ hasText: 'E2E 別地点の工程打ち合わせ' });
        const officeEntry = panel
            .getByRole('listitem')
            .filter({ hasText: 'E2E 初回訪問。見積もりを依頼された。' });
        await expect(otherEntry).toHaveAttribute('data-current-place', 'false');
        await expect(otherEntry).toContainText('E2E 神戸現場');
        await expect(otherEntry).toContainText('担当者(社): E2E Admin User');
        await expect(officeEntry).toHaveAttribute('data-current-place', 'true');
        await expect(officeEntry).toHaveClass(/bg-sky-50/);
        await expect(officeEntry).toContainText('担当者(客): 山田 太郎');
        await page.mouse.move(0, 0);
        await expect(otherEntry).toHaveCSS('opacity', '0.7');
        await page.screenshot({
            path: testInfo.outputPath('combined-history.png'),
            animations: 'disabled',
        });
        await otherEntry
            .getByRole('button', { name: '記録を編集', exact: true })
            .click();
        await expect(panel.getByText('記録先: E2E 神戸現場')).toBeVisible();
        await panel
            .getByLabel('内容')
            .fill('E2E 別地点の工程打ち合わせ（更新）');
        await panel.getByRole('button', { name: '更新', exact: true }).click();
        await expect(otherEntry).toHaveAttribute('data-current-place', 'false');
        await otherEntry
            .getByRole('button', { name: 'E2E 神戸現場を地図で表示' })
            .click();
        await expect(otherEntry).toHaveAttribute('data-current-place', 'true');
        await expect(officeEntry).toHaveAttribute(
            'data-current-place',
            'false',
        );
        await expect(otherEntry).toContainText('担当者(社): E2E Admin User');
        await expect(
            page.getByTitle(`${seededClient} E2E 神戸現場`),
        ).toBeInViewport();
    });

    test('client list and detail links focus that client on the map', async ({
        page,
    }, testInfo) => {
        await login(page, 'e2e-login');

        for (const fromDetail of [false, true]) {
            await page.goto('/crm/clients');

            if (fromDetail) {
                await page.setViewportSize({ width: 390, height: 844 });
                await expect(
                    page
                        .getByRole('link')
                        .filter({ hasText: 'E2E 東大阪工業' })
                        .locator('.font-medium'),
                ).toBeVisible();
                expect(
                    await page
                        .getByRole('link')
                        .filter({ hasText: 'E2E 東大阪工業' })
                        .locator('.font-medium')
                        .evaluate(
                            (label) => label.scrollWidth <= label.clientWidth,
                        ),
                ).toBe(true);
                await page.screenshot({
                    path: testInfo.outputPath('client-list-mobile.png'),
                });
                await page
                    .getByRole('link')
                    .filter({ hasText: 'E2E 東大阪工業' })
                    .click();
                await expect(page).toHaveURL(/\/crm\/clients\/\d+$/);
                await page.screenshot({
                    path: testInfo.outputPath('client-detail-mobile.png'),
                });
            }

            await page
                .getByRole('link', {
                    name: 'E2E 東大阪工業を地図で表示',
                    exact: true,
                })
                .click();
            await expect(page).toHaveURL(
                (url) =>
                    url.pathname === '/crm/map' &&
                    Number(url.searchParams.get('client')) > 0 &&
                    url.searchParams.get('archived') === '1',
            );
            await expect(
                page.getByRole('button', {
                    name: '絞り込みを解除',
                    exact: true,
                }),
            ).toBeVisible();
            await expect(
                page.getByTitle('E2E 東大阪工業 E2E 東大阪事務所'),
            ).toBeInViewport();
            await expect
                .poll(async () =>
                    page.evaluate(() => {
                        const key = Object.keys(sessionStorage).find((key) =>
                            key.startsWith('crm-map:'),
                        );

                        return key
                            ? (
                                  JSON.parse(sessionStorage.getItem(key)!) as {
                                      viewport: { zoom: number };
                                  }
                              ).viewport.zoom
                            : null;
                    }),
                )
                .toBe(15);
        }
    });

    test('other-place history opens archived pins without reassigning the entry', async ({
        page,
    }) => {
        await login(page, 'e2e-admin');
        await page.goto('/crm/clients');
        await page.getByRole('link').filter({ hasText: seededClient }).click();
        await page
            .getByRole('link')
            .filter({ hasText: 'E2E 完了現場' })
            .click();
        await addLog(page, 'E2E 完了後のご挨拶');
        await openSeededOffice(page);
        const archive = page.getByRole('checkbox', {
            name: 'アーカイブも表示',
        });
        await expect(archive).not.toBeChecked();
        const entry = page
            .getByRole('listitem')
            .filter({ hasText: 'E2E 完了後のご挨拶' });
        await expect(entry).toHaveAttribute('data-current-place', 'false');
        await entry
            .getByRole('button', { name: 'E2E 完了現場を地図で表示' })
            .click();
        await expect(archive).toBeChecked();
        await expect(entry).toHaveAttribute('data-current-place', 'true');
        await expect(
            page.getByTitle(`${seededClient} E2E 完了現場`),
        ).toBeInViewport();
    });

    test('a client without places has an actionable map empty state', async ({
        page,
    }, testInfo) => {
        await login(page, 'e2e-admin');
        await page.goto('/crm/clients/create');
        const name = `E2E 位置未登録 ${testInfo.testId}`;
        await page.getByLabel('顧客名').fill(name);
        await page.getByLabel(/略称/).fill('未');
        await page
            .getByRole('button', { name: '顧客を追加', exact: true })
            .click();
        await page
            .getByRole('link', { name: `${name}を地図で表示`, exact: true })
            .click();
        await expect(page.getByRole('status')).toContainText(
            'この顧客の表示できる地点がありません',
        );
        await expect(
            page.getByRole('button', { name: '絞り込みを解除', exact: true }),
        ).toBeVisible();
    });

    test('current location shows nearby active places and can refresh and clear', async ({
        page,
        context,
    }, testInfo) => {
        await context.grantPermissions(['geolocation', 'microphone']);
        await context.setGeolocation({
            latitude: 34.7025,
            longitude: 135.4959,
            accuracy: 35,
        });
        await login(page, 'e2e-login');
        await page.goto('/crm/map');
        await expect(page.locator('.crm-current-location')).toHaveCount(0);
        await page.getByRole('button', { name: '現在地', exact: true }).click();
        await expect(page.locator('.crm-current-location')).toBeVisible();
        await expect(page.locator('.crm-location-accuracy')).toBeVisible();
        const nearby = page.getByRole('region', { name: '近くの訪問先' });
        await expect(nearby.getByRole('listitem').first()).toContainText(
            seededOffice,
        );
        await expect(nearby.getByRole('listitem').first()).toContainText('0m');
        await page.screenshot({
            path: testInfo.outputPath('nearby-desktop.png'),
        });
        await page.evaluate(() =>
            document.documentElement.classList.add('dark'),
        );
        await expect(
            page.getByRole('button', { name: '現在地を更新', exact: true }),
        ).toHaveCSS(
            'color',
            await page
                .locator('body')
                .evaluate((body) => getComputedStyle(body).color),
        );
        await page.screenshot({
            path: testInfo.outputPath('nearby-dark.png'),
            animations: 'disabled',
        });
        await page.evaluate(() =>
            document.documentElement.classList.remove('dark'),
        );
        await page.setViewportSize({ width: 390, height: 844 });
        await page.screenshot({
            path: testInfo.outputPath('nearby-mobile.png'),
        });
        await nearby.getByRole('button', { name: /候補\d+件/ }).click();
        await nearby
            .getByRole('button', { name: new RegExp(seededOffice) })
            .click();
        await expect(
            page.getByRole('complementary', { name: '地点の記録' }),
        ).toContainText(seededOffice);
        await page.getByRole('button', { name: '閉じる', exact: true }).click();
        await page.getByRole('checkbox', { name: 'アーカイブも表示' }).click();
        await expect(
            page.getByRole('checkbox', { name: 'アーカイブも表示' }),
        ).toBeChecked();
        await context.setGeolocation({
            latitude: 34.55,
            longitude: 135.45,
            accuracy: 35,
        });
        await page
            .getByRole('button', { name: '現在地を更新', exact: true })
            .click();
        await expect(
            page.getByTitle(`${seededClient} E2E 完了現場`),
        ).toBeInViewport();
        await expect(nearby).toContainText(
            '5km以内にアクティブな地点がありません',
        );
        await context.setGeolocation({
            latitude: 35.68,
            longitude: 139.76,
            accuracy: 1500,
        });
        await page
            .getByRole('button', { name: '現在地を更新', exact: true })
            .click();
        await expect(nearby).toContainText(
            '5km以内にアクティブな地点がありません',
        );
        await expect(nearby).toContainText('位置情報の精度が低い');
        await page.getByRole('button', { name: '現在地の表示を消す' }).click();
        await expect(nearby).toHaveCount(0);
        await expect(page.locator('.crm-current-location')).toHaveCount(0);
        await page.getByRole('button', { name: '現在地', exact: true }).click();
        await expect(page.locator('.crm-current-location')).toBeVisible();
    });

    test('location permission denial is actionable and archive help does not toggle the filter', async ({
        page,
    }) => {
        await page.addInitScript(() => {
            navigator.geolocation.getCurrentPosition = (_success, error) => {
                error?.({
                    code: 1,
                    message: 'Denied',
                    PERMISSION_DENIED: 1,
                    POSITION_UNAVAILABLE: 2,
                    TIMEOUT: 3,
                });
            };
        });
        await login(page, 'e2e-login');
        await page.goto('/crm/map');
        const archive = page.getByRole('checkbox', {
            name: 'アーカイブも表示',
        });
        const help = page.getByRole('button', {
            name: 'アーカイブも表示の使い方',
        });
        await help.hover();
        await expect(page.getByRole('tooltip')).toContainText(
            '過去の記録は削除されません',
        );
        await expect(archive).not.toBeChecked();
        await page.mouse.move(0, 0);
        await page.setViewportSize({ width: 390, height: 844 });
        await help.click();
        await expect(page.getByRole('dialog')).toContainText(
            '過去の記録は削除されません',
        );
        await page.getByRole('button', { name: 'わかりました' }).click();
        await expect(archive).not.toBeChecked();
        await page.getByRole('button', { name: '現在地', exact: true }).click();
        await expect(page.getByRole('status')).toContainText(
            '位置情報が許可されていません',
        );
        await expect(
            page.getByRole('button', { name: '現在地', exact: true }),
        ).toBeEnabled();
        await expect(page.locator('.crm-current-location')).toHaveCount(0);
    });

    test('a place removed while opening it does not leave a loading panel', async ({
        page,
    }) => {
        await login(page, 'e2e-login');
        await page.goto('/crm/map');
        await page.route('**/crm/map?place=*', async (route) => {
            const response = await route.fetch();
            const body = (await response.json()) as {
                props: { selectedPlace: null };
            };
            body.props.selectedPlace = null;
            await route.fulfill({ response, json: body });
        });
        await page.getByTitle(`${seededClient} ${seededOffice}`).click();
        await expect(page.getByRole('alert')).toHaveText(
            'この地点は削除されたか、表示できなくなりました。',
        );
        await expect(
            page.getByRole('complementary', { name: '地点の記録' }),
        ).toHaveCount(0);
    });
});
