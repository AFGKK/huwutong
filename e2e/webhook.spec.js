import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn } from './helpers.js';

test.describe.configure({ mode: 'serial' });

test.describe('Webhook 管理 E2E', () => {

    test('Webhook 回放页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/webhook-replay');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });

    test('Webhook 事件页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/webhook-events');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });
});
