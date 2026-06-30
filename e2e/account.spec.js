import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn } from './helpers.js';

test.describe.configure({ mode: 'serial' });

test.describe('账号安全 E2E', () => {

    test('活跃会话页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/sessions');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('登录历史页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/account/login-history');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('账号绑定页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/account/binding');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('邀请码页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/invite-codes');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });
});
