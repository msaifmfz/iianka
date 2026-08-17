import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

const password = 'password';
const adminLoginId = 'e2e-admin';

const usedGuideFileName = 'E2E案内図_使用中';
const unusedGuideFileName = 'E2E案内図_未使用';
const upcomingScheduleName = 'E2E 案内図 今後の現場';
const laterUpcomingScheduleName = 'E2E 案内図 先の現場';
const pastScheduleName = 'E2E 案内図 過去の現場';

async function login(page: Page, userLoginId = adminLoginId): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('ログインID').fill(userLoginId);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: 'ログイン' }).click();
    await expect(page).toHaveURL(/\/schedule-overview(?:\?.*)?$/);
}

/**
 * The hold gesture the cards and usage rows share: press, wait past the 500ms
 * threshold, release. Anything shorter is a tap and navigates instead.
 */
async function holdToPreview(page: Page, target: Locator): Promise<void> {
    const box = await target.boundingBox();

    if (box === null) {
        throw new Error('Could not resolve the hold target position.');
    }

    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await page.mouse.down();
    await page.waitForTimeout(550);
    await page.mouse.up();
}

test.describe('site guide library', () => {
    test('filters the library by guide file name', async ({ page }) => {
        await login(page);
        await page.goto('/construction-sites');

        const usedCard = page.getByRole('button', {
            name: `${usedGuideFileName} の詳細ページを開く`,
        });
        const unusedCard = page.getByRole('button', {
            name: `${unusedGuideFileName} の詳細ページを開く`,
        });

        await expect(usedCard).toBeVisible();
        await expect(unusedCard).toBeVisible();

        await page.getByLabel('案内図を検索').fill('未使用');

        await expect(page).toHaveURL(/\/construction-sites\?search=/);
        await expect(unusedCard).toBeVisible();
        await expect(usedCard).toBeHidden();

        await page.getByRole('link', { name: '条件をクリア' }).click();
        await expect(page).toHaveURL(/\/construction-sites$/);
        await expect(usedCard).toBeVisible();
    });

    test('shows a guide file usage count and its unused state', async ({
        page,
    }) => {
        await login(page);
        await page.goto('/construction-sites');

        await expect(
            page.getByRole('button', {
                name: `${usedGuideFileName} の詳細ページを開く`,
            }),
        ).toContainText('使用予定 3件');
        await expect(
            page.getByRole('button', {
                name: `${unusedGuideFileName} の詳細ページを開く`,
            }),
        ).toContainText('未使用');
    });

    test('lists guide file names as headings', async ({ page }) => {
        await login(page);
        await page.goto('/construction-sites');

        // The card title is a heading wrapping its press target, so the grid
        // stays navigable by heading rather than only by button.
        await expect(
            page.getByRole('heading', { name: new RegExp(usedGuideFileName) }),
        ).toBeVisible();
    });

    test('previews a guide file and its schedules on hold, then edits from there', async ({
        page,
    }) => {
        await login(page);
        await page.goto('/construction-sites');

        const libraryUrl = page.url();

        await holdToPreview(
            page,
            page.getByRole('button', {
                name: `${usedGuideFileName} の詳細ページを開く`,
            }),
        );

        const guideDialog = page.getByRole('dialog', {
            name: usedGuideFileName,
        });

        await expect(guideDialog).toBeVisible();
        // Holding previews rather than navigating.
        await expect(page).toHaveURL(libraryUrl);
        await expect(guideDialog).toContainText('今後の予定');
        await expect(guideDialog).toContainText('過去の予定');
        await expect(guideDialog).toContainText(upcomingScheduleName);
        await expect(guideDialog).toContainText(pastScheduleName);

        // The upcoming group runs soonest-first: the next use of the guide file
        // is the one a rename or a delete would disrupt, so it leads.
        await expect(
            guideDialog.getByRole('button', { name: /の予定ページを開く$/ }),
        ).toHaveText([
            new RegExp(upcomingScheduleName),
            new RegExp(laterUpcomingScheduleName),
            new RegExp(pastScheduleName),
        ]);

        const scheduleRow = guideDialog.getByRole('button', {
            name: `${upcomingScheduleName}の予定ページを開く`,
        });

        await holdToPreview(page, scheduleRow);

        const scheduleDialog = page.getByRole('dialog', {
            name: upcomingScheduleName,
        });

        await expect(scheduleDialog).toBeVisible();
        await expect(scheduleDialog).toContainText('E2E Guide Contractor');
        await expect(scheduleDialog).toContainText('E2E 案内図 持ち出し');
        await expect(page).toHaveURL(libraryUrl);

        // The schedule dialog stacks on the guide dialog: closing it returns to
        // the guide rather than dropping the user back on the grid.
        await page.keyboard.press('Escape');
        await expect(scheduleDialog).toBeHidden();
        await expect(guideDialog).toBeVisible();

        await holdToPreview(page, scheduleRow);
        await expect(scheduleDialog).toBeVisible();

        await scheduleDialog
            .getByRole('link', { name: '編集ページへ' })
            .click();

        await expect(page).toHaveURL(
            /\/construction-schedules\/\d+\/edit\?return_to=/,
        );

        await page.getByRole('button', { name: '現場案内図へ戻る' }).click();
        await expect(page).toHaveURL(/\/construction-sites/);
    });

    test('opens the same guide detail from the keyboard accessible control', async ({
        page,
    }) => {
        await login(page);
        await page.goto('/construction-sites');

        await page
            .getByRole('button', {
                name: `${unusedGuideFileName} の詳細を表示`,
            })
            .click();

        const guideDialog = page.getByRole('dialog', {
            name: unusedGuideFileName,
        });

        await expect(guideDialog).toBeVisible();
        await expect(guideDialog).toContainText(
            'この案内図を使用している予定はありません。',
        );
    });

    test('lists the usage schedules on the guide detail page', async ({
        page,
    }) => {
        await login(page);
        await page.goto('/construction-sites');

        await page
            .getByRole('button', {
                name: `${usedGuideFileName} の詳細ページを開く`,
            })
            .click();

        await expect(page).toHaveURL(/\/construction-sites\/\d+$/);
        await expect(
            page.getByRole('heading', { name: '使用している予定' }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', {
                name: `${pastScheduleName}の予定ページを開く`,
            }),
        ).toBeVisible();
    });
});
