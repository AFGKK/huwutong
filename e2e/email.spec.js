import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn } from './helpers.js';

test.describe.configure({ mode: 'serial' });

test.describe('邮件管理 E2E', () => {

    test('邮件模板页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/email-templates');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('邮件追踪页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/email-tracking');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('页面无崩溃错误', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/email-templates');

        const error = page.locator('text=出错了, text=错误, text=Error').first();
        const hasError = await error.isVisible().catch(() => false);
        expect(hasError).toBeFalsy();
    });
});
