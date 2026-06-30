import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn } from './helpers.js';

test.describe.configure({ mode: 'serial' });

test.describe('MFA 设置 E2E', () => {

    test('MFA 页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/mfa');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('页面无崩溃错误', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/mfa');

        const error = page.locator('text=出错了, text=错误, text=Error').first();
        const hasError = await error.isVisible().catch(() => false);
        expect(hasError).toBeFalsy();
    });
});
