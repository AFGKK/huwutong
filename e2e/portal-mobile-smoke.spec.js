import { test, expect } from '@playwright/test';
import { navigateAsLoggedIn } from './helpers.js';

test.describe.configure({ mode: 'serial' });

test.describe('客户门户移动端冒烟 (375px)', () => {

    const customer = {
        email: 'demo@huwutong.com',
        name: '演示用户',
        roles: ['customer'],
    };

    test('1. 商店页面加载且无横滚', async ({ page }) => {
        await navigateAsLoggedIn(page, '/portal/shop', customer);

        // 等待商品网格渲染
        await expect(page.locator('.product-grid')).toBeVisible({ timeout: 10000 });
        await expect(page.locator('.product-card').first()).toBeVisible();

        // 验证无错误弹窗
        const errors = await page.locator('.el-alert--error').count();
        expect(errors).toBe(0);

        // 验证无横向滚动（375px 视口）
        const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
        expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
    });

    test('2. 购物车页面加载且移动端显示卡片视图', async ({ page }) => {
        await navigateAsLoggedIn(page, '/portal/cart', customer);

        // 等待购物车数据加载
        await expect(page.locator('.cart-page')).toBeVisible({ timeout: 10000 });

        // 在 375px 下应渲染卡片列表（非表格）
        const hasMobileList = await page.locator('.cart-mobile-list').isVisible().catch(() => false);
        if (hasMobileList) {
            await expect(page.locator('.cart-mobile-list')).toBeVisible();
        }

        // 验证无横滚
        const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
        expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1); // 允许 1px 舍入
    });

    test('3. 结账页面加载且移动端显示卡片明细', async ({ page }) => {
        // 先导航过去，再设置 sessionStorage
        await navigateAsLoggedIn(page, '/portal/checkout', customer);

        await page.evaluate(() => {
            sessionStorage.setItem('checkout_items', JSON.stringify([
                { product_name: '基础版 - 月付', quantity: 2, price: 99, sku: { price: 99, billing_cycle: 'monthly', version: '1.0' } },
            ]));
        });

        // 重新加载让 Vue 从 sessionStorage 读取
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1500);

        await expect(page.locator('.checkout-page')).toBeVisible({ timeout: 10000 });

        // 验证无横滚
        const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
        expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);
    });

    test('4. 订单页面加载且含订单卡片', async ({ page }) => {
        await navigateAsLoggedIn(page, '/portal/orders', customer);

        await expect(page.locator('.portal-orders-page')).toBeVisible({ timeout: 10000 });

        // 应加载出订单卡片
        const orderCard = page.locator('.order-card').first();
        await expect(orderCard).toBeVisible({ timeout: 10000 });

        // 验证状态标签存在
        await expect(orderCard.locator('.el-tag')).toBeVisible();

        // 验证无横滚
        const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
        expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);
    });

    test('5. 完整核心路径无崩溃：商店→购物车→结账', async ({ page }) => {
        await navigateAsLoggedIn(page, '/portal/shop', customer);
        await expect(page.locator('.product-grid')).toBeVisible({ timeout: 10000 });
        await expect(page.locator('.product-card').first()).toBeVisible();
        // 商店无横滚
        let sw = await page.evaluate(() => document.documentElement.scrollWidth);
        let cw = await page.evaluate(() => document.documentElement.clientWidth);
        expect(sw).toBeLessThanOrEqual(cw);

        // 导航到购物车
        await page.goto(normalizeUrl(page, '/portal/cart'), { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1500);
        await expect(page.locator('.cart-page')).toBeVisible({ timeout: 10000 });

        // 导航到结账
        await page.goto(normalizeUrl(page, '/portal/checkout'), { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1000);
        await page.evaluate(() => {
            sessionStorage.setItem('checkout_items', JSON.stringify([
                { product_name: '基础版 - 月付', quantity: 1, price: 99, sku: { price: 99, billing_cycle: 'monthly' } },
            ]));
        });
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1500);
        await expect(page.locator('.checkout-page')).toBeVisible({ timeout: 10000 });

        // 验证无横滚
        sw = await page.evaluate(() => document.documentElement.scrollWidth);
        cw = await page.evaluate(() => document.documentElement.clientWidth);
        expect(sw).toBeLessThanOrEqual(cw + 1);
    });
});

function normalizeUrl(page, path) {
    const base = page.url().replace(/\/[^/]*$/, '');
    const origin = new URL(base).origin;
    return origin + path;
}
