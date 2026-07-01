import { readFile } from 'node:fs/promises';
import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const password = 'password';
const editorLoginId = 'e2e-editor';
const adminLoginId = 'e2e-admin';

function escapeRegExp(value: string) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

async function login(page: Page, userLoginId: string) {
    await page.goto('/login');
    await page.getByLabel('ログインID').fill(userLoginId);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: 'ログイン' }).click();
    await expect(page).toHaveURL(/\/schedule-overview(?:\?.*)?$/);
}

async function openEditFromSearch({
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

    const editLink = page
        .getByRole('link', {
            name: new RegExp(`${escapeRegExp(location)}.*編集`),
        })
        .first();

    await expect(editLink).toBeVisible();
    await editLink.click();
    await expect(page).toHaveURL(editUrlPattern);
}

test.describe('action labels', () => {
    test('shows domain-specific labels on editor create and edit forms', async ({
        page,
    }) => {
        await login(page, editorLoginId);

        await page.goto('/construction-schedules/create');
        await expect(
            page.getByRole('button', { name: '工事予定を作成' }),
        ).toBeVisible();

        await page.goto('/business-schedules/create');
        await expect(
            page.getByRole('button', { name: '業務予定を作成' }),
        ).toBeVisible();

        await page.goto('/internal-notices/create');
        await expect(
            page.getByRole('button', { name: '業務連絡を作成' }),
        ).toBeVisible();

        await page.goto('/construction-sites/create');
        await expect(
            page.getByRole('button', { name: '現場案内図を追加' }),
        ).toBeVisible();

        await page.goto('/cleaning-duty-rules/create');
        await expect(
            page.getByRole('button', { name: '掃除当番設定を作成' }),
        ).toBeVisible();

        await openEditFromSearch({
            page,
            location: 'E2E Overlap Early',
            editUrlPattern: /\/construction-schedules\/\d+\/edit/,
        });
        await expect(
            page.getByRole('button', { name: '工事予定を修正' }),
        ).toBeVisible();

        await openEditFromSearch({
            page,
            location: 'E2E Overlap Late',
            editUrlPattern: /\/business-schedules\/\d+\/edit/,
        });
        await expect(
            page.getByRole('button', { name: '業務予定を修正' }),
        ).toBeVisible();

        await page.goto('/settings/profile');
        await expect(
            page.getByRole('button', { name: 'プロフィールを保存' }),
        ).toBeVisible();
    });

    test('shows domain-specific labels on admin and voucher actions', async ({
        page,
    }) => {
        await login(page, adminLoginId);

        await page.goto('/admin/users');
        await expect(
            page.getByRole('button', { name: 'ユーザーを検索' }),
        ).toBeVisible();

        await page.goto('/admin/users/create');
        await expect(
            page.getByRole('button', { name: 'ユーザーを追加' }),
        ).toBeVisible();

        await page.goto('/admin/audit-logs');
        await expect(
            page.getByRole('button', { name: '監査ログを検索' }),
        ).toBeVisible();

        await page.goto('/voucher-confirmations?date=2026-05-13&day=all');
        await expect(
            page.getByRole('button', { name: '伝票確認を保存' }).first(),
        ).toBeVisible();
    });

    test('keeps two-factor action labels localized', async () => {
        const setupModal = await readFile(
            'resources/js/components/two-factor-setup-modal.tsx',
            'utf8',
        );
        const recoveryCodes = await readFile(
            'resources/js/components/two-factor-recovery-codes.tsx',
            'utf8',
        );

        expect(setupModal).toContain('次へ');
        expect(setupModal).toContain('戻る');
        expect(setupModal).toContain('認証する');
        expect(recoveryCodes).toContain('リカバリーコードを表示');
        expect(recoveryCodes).toContain('リカバリーコードを再生成');
    });
});
