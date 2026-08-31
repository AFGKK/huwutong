import { test, expect } from '@playwright/test';

/**
 * T-18: H5 License 查询页 E2E
 *
 * 手机视口（375px）；有效/无效 Key；无横滚；激活引导/分享入口。
 */
test.describe.configure({ mode: 'serial' });

const MOBILE = { width: 375, height: 812 };

const mockFound = {
    success: true,
    found: true,
    data: {
        license_key: 'HWT-VALID-TEST-0001',
        product_name: '互物通专业版',
        product_description: 'E2E 测试产品描述',
        license_type_label: '商业授权',
        status: 'active',
        status_label: '有效',
        is_expired: false,
        activated: true,
        activated_devices: 1,
        max_devices: 5,
        created_at: '2026-01-01 10:00:00',
        expires_at: '2027-01-01 10:00:00',
    },
};

const mockNotFound = {
    success: true,
    found: false,
    message: '未找到该 License Key',
};

async function dismissCookieIfPresent(page) {
    // 先清本地同意状态，再直接调用关闭函数，避免 cookie 浮层挡住「接受全部」
    await page.evaluate(() => {
        try {
            localStorage.setItem('cookie_consent', 'accepted');
            localStorage.setItem('cookie_consent_given', JSON.stringify({
                action: 'accepted',
                categories: ['functional', 'analytics', 'marketing'],
                ts: Date.now(),
            }));
        } catch (e) { /* ignore */ }
        if (typeof window.acceptCookieConsent === 'function') {
            window.acceptCookieConsent();
        }
        const banner = document.getElementById('cookie-banner');
        if (banner) banner.style.display = 'none';
    });
}

test.describe('T-18 License 查询页 H5', () => {
    test.use({ viewport: MOBILE });

    test('Q1. 375px 页面加载且无横向滚动', async ({ page }) => {
        await page.goto('/license/query', { waitUntil: 'domcontentloaded' });
        await dismissCookieIfPresent(page);

        await expect(page).toHaveTitle(/授权查询/);
        await expect(page.locator('#licenseKey')).toBeVisible();
        await expect(page.locator('#searchBtn')).toBeVisible();
        await expect(page.locator('text=试试示例 Key')).toBeVisible();

        const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
        expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);
    });

    test('Q2. 空 Key 显示字段错误', async ({ page }) => {
        await page.goto('/license/query', { waitUntil: 'domcontentloaded' });
        await dismissCookieIfPresent(page);

        await page.locator('#licenseKey').fill('');
        await page.locator('#searchBtn').click();

        const err = page.locator('#inputError');
        await expect(err).toBeVisible({ timeout: 3000 });
        await expect(err).toContainText(/请输入/);
    });

    test('Q3. 无效 Key 显示未找到', async ({ page }) => {
        await page.route('**/api/license/public-lookup', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(mockNotFound),
            });
        });

        await page.goto('/license/query', { waitUntil: 'domcontentloaded' });
        await dismissCookieIfPresent(page);

        await page.locator('#licenseKey').fill('HWT-INVALID-XXXX');
        await page.locator('#searchBtn').click();

        await expect(page.locator('#notfound')).toBeVisible({ timeout: 8000 });
        await expect(page.locator('#result')).toBeHidden();
    });

    test('Q4. 有效 Key 展示结果、状态与分享', async ({ page }) => {
        await page.route('**/api/license/public-lookup', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(mockFound),
            });
        });

        await page.goto('/license/query', { waitUntil: 'domcontentloaded' });
        await dismissCookieIfPresent(page);

        await page.locator('#licenseKey').fill('HWT-VALID-TEST-0001');
        await page.locator('#searchBtn').click();

        await expect(page.locator('#result')).toBeVisible({ timeout: 8000 });
        await expect(page.locator('#resultKey')).toContainText('HWT-VALID-TEST-0001');
        await expect(page.locator('#resultProduct')).toContainText('互物通专业版');
        await expect(page.locator('#statusBadge')).toBeVisible();
        await expect(page.locator('#shareBtn')).toBeVisible();
        await expect(page.locator('#activationGuide')).toBeVisible();
        await expect(page.locator('#guideActive')).toBeVisible();

        // 仍无横滚
        const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
        expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);
    });

    test('Q5. 示例 Key 可填入并查询', async ({ page }) => {
        await page.route('**/api/license/public-lookup', async (route) => {
            const post = route.request().postDataJSON() || {};
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    ...mockFound,
                    data: { ...mockFound.data, license_key: post.license_key || mockFound.data.license_key },
                }),
            });
        });

        await page.goto('/license/query', { waitUntil: 'domcontentloaded' });
        await dismissCookieIfPresent(page);

        await page.locator('button:has-text("HWT-DEMO")').click();
        await expect(page.locator('#licenseKey')).toHaveValue(/HWT-DEMO/);

        // fillExample 会触发 doSearch
        await expect(page.locator('#result')).toBeVisible({ timeout: 8000 });
    });

    test('Q6. 网络失败显示错误与重试', async ({ page }) => {
        await page.route('**/api/license/public-lookup', async (route) => {
            await route.abort('failed');
        });

        await page.goto('/license/query', { waitUntil: 'domcontentloaded' });
        await dismissCookieIfPresent(page);

        await page.locator('#licenseKey').fill('HWT-NET-FAIL');
        await page.locator('#searchBtn').click();

        await expect(page.locator('#error')).toBeVisible({ timeout: 8000 });
        await expect(page.locator('#errorMessage')).toContainText(/网络/);
        await expect(page.locator('#error button:has-text("重试")')).toBeVisible();
    });
});
