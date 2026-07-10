import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn, adminUrl } from './helpers.js';

test.describe.configure({ mode: 'serial' });

test.describe('电商下单流程', () => {

    test('1. 结算页可加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/build/checkout', {
            email: 'customer@test.com',
            name: '测试用户',
            roles: ['customer'],
        });

        await expect(page.locator('body')).not.toContainText('Loading', { timeout: 10000 });
    });

    test('2. 订单管理列表页可加载', async ({ page }) => {
        await navigateAsLoggedIn(page, '/admin/orders', {
            email: 'admin@huwutong.com',
            name: '管理员',
            roles: ['super-admin', 'admin'],
        });

        await expect(page.locator('body')).not.toContainText('Loading', { timeout: 10000 });
    });
});
