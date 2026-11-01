import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const password = 'password';
const passkeyName = 'E2E browser passkey';

async function confirmPassword(page: Page): Promise<void> {
    await page.goto('/user/confirm-password');
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: 'パスワードを確認' }).click();
    await expect(page).not.toHaveURL(/\/user\/confirm-password$/);
}

test('registers a passkey, signs in without a password and removes it', async ({
    browserName,
    context,
    page,
}) => {
    test.skip(
        browserName !== 'chromium',
        'Virtual WebAuthn requires Chromium CDP.',
    );

    const client = await context.newCDPSession(page);

    await client.send('WebAuthn.enable');
    const { authenticatorId } = await client.send(
        'WebAuthn.addVirtualAuthenticator',
        {
            options: {
                protocol: 'ctap2',
                transport: 'internal',
                hasResidentKey: true,
                hasUserVerification: true,
                isUserVerified: true,
                automaticPresenceSimulation: true,
            },
        },
    );

    // Exercise the explicit sign-in button independently of conditional autofill.
    await page.addInitScript(() => {
        PublicKeyCredential.isConditionalMediationAvailable = () =>
            Promise.resolve(false);
    });

    try {
        await page.goto('/login');
        await page.getByLabel('ログインID').fill('e2e-login');
        await page.locator('input[name="password"]').fill(password);
        await page
            .getByRole('button', { name: 'ログイン', exact: true })
            .click();
        await expect(page).toHaveURL(/\/schedule-overview(?:\?.*)?$/);
        await confirmPassword(page);
        await page.goto('/settings/security');
        await page.getByLabel('パスキー名（任意）').fill(passkeyName);

        const registration = page.waitForResponse(
            (response) =>
                new URL(response.url()).pathname === '/user/passkeys' &&
                response.request().method() === 'POST',
        );

        await page.getByRole('button', { name: 'パスキーを追加' }).click();
        expect((await registration).ok()).toBe(true);
        await expect(
            page.getByText(passkeyName, { exact: true }),
        ).toBeVisible();

        await context.clearCookies();
        await page.goto('/login');

        const verification = page.waitForResponse(
            (response) =>
                new URL(response.url()).pathname === '/passkeys/login' &&
                response.request().method() === 'POST',
        );

        await page
            .getByRole('button', { name: 'パスキーでサインイン' })
            .click();
        const response = await verification;

        expect(response.ok()).toBe(true);
        expect(response.request().postDataJSON()).toMatchObject({
            remember: false,
        });
        await expect(page).toHaveURL(/\/schedule-overview(?:\?.*)?$/);
        await confirmPassword(page);
        await page.goto('/settings/security');
        await expect(
            page.getByText(passkeyName, { exact: true }),
        ).toBeVisible();
        await page
            .getByText(passkeyName, { exact: true })
            .locator('..')
            .locator('..')
            .getByRole('button', { name: '削除', exact: true })
            .click();
        await expect(page.getByText(passkeyName, { exact: true })).toHaveCount(
            0,
        );
        await expect(
            page.getByText('パスキーはまだ登録されていません。'),
        ).toBeVisible();
    } finally {
        if (!page.isClosed()) {
            await client.send('WebAuthn.removeVirtualAuthenticator', {
                authenticatorId,
            });
            await client.detach();
        }
    }
});
