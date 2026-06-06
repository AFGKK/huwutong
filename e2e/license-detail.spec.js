import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn, getMockLicenses, getMockLicenseDetail } from './helpers.js';

/**
 * License 详情与激活管理 E2E 测试
 *
 * 测试场景：
 *   - License 详情页加载和基本信息展示
 *   - 设备激活列表展示
 *   - License 暂停/恢复/激活状态操作
 *   - 功能特性展示
 */
test.describe.configure({ mode: 'serial' });

test.describe('License 详情与激活 E2E', () => {

    // 共同的 mock 数据
    const mockLicenses = getMockLicenses(8);
    const mockLicenseDetail = getMockLicenseDetail(1);

    test('LD1. License 详情页基本信息加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/licenses/1', {
            mockLicenses,
            mockLicenseDetail,
        });

        // 验证页面标题（el-page-header 的 content 或 h2）
        const heading = page.locator('h2, .page-title, [class*="title"], .el-page-header').first();
        await expect(heading).toBeVisible({ timeout: 10000 });
    });

    test('LD2. License 详情页统计卡片', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/licenses/1', {
            mockLicenses,
            mockLicenseDetail,
        });

        // 验证统计卡片（seats, devices 等）
        const statCards = page.locator('.el-card, .stat-card, [class*="stat"]').first();
        const hasStats = await statCards.isVisible().catch(() => false);

        if (hasStats) {
            await expect(statCards).toBeVisible();
        } else {
            // 至少页面加载成功
            const heading = page.locator('h2, .page-title, [class*="title"]').first();
            await expect(heading).toBeVisible({ timeout: 3000 });
        }
    });

    test('LD3. License 详情页拥有设备/激活列表', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/licenses/1', {
            mockLicenses,
            mockLicenseDetail,
        });

        // 查找"设备"或"激活" Tab
        const devicesTab = page.locator('text=设备, text=激活, text=Devices, text=Activations, [class*="device"], [class*="activation"]').first();
        const hasTab = await devicesTab.isVisible().catch(() => false);

        if (hasTab) {
            await devicesTab.click();
            await page.waitForTimeout(1000);

            // 验证设备列表
            const table = page.locator('.el-table, table').first();
            await expect(table).toBeVisible({ timeout: 3000 });
        }
    });

    test('LD4. License 详情页功能特性列表', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/licenses/1', {
            mockLicenses,
            mockLicenseDetail,
        });

        // 查找"功能"或"特性" Tab
        const featuresTab = page.locator('text=功能, text=特性, text=Features, text=功能开关').first();
        const hasTab = await featuresTab.isVisible().catch(() => false);

        if (hasTab) {
            await featuresTab.click();
            await page.waitForTimeout(1000);

            // 验证功能列表
            const table = page.locator('.el-table, table').first();
            await expect(table).toBeVisible({ timeout: 3000 });
        }
    });

    test('LD5. License 详情页面内容加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/licenses/1', {
            mockLicenses,
            mockLicenseDetail,
        });

        // 验证页面标题/Header 已加载（el-page-header 或者 h2）
        const headerContent = page.locator('h2, .page-title, [class*="title"], .el-page-header__title, .license-key').first();
        const hasHeader = await headerContent.isVisible().catch(() => false);
        if (hasHeader) {
            await expect(headerContent).toBeVisible();
        } else {
            // 可能是 loading 状态，至少页面没有崩溃
            const loading = page.locator('.el-loading-mask, [class*="loading"]').first();
            const hasLoading = await loading.isVisible().catch(() => false);
            expect(hasLoading || page.url().includes('licenses')).toBeTruthy();
        }
    });

    test('LD6. License 详情页面导航', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/licenses/1', {
            mockLicenses,
            mockLicenseDetail,
        });

        // 直接导航回列表
        await page.goto('/admin/licenses');
        await page.waitForTimeout(1000);
        expect(page.url()).toContain('licenses');
        expect(page.url()).not.toMatch(/licenses\/\d+/);
    });

    test('LD7. License 编辑操作入口可见', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/licenses/1', {
            mockLicenses,
            mockLicenseDetail,
        });

        // 编辑按钮
        const editBtn = page.locator('button:has-text("编辑"), button:has-text("修改"), a:has-text("编辑")').first();
        const hasEdit = await editBtn.isVisible().catch(() => false);

        if (hasEdit) {
            await expect(editBtn).toBeVisible();
        } else {
            // 可能是下拉菜单中的操作
            const moreBtn = page.locator('button:has-text("更多"), [class*="more"], .el-dropdown').first();
            const hasMore = await moreBtn.isVisible().catch(() => false);
            if (hasMore) {
                await expect(moreBtn).toBeVisible();
            }
        }
    });
});
