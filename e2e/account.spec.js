import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn } from './helpers.js';

test.describe.configure({ mode: 'serial' });

test.describe('账号安全 E2E', () => {

    test('活跃会话页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/sessions');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });

    test('登录历史页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/account/login-history');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });

    test('账号绑定页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/account/binding');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });

    test('邀请码页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/invite-codes');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });
});
