import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn } from './helpers.js';

/**
 * 产品与客户管理 E2E 测试
 */
test.describe.configure({ mode: 'serial' });

test.describe('产品管理 E2E', () => {

    test('P1. 产品列表页正常加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/products');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('P2. 产品详情页查看', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/products');

        const firstLink = page.locator('.el-table__row a, td a, a:has-text("产品"), a:has-text("Pro")').first();
        if (await firstLink.isVisible().catch(() => false)) {
            await firstLink.click();
            await page.waitForTimeout(2000);
            expect(page.url()).toMatch(/products\/\d+/);
        } else {
            test.skip(true, '产品列表为空，跳过详情页测试');
        }
    });

    test('P3. 产品功能列表展示', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/products');

        const firstLink = page.locator('.el-table__row a, td a').first();
        if (await firstLink.isVisible().catch(() => false)) {
            await firstLink.click();
            await page.waitForTimeout(2000);

            const featuresTab = page.locator('text=功能, text=特性, text=Features, [class*="feature"]').first();
            if (await featuresTab.isVisible().catch(() => false)) {
                await featuresTab.click();
                await page.waitForTimeout(1000);

                const featureList = page.locator('.el-table, table, [class*="feature-list"]').first();
                await expect(featureList).toBeVisible({ timeout: 3000 });
            }
        } else {
            test.skip(true, '产品列表为空，跳过功能测试');
        }
    });
});

test.describe('客户管理 E2E', () => {

    test('C1. 客户列表页正常加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/customers');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('C2. 客户详情页查看', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/customers');

        const firstLink = page.locator('.el-table__row a, td a').first();
        if (await firstLink.isVisible().catch(() => false)) {
            await firstLink.click();
            await page.waitForTimeout(2000);

            expect(page.url()).toMatch(/customers\/\d+/);

            const licenseTab = page.locator('text=License, text=许可证, [class*="license"]').first();
            if (await licenseTab.isVisible().catch(() => false)) {
                await licenseTab.click();
                await page.waitForTimeout(1000);

                const subTable = page.locator('.el-table, table').first();
                await expect(subTable).toBeVisible({ timeout: 3000 });
            }
        } else {
            test.skip(true, '客户列表为空，跳过详情页测试');
        }
    });

    test('C3. 客户搜索过滤功能', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/customers');

        const searchInput = page.locator('input[placeholder*="搜索"], input[placeholder*="查找"], .el-input input').first();
        if (await searchInput.isVisible().catch(() => false)) {
            await searchInput.fill('测试');
            await searchInput.press('Enter');
            await page.waitForTimeout(2000);

            const error = page.locator('text=错误, text=Error, text=出错了').first();
            await expect(error).not.toBeVisible({ timeout: 3000 });
        }
    });
});
