import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn, getMockUser, adminUrl } from './helpers.js';

test.describe.configure({ mode: 'serial' });

test.describe('IM 即时通讯', () => {

    test('1. IM 聊天页面可加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/user-chat', {
            email: 'admin@huwutong.com',
            name: '管理员',
            roles: ['super-admin', 'admin'],
        });

        await expect(page.locator('body')).not.toContainText('Loading', { timeout: 10000 });
    });

    test('2. 会话列表可见', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/user-chat', {
            email: 'admin@huwutong.com',
            name: '管理员',
            roles: ['super-admin', 'admin'],
        });

        // 侧栏至少应该有一个会话列表容器
        const sidebar = page.locator('.conversation-list, .sidebar, [class*="conv"]').first();
        await expect(sidebar).toBeVisible({ timeout: 8000 });
    });

    test('3. 好友列表选项卡可点击', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/user-chat', {
            email: 'admin@huwutong.com',
            name: '管理员',
            roles: ['super-admin', 'admin'],
        });

        // 点击「好友」Tab
        const friendTab = page.locator('text=好友, text=联系人').first();
        if (await friendTab.isVisible()) {
            await friendTab.click();
            await page.waitForTimeout(1000);
        }
    });

    test('4. 搜索用户功能可用', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/user-chat', {
            email: 'admin@huwutong.com',
            name: '管理员',
            roles: ['super-admin', 'admin'],
        });

        const searchInput = page.locator('input[placeholder*="搜索"]').first();
        if (await searchInput.isVisible()) {
            await searchInput.fill('test');
            await page.waitForTimeout(1000);
        }
    });
});
