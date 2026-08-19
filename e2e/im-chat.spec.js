import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn, getMockImConversations, adminUrl } from './helpers.js';

test.describe.configure({ mode: 'serial' });

const adminUser = {
    email: 'admin@huwutong.com',
    name: '管理员',
    roles: ['super-admin', 'admin'],
};

test.describe('IM 即时通讯', () => {

    test('1. IM 中心可加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/im', adminUser);

        await expect(page.locator('.im-center')).toBeVisible({ timeout: 15000 });
        await expect(page.locator('.queue-layout')).toHaveCount(0);
    });

    test('2. 客服工作台 Tab 已移除并回落到 AI 对话', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/im?tab=agentWorkspace', adminUser);

        await expect(page.locator('.im-center')).toBeVisible({ timeout: 15000 });
        await expect(page.locator('.queue-layout')).toHaveCount(0);
    });

    test.describe('私信页（大组件懒加载）', () => {
        test.setTimeout(120000);

        test('3. IM 聊天页面可加载', async ({ page }) => {
            await navigateAsLoggedIn(page, '/admin/user-chat', adminUser);
            await page.waitForLoadState('networkidle');

            await expect(page).toHaveURL(/user-chat/, { timeout: 15000 });
            await expect(page.locator('.user-chat-page, .chat-layout, .conversation-list').first()).toBeVisible({ timeout: 90000 });
        });

        test('4. 会话列表展示模拟数据', async ({ page }) => {
            await navigateAsLoggedIn(page, '/admin/user-chat', adminUser);
            await page.waitForLoadState('networkidle');

            const convList = page.locator('.conversation-list');
            await expect(convList).toBeVisible({ timeout: 90000 });

            const mockConvs = getMockImConversations();
            await expect(page.locator('.conv-name', { hasText: mockConvs[0].name })).toBeVisible({ timeout: 30000 });
        });
    });
});
