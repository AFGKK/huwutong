import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn } from './helpers.js';

/**
 * 系统设置与诊断 E2E 测试
 */
test.describe.configure({ mode: 'serial' });

test.describe('系统设置 E2E', () => {

    test('S1. 系统设置页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/settings');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('S2. 系统健康/诊断页面', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/health');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });

    test('S3. 审计日志页面', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/audit-logs');

        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);
        await expect(page.locator('body')).not.toContainText('Loading');
    });
});
