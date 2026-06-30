import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn } from './helpers.js';

test.describe.configure({ mode: 'serial' });

test.describe('其他管理功能 E2E', () => {

    test('AI 集成向导页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/wizard');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('AI 智能客服页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/ai-chat');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('API Playground 页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/playground');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('License 文件分发页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/license-files');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('全局资源白名单页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/global-resources');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('试用管理页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/trials');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('离线 License 页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/offline');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });
    
    test('续费失败流水线页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/billing/retention');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });
});
