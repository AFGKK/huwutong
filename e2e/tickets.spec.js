import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn } from './helpers.js';

test.describe.configure({ mode: 'serial' });

test.describe('工单管理 E2E', () => {

    test('工单列表页加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/tickets');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });

    test('页面无崩溃错误', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/tickets');

        const error = page.locator('text=出错了, text=错误, text=Error').first();
        const hasError = await error.isVisible().catch(() => false);
        expect(hasError).toBeFalsy();
    });
});
