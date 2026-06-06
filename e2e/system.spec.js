import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn } from './helpers.js';

/**
 * 系统设置与诊断 E2E 测试
 */
test.describe.configure({ mode: 'serial' });

test.describe('系统设置 E2E', () => {

    test('S1. 系统设置页面加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/settings');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });

    test('S2. 系统健康/诊断页面', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/health');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        await expect(heading).toBeVisible({ timeout: 5000 });
    });

    test('S3. 审计日志页面', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/audit-logs');

        const heading = page.locator('h2, .page-title, [class*="title"]').first();
        const hasHeading = await heading.isVisible().catch(() => false);
        expect(hasHeading).toBeTruthy();
    });
});
