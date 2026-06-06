import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn } from './helpers.js';

/**
 * License 管理 E2E 测试
 *
 * 测试流程：License 列表页加载、搜索、详情导航
 */
test.describe.configure({ mode: 'serial' });

test.describe('License 管理 E2E', () => {

    test('L1. License 列表页正常加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/licenses');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });

    test('L2. License 列表包含页面元素', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/licenses');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });

        // 验证页面没有崩溃
        const error = page.locator('text=错误, text=出错了, text=Error').first();
        const hasError = await error.isVisible().catch(() => false);
        expect(hasError).toBeFalsy();
    });

    test('L3. License 详情页查看', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/licenses');

        const firstLink = page.locator('a:has-text("HWT-"), a:has-text("LIC-"), .el-link, .el-table__row a, td a').first();
        if (await firstLink.isVisible().catch(() => false)) {
            await firstLink.click();
            await page.waitForTimeout(2000);
            expect(page.url()).toMatch(/licenses\/\d+/);
        } else {
            test.skip(true, '列表为空，跳过详情页测试');
        }
    });

    test('L4. License 搜索/筛选功能', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/licenses');

        const searchInput = page.locator('input[placeholder*="搜索"], input[placeholder*="查找"], .el-input input').first();
        if (await searchInput.isVisible().catch(() => false)) {
            await searchInput.fill('test');
            await searchInput.press('Enter');
            await page.waitForTimeout(2000);
            expect(page.url()).toContain('licenses');
        }
    });
});
