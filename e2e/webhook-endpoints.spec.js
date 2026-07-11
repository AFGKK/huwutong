import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn } from './helpers.js';

test.describe('Webhook 端点管理 E2E', () => {

    test('页面加载正常', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/webhooks?tab=endpoints');

        await page.waitForSelector('h2:has-text("Webhook")', { timeout: 15000 });

        const heading = page.locator('h2', { hasText: 'Webhook 端点管理' });
        await expect(heading).toBeVisible({ timeout: 5000 });
    });

    test('页面无崩溃错误', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/webhooks?tab=endpoints');

        await expect(page.locator('text=页面不存在')).toHaveCount(0);
        await expect(page.locator('.el-alert--error')).toHaveCount(0);
    });

    test('加载 Webhook 回放页面正常', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/webhooks?tab=replay');

        await expect(page.locator('h2', { hasText: 'Webhook 事件回放面板' })).toBeVisible({ timeout: 10000 });
        await expect(page.locator('text=页面不存在')).toHaveCount(0);
    });

    test('加载 Webhook 事件页面正常', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/webhooks?tab=events');

        await expect(page.locator('h2', { hasText: 'Webhook 事件' })).toBeVisible({ timeout: 10000 });
        await expect(page.locator('text=页面不存在')).toHaveCount(0);
    });
});
