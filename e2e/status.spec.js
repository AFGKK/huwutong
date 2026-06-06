import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn } from './helpers.js';

test.describe('系统状态公开页面', () => {
    test('公开状态页加载正常', async ({ page }) => {
        await page.goto('/admin/status');
        await page.waitForTimeout(2000);
        await expect(page.locator('body')).toBeVisible();
    });
});
