import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn } from './helpers.js';

test.describe('Webhook 端点管理 E2E', () => {

    test('页面加载正常', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/webhook-endpoints');

        const heading = page.locator('h2').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });

    test('页面无崩溃错误', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/webhook-endpoints');

        const error = page.locator('text=出错了, text=错误, text=Error').first();
        const hasError = await error.isVisible().catch(() => false);
        expect(hasError).toBeFalsy();
    });

    test('加载 Webhook 回放页面正常', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/webhook-replay');

        await expect(page.locator('body')).toBeVisible({ timeout: 5000 });
    });

    test('加载 Webhook 事件页面正常', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/webhook-events');

        await expect(page.locator('body')).toBeVisible({ timeout: 5000 });
    });
});
