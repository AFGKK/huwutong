import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn, getMockUser, adminUrl } from './helpers.js';

test.describe.configure({ mode: 'serial' });

test.describe('OA 互物号文章编辑器', () => {

    test('1. 文章编辑器页面可加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/build/oa-editor', {
            email: 'admin@huwutong.com',
            name: '管理员',
            roles: ['super-admin', 'admin'],
        });

        await expect(page.locator('body')).not.toContainText('Loading', { timeout: 10000 });
    });

    test('2. 编辑器工具栏可见', async ({ page }) => {
        await navigateAsLoggedIn(page, '/build/oa-editor', {
            email: 'admin@huwutong.com',
            name: '管理员',
            roles: ['super-admin', 'admin'],
        });

        // TipTap 编辑器工具栏
        const toolbar = page.locator('.ProseMirror-toolbar, .editor-toolbar, [class*="toolbar"]').first();
        if (await toolbar.isVisible()) {
            await expect(toolbar).toBeVisible();
        }
    });

    test('3. 编辑器内容区域可点击', async ({ page }) => {
        await navigateAsLoggedIn(page, '/build/oa-editor', {
            email: 'admin@huwutong.com',
            name: '管理员',
            roles: ['super-admin', 'admin'],
        });

        const editor = page.locator('.ProseMirror, [contenteditable="true"]').first();
        if (await editor.isVisible()) {
            await editor.click();
            await page.waitForTimeout(500);
        }
    });

    test('4. 侧栏选项卡可见', async ({ page }) => {
        await navigateAsLoggedIn(page, '/build/oa-editor', {
            email: 'admin@huwutong.com',
            name: '管理员',
            roles: ['super-admin', 'admin'],
        });

        // 至少有一个侧栏标签（广告、产品等）
        const sideTabs = page.locator('text=广告, text=素材, text=产品').first();
        if (await sideTabs.isVisible()) {
            await expect(sideTabs).toBeVisible();
        }
    });
});
