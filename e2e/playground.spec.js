import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn } from './helpers.js';

test.describe('API Playground 页面', () => {
    test('页面加载正常，无错误', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/playground');
        await page.waitForTimeout(1000);
        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });
});
