import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

const password = 'password';
const loginId = 'e2e-login';
const editorLoginId = 'e2e-editor';
const overlapDate = '2026-05-13';
const heatLevelDate = '2026-07-01';

function escapeRegExp(value: string) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

async function login(page: Page, userLoginId = loginId) {
    await page.goto('/login');
    await page.getByLabel('ログインID').fill(userLoginId);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: 'ログイン' }).click();
    await expect(page).toHaveURL(/\/schedule-overview(?:\?.*)?$/);
}

async function eventBox(locator: Locator) {
    await expect(locator).toBeVisible();

    const box = await locator.boundingBox();

    expect(box).not.toBeNull();

    return box!;
}

async function scrollUntilVisible(page: Page, locator: Locator) {
    for (let attempt = 0; attempt < 8; attempt++) {
        if (await locator.isVisible()) {
            return;
        }

        await page.evaluate(() => {
            window.scrollTo(0, document.documentElement.scrollHeight);
        });
        await page.waitForTimeout(350);
    }
}

async function expectSearchOverviewEditBackChain({
    page,
    location,
    editUrlPattern,
}: {
    page: Page;
    location: string;
    editUrlPattern: RegExp;
}) {
    await page.goto(
        `/schedule-search?location=${encodeURIComponent(location)}`,
    );

    const result = page.getByRole('button', {
        name: new RegExp(escapeRegExp(location)),
    });

    await expect(result).toBeVisible();
    await result.click();
    await expect(page).toHaveURL(/\/schedule-overview\?.*return_to=/);

    const overviewUrl = page.url();
    const editLink = page
        .getByRole('link', {
            name: new RegExp(`${escapeRegExp(location)}.*編集`),
        })
        .first();

    await expect(editLink).toBeVisible();
    await editLink.click();
    await expect(page).toHaveURL(editUrlPattern);

    await page.getByRole('button', { name: '予定表へ戻る' }).click();
    await expect(page).toHaveURL(overviewUrl);

    await page.getByRole('button', { name: '検索へ戻る' }).click();
    await expect(page).toHaveURL(/\/schedule-search\?/);
    await expect(page).not.toHaveURL(editUrlPattern);
    await expect(page.locator('[data-search-selected="true"]')).toContainText(
        location,
    );
}

test.describe('schedule overview timeline', () => {
    test('stacks overlapping timeline chips below each other', async ({
        page,
    }) => {
        await login(page);
        await page.goto(`/schedule-overview?date=${overlapDate}`);

        const overlapEarly = page.getByRole('button', {
            name: /E2E Overlap Early/,
        });
        const overlapLate = page.getByRole('button', {
            name: /E2E Overlap Late/,
        });
        const backToBackFirst = page.getByRole('button', {
            name: /E2E Back To Back First/,
        });
        const backToBackSecond = page.getByRole('button', {
            name: /E2E Back To Back Second/,
        });

        const earlyBox = await eventBox(overlapEarly);
        const lateBox = await eventBox(overlapLate);
        const backToBackFirstBox = await eventBox(backToBackFirst);
        const backToBackSecondBox = await eventBox(backToBackSecond);

        expect(earlyBox.y + earlyBox.height).toBeLessThanOrEqual(lateBox.y);
        expect(
            Math.abs(backToBackFirstBox.y - backToBackSecondBox.y),
        ).toBeLessThan(2);
    });
});

test.describe('schedule overview calendar heat', () => {
    test('uses fixed construction count ranges for heat labels', async ({
        page,
    }) => {
        await login(page);
        await page.goto(`/schedule-overview?date=${heatLevelDate}`);

        for (const [date, count, label] of [
            ['2026-07-01', 0, '0件'],
            ['2026-07-02', 4, '1〜4件'],
            ['2026-07-03', 5, '5〜7件'],
            ['2026-07-04', 7, '5〜7件'],
            ['2026-07-05', 8, '8件以上'],
            ['2026-07-06', 10, '8件以上'],
            ['2026-07-07', 11, '8件以上'],
            ['2026-07-08', 12, '8件以上'],
            ['2026-07-09', 13, '8件以上'],
        ] as const) {
            await expect(
                page.getByRole('link', {
                    name: new RegExp(
                        `${escapeRegExp(date)}: 混雑度${escapeRegExp(label)}、工事${count}件`,
                    ),
                }),
            ).toBeVisible();
        }
    });
});

test.describe('schedule search return flow', () => {
    test('restores the search scroll position after returning from overview', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1280, height: 420 });
        await login(page);
        await page.goto(
            '/schedule-search?location=E2E%20Search%20Deep&direction=asc',
        );

        const toolbar = page.locator('[data-search-toolbar="true"]');
        const result = page.getByRole('button', {
            name: /E2E Search Deep 25/,
        });

        await expect(toolbar).toBeVisible();
        await scrollUntilVisible(page, result);
        await expect(result).toBeVisible();
        await result.scrollIntoViewIfNeeded();
        await expect(result).toBeInViewport();
        await expect
            .poll(async () => page.evaluate(() => window.scrollY))
            .toBeGreaterThan(0);

        const scrollBeforeNavigate = await page.evaluate(() => window.scrollY);
        const resultTopBefore = await result.evaluate(
            (element) => element.getBoundingClientRect().top,
        );
        const toolbarTopBefore = await toolbar.evaluate(
            (element) => element.getBoundingClientRect().top,
        );

        expect(Math.abs(toolbarTopBefore)).toBeLessThanOrEqual(1);

        await result.click();

        await expect(page).toHaveURL(/\/schedule-overview\?.*return_to=/);

        await page.getByRole('button', { name: '検索へ戻る' }).click();

        await expect(page).toHaveURL(/\/schedule-search\?/);
        await expect(page).not.toHaveURL(/selected_type=/);

        await expect
            .poll(async () => page.evaluate(() => window.scrollY))
            .toBeGreaterThanOrEqual(Math.max(scrollBeforeNavigate - 20, 0));

        const selectedResult = page.locator('[data-search-selected="true"]');
        await expect(selectedResult).toContainText('E2E Search Deep 25');
        await expect(selectedResult).toBeInViewport();

        const resultTopAfter = await selectedResult.evaluate(
            (element) => element.getBoundingClientRect().top,
        );
        const toolbarTopAfter = await toolbar.evaluate(
            (element) => element.getBoundingClientRect().top,
        );

        expect(Math.abs(resultTopAfter - resultTopBefore)).toBeLessThan(80);
        expect(Math.abs(toolbarTopAfter)).toBeLessThanOrEqual(1);
    });

    test('returns from overview to search after visiting construction and business edit pages', async ({
        page,
    }) => {
        await login(page, editorLoginId);

        await expectSearchOverviewEditBackChain({
            page,
            location: 'E2E Overlap Early',
            editUrlPattern: /\/construction-schedules\/\d+\/edit/,
        });

        await expectSearchOverviewEditBackChain({
            page,
            location: 'E2E Overlap Late',
            editUrlPattern: /\/business-schedules\/\d+\/edit/,
        });
    });
});
