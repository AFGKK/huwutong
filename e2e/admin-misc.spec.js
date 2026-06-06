import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn } from './helpers.js';

test.describe.configure({ mode: 'serial' });

test.describe('其他管理功能 E2E', () => {

    test('AI 集成向导页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/wizard');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });

    test('AI 智能客服页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/ai-chat');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });

    test('API Playground 页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/playground');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });

    test('License 文件分发页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/license-files');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });

    test('全局资源白名单页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/global-resources');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });

    test('试用管理页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/trials');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });

    test('离线 License 页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/offline');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });

    test('续费失败流水线页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/billing/retention');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });
});
