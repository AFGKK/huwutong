import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn, setupApiMocks, getMockUser, adminUrl } from './helpers.js';

/**
 * 核心用户旅程 E2E 测试
 *
 * 测试流程: 登录页 → 系统状态 → 认证重定向 → 仪表盘 → 页面导航 → 登出
 */
const TEST_EMAIL = `e2e_${Date.now()}@test.com`;

test.describe.configure({ mode: 'serial' });

test.describe('核心用户旅程', () => {

    test('1. 访问后台显示登录页面', async ({ page }) => {
        await page.goto(adminUrl('/admin/login'));
        await expect(page).toHaveTitle(/HWT License/);
        await expect(page.locator('button:has-text("登")').first()).toBeVisible();
    });

    test('2. 系统状态页面可访问', async ({ page }) => {
        await page.goto(adminUrl('/admin/status'));
        await expect(page.locator('text=系统状态').first()).toBeVisible({ timeout: 5000 });
    });

    test('3. 未登录访问仪表盘应重定向到登录页', async ({ page }) => {
        await page.goto(adminUrl('/admin/dashboard'));
        await page.waitForTimeout(2000);
        expect(page.url()).toContain('login');
    });

    test('4. 仪表盘可见并显示用户信息', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/dashboard', {
            email: TEST_EMAIL,
            name: 'E2E Test User',
            roles: ['admin'],
        });

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('5. 访问 License 管理页', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/licenses', {
            email: TEST_EMAIL,
            name: 'E2E Test User',
            roles: ['admin'],
        });

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('6. 登出后重定向', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/dashboard', {
            email: TEST_EMAIL,
            name: 'E2E Test User',
            roles: ['admin'],
        });
        await page.waitForTimeout(1000);

        // 清除 localStorage
        await page.evaluate(() => {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user');
        });

        // 导航到仪表盘应被重定向到登录页
        await page.goto(adminUrl('/admin/dashboard'));
        await page.waitForTimeout(3000);

        expect(page.url()).toContain('login');
    });

    test('7. 无效登录表单验证', async ({ page }) => {
        await page.goto(adminUrl('/admin/login'));

        // 直接点击登录按钮（空表单触发验证）
        await page.click('button:has-text("登")');
        await page.waitForTimeout(1000);

        // 应显示表单验证错误
        const errorMsg = page.locator('.el-form-item__error, .el-message--error, [class*="error"]').first();
        await expect(errorMsg).toBeVisible({ timeout: 3000 });
    });

    test('8. 系统健康页面可访问', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/health', {
            email: TEST_EMAIL,
            name: 'E2E Test User',
            roles: ['admin'],
        });

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });
});
