import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

const password = 'password';
const loginId = 'e2e-login';
const overlapDate = '2026-05-13';

async function login(page: Page) {
    await page.goto('/login');
    await page.getByLabel('ログインID').fill(loginId);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: 'ログイン' }).click();
    await expect(page).toHaveURL(/\/construction-schedules(?:\?.*)?$/);
}

async function eventBox(locator: Locator) {
    await expect(locator).toBeVisible();

    const box = await locator.boundingBox();

    expect(box).not.toBeNull();

    return box!;
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
