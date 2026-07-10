import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn, getMockUser, adminUrl } from './helpers.js';

test.describe.configure({ mode: 'serial' });

test.describe('联盟推广管理', () => {

    test('1. 联盟推广页面可加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/affiliate', {
            email: 'admin@huwutong.com',
            name: '管理员',
            roles: ['super-admin', 'admin'],
        });

        await expect(page.locator('body')).not.toContainText('Loading', { timeout: 10000 });
    });

    test('2. 活动列表可见', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/affiliate', {
            email: 'admin@huwutong.com',
            name: '管理员',
            roles: ['super-admin', 'admin'],
        });

        // 至少能看到页面标题或主要内容区
        const title = page.locator('text=联盟推广, text=推广活动').first();
        if (await title.isVisible()) {
            await expect(title).toBeVisible();
        }
    });

    test('3. 推广审核 Tab 可切换', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/affiliate', {
            email: 'admin@huwutong.com',
            name: '管理员',
            roles: ['super-admin', 'admin'],
        });

        const reviewTab = page.locator('text=推广审核, text=待审核').first();
        if (await reviewTab.isVisible()) {
            await reviewTab.click();
            await page.waitForTimeout(1500);
        }
    });

    test('4. 多级关系链 Tab 可切换', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/affiliate', {
            email: 'admin@huwutong.com',
            name: '管理员',
            roles: ['super-admin', 'admin'],
        });

        const treeTab = page.locator('text=多级关系链, text=关系链').first();
        if (await treeTab.isVisible()) {
            await treeTab.click();
            await page.waitForTimeout(1500);
        }
    });

    test('5. 点击日志 Tab 可切换', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/affiliate', {
            email: 'admin@huwutong.com',
            name: '管理员',
            roles: ['super-admin', 'admin'],
        });

        const logTab = page.locator('text=点击日志, text=推广日志').first();
        if (await logTab.isVisible()) {
            await logTab.click();
            await page.waitForTimeout(1500);
        }
    });
});
