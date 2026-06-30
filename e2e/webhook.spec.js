import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn } from './helpers.js';

test.describe.configure({ mode: 'serial' });

test.describe('Webhook 管理 E2E', () => {

    test('Webhook 回放页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/webhook-replay');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('Webhook 事件页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/webhook/events');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });
});
