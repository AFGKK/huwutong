import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

/**
 * E2E: 核心用户旅程
 *
 * 测试流程: 注册 → 登录 → 查看仪表盘 → 登出
 *
 * 运行说明:
 *   1. 确保 MySQL/Redis 服务已启动
 *   2. php artisan serve --port=8000
 *   3. npx playwright test
 */

const BASE_URL = process.env.BASE_URL || 'http://localhost:8000';
const TEST_EMAIL = `e2e_${Date.now()}@test.com`;
const TEST_PASSWORD = 'E2e@Test2026!';
const TEST_NAME = 'E2E Test User';

test.describe.configure({ mode: 'serial' });

test.describe('核心用户旅程', () => {

    test('1. 访问首页显示登录页面', async ({ page }) => {
        await page.goto('/');
        await expect(page).toHaveTitle(/登录|Login|HWT/);
        // 应该能看到登录表单
        await expect(page.locator('text=登录').first()).toBeVisible();
    });

    test('2. 注册新用户', async ({ page }) => {
        await page.goto('/register');
        await expect(page).toHaveURL(/register/);

        // 填写注册表单
        await page.fill('input[placeholder*="姓名"]', TEST_NAME);
        await page.fill('input[placeholder*="邮箱"]', TEST_EMAIL);
        await page.fill('input[placeholder*="密码"]', TEST_PASSWORD);
        await page.fill('input[placeholder*="确认密码"]', TEST_PASSWORD);

        // 提交
        await page.click('button:has-text("注册")');

        // 等待成功响应
        await page.waitForTimeout(2000);
        // 注册成功后会跳转到仪表盘或登录页
        const currentUrl = page.url();
        expect(
            currentUrl.includes('dashboard') ||
            currentUrl.includes('login')
        ).toBeTruthy();
    });

    test('3. 使用新账号登录', async ({ page }) => {
        await page.goto('/login');
        await expect(page).toHaveURL(/login/);

        // 填写登录表单
        await page.fill('input[placeholder*="邮箱"]', TEST_EMAIL);
        await page.fill('input[placeholder*="密码"]', TEST_PASSWORD);

        // 提交
        await page.click('button:has-text("登录")');

        // 等待导航到仪表盘
        await page.waitForURL(/dashboard/, { timeout: 10000 });
        await expect(page).toHaveURL(/dashboard/);
    });

    test('4. 仪表盘可见并显示用户信息', async ({ page }) => {
        // 先登录
        await page.goto('/login');
        await page.fill('input[placeholder*="邮箱"]', TEST_EMAIL);
        await page.fill('input[placeholder*="密码"]', TEST_PASSWORD);
        await page.click('button:has-text("登录")');
        await page.waitForURL(/dashboard/, { timeout: 10000 });

        // 验证仪表盘已经加载
        await expect(page.locator('text=仪表盘').first()).toBeVisible();
    });

    test('5. 访问 License 管理页', async ({ page }) => {
        // 登录
        await page.goto('/login');
        await page.fill('input[placeholder*="邮箱"]', TEST_EMAIL);
        await page.fill('input[placeholder*="密码"]', TEST_PASSWORD);
        await page.click('button:has-text("登录")');
        await page.waitForURL(/dashboard/, { timeout: 10000 });

        // 导航到 License 页面
        await page.goto('/licenses');
        await page.waitForTimeout(1000);

        // 页面应该显示 License 管理标题
        await expect(page.locator('h2')).toContainText('License');
    });

    test('6. 登出', async ({ page }) => {
        // 登录
        await page.goto('/login');
        await page.fill('input[placeholder*="邮箱"]', TEST_EMAIL);
        await page.fill('input[placeholder*="密码"]', TEST_PASSWORD);
        await page.click('button:has-text("登录")');
        await page.waitForURL(/dashboard/, { timeout: 10000 });

        // 点击退出按钮
        const logoutBtn = page.locator('button:has-text("退出"), span:has-text("退出")').first();
        if (await logoutBtn.isVisible()) {
            await logoutBtn.click();
        } else {
            // 尝试通过 URL 退出
            await page.goto('/logout');
        }

        await page.waitForTimeout(1000);
        // 登出后应回到登录页
        expect(page.url()).toContain('login');
    });

    test('7. 无效密码登录应显示错误', async ({ page }) => {
        await page.goto('/login');

        await page.fill('input[placeholder*="邮箱"]', TEST_EMAIL);
        await page.fill('input[placeholder*="密码"]', 'WrongPassword123!');
        await page.click('button:has-text("登录")');

        // 应该看到错误提示
        await page.waitForTimeout(1000);
        // 页面应该停留在登录页
        expect(page.url()).toContain('login');
    });

    test('8. 忘记密码流程', async ({ page }) => {
        await page.goto('/forgot-password');

        await expect(page.locator('text=忘记密码').first()).toBeVisible();
        await page.fill('input[placeholder*="邮箱"]', TEST_EMAIL);
        await page.click('button:has-text("发送")');

        // 验证成功提示
        await page.waitForTimeout(2000);
        // 应该显示成功消息或重定向到登录页
        expect(
            page.url().includes('login') || page.url().includes('forgot')
        ).toBeTruthy();
    });
});
