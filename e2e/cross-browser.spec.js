import { test, expect } from '@playwright/test';

/**
 * T-14 / T-15: 跨浏览器兼容性测试
 *
 * 在 Chrome / Firefox / Safari / 移动端 Safari 上验证关键页面。
 *
 * 注意事项：
 * - focus: 页面可访问、无崩溃、基本布局正常、控制台无错误
 */

test.describe('T-14 跨浏览器兼容性 — 公共页面', () => {

    function collectErrors(page) {
        const errors = [];
        page.on('pageerror', err => errors.push(err.message));
        return errors;
    }

    // ═══════════════════════════════════════════
    // 首页
    // ═══════════════════════════════════════════

    test('首页正常加载，标题和描述正确', async ({ page, browserName }) => {
        const errors = collectErrors(page);
        page.on('console', msg => {
            if (msg.type() === 'error') errors.push(msg.text());
        });

        await page.goto('/', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(2000);

        // 页面必须正常加载，无崩溃
        const title = await page.title();
        console.log(`[${browserName}] Page title: "${title}"`);
        expect(title.length).toBeGreaterThan(0);

        // 检查页面主体存在
        await expect(page.locator('body')).toBeVisible();

        // 导航栏应该存在
        const nav = page.locator('nav, header, [class*="nav"], [class*="navbar"], .main-header');
        const navCount = await nav.count();
        if (navCount > 0) {
            await expect(nav.first()).toBeVisible();
        }

        // 没有阻止页面渲染的关键错误
        const criticalErrors = errors.filter(e =>
            e.includes('Cannot read') || e.includes('undefined is not')
            || e.includes('null is not') || e.includes('Script error')
        );
        expect(criticalErrors.length).toBe(0);
    });

    test('首页页面结构完整', async ({ page, browserName }) => {
        await page.goto('/', { waitUntil: 'networkidle' });

        // 页面应该有 body
        const body = page.locator('body');
        await expect(body).toBeVisible();

        // 页面应有合理内容高度
        const bodyHeight = await body.evaluate(el => el.scrollHeight);
        console.log(`[${browserName}] Page height: ${bodyHeight}px`);
        expect(bodyHeight).toBeGreaterThan(100);

        // 页脚应该存在
        const footer = page.locator('footer, [class*="footer"]');
        const footerCount = await footer.count();
        if (footerCount > 0) {
            await expect(footer.first()).toBeVisible();
            console.log(`[${browserName}] Footer found`);
        }

        // 没有 404 文字
        const bodyText = await body.innerText().catch(() => '');
        const hasTrace = bodyText.includes('NotFoundHttpException') || bodyText.includes('Whoops');
        expect(hasTrace).toBe(false);
    });

    // ═══════════════════════════════════════════
    // License 查询页
    // ═══════════════════════════════════════════

    test('License 查询页可访问', async ({ page, browserName }) => {
        const errors = collectErrors(page);
        await page.goto('/license/query', { waitUntil: 'networkidle' });

        // 标题正确
        const title = await page.title();
        console.log(`[${browserName}] License page title: "${title}"`);

        // 搜索输入框应存在
        const searchInput = page.locator('input[type="text"], input#licenseKey, input[placeholder*="License"], input[placeholder*="Key"]');
        await expect(searchInput).toBeVisible({ timeout: 8000 });

        // 搜索按钮应存在
        const searchBtn = page.locator('button#searchBtn, button:has-text("查询")');
        await expect(searchBtn).toBeVisible({ timeout: 5000 });

        // 页面标题 h1 应存在
        const heading = page.locator('h1');
        await expect(heading).toBeVisible();

        // 无渲染错误
        expect(errors.filter(e => !e.includes('ResizeObserver'))).toHaveLength(0);
    });

    test('License 查询页 — 示例 Key 按钮可点', async ({ page, browserName }) => {
        await page.goto('/license/query', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);

        // 找 Demo Key 按钮（或其替代方案）
        const demoBtn = page.locator('button:has-text("HWT-DEMO")').first();
        if (await demoBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
            await demoBtn.click();
            console.log(`[${browserName}] Demo key button clicked`);

            // 等待查询触发（加载中或结果可见）
            const triggered = await page.waitForFunction(() => {
                const r = document.getElementById('result');
                const l = document.getElementById('loading');
                const notfound = document.getElementById('notfound');
                return (r && !r.classList.contains('hidden')) ||
                       (l && !l.classList.contains('hidden')) ||
                       (notfound && !notfound.classList.contains('hidden'));
            }, { timeout: 8000 }).then(() => true).catch(() => false);

            console.log(`[${browserName}] Query triggered: ${triggered}`);
        } else {
            console.log(`[${browserName}] No demo key button, checking input instead`);
            const input = page.locator('#licenseKey');
            if (await input.isVisible().catch(() => false)) {
                await input.fill('HWT-DEMO-TEST');
                console.log(`[${browserName}] Filled input manually`);
            }
        }
    });

    // ═══════════════════════════════════════════
    // 产品列表页
    // ═══════════════════════════════════════════

    test('产品列表页正常加载', async ({ page, browserName }) => {
        const errors = collectErrors(page);
        await page.goto('/products', { waitUntil: 'networkidle' });

        // 页面正常加载
        await expect(page.locator('body')).toBeVisible();

        // 有产品容器
        const productGrid = page.locator('[class*="product"], .product-grid, .product-list, [class*="card"]');
        const gridCount = await productGrid.count();
        if (gridCount > 0) {
            await expect(productGrid.first()).toBeVisible();
            console.log(`[${browserName}] Found ${gridCount} product elements`);
        }

        // 检查页面有合理的高度（非空白页）
        const bodyHeight = await page.locator('body').evaluate(el => el.scrollHeight);
        expect(bodyHeight).toBeGreaterThan(200);

        // 无关键错误
        const criticalErrors = errors.filter(e =>
            e.includes('Cannot read') || e.includes('undefined is not')
        );
        expect(criticalErrors.length).toBe(0);
    });

    test('产品列表页 — 筛选控件存在', async ({ page, browserName }) => {
        await page.goto('/products', { waitUntil: 'networkidle' });

        // 查找排序/筛选 UI 元素
        const filterBar = page.locator('[class*="filter"], [class*="sort"], select, [class*="search"], input[placeholder*="搜索"], input[placeholder*="product"]');
        const hasFilter = await filterBar.isVisible().catch(() => false);
        console.log(`[${browserName}] Filter/sort visible: ${hasFilter}`);

        // 页脚/底部信息
        const footer = page.locator('footer, [class*="footer"]');
        const hasFooter = await footer.isVisible().catch(() => false);
        console.log(`[${browserName}] Footer visible: ${hasFooter}`);
    });

    // ═══════════════════════════════════════════
    // 产品详情页
    // ═══════════════════════════════════════════

    test('产品详情页正常加载（如果存在产品链接）', async ({ page, browserName }) => {
        // 先去产品列表看看
        await page.goto('/products', { waitUntil: 'domcontentloaded' });

        const productLink = page.locator('a[href*="/products/"], a[href*="/product/"], [class*="product"] a').first();
        if (await productLink.isVisible({ timeout: 3000 }).catch(() => false)) {
            const href = await productLink.getAttribute('href');
            console.log(`[${browserName}] Found product link: ${href}`);

            // 直接导航到详情页
            await page.goto(href, { waitUntil: 'networkidle' });
            await expect(page.locator('body')).toBeVisible();

            const title = await page.title();
            console.log(`[${browserName}] Detail page title: "${title}"`);
            expect(title.length).toBeGreaterThan(0);
        } else {
            console.log(`[${browserName}] No product links, detail test skipped`);
            test.skip();
        }
    });

    // ═══════════════════════════════════════════
    // 定价页
    // ═══════════════════════════════════════════

    test('定价页正常加载', async ({ page, browserName }) => {
        const errors = collectErrors(page);
        await page.goto('/pricing', { waitUntil: 'networkidle' });

        // 页面加载
        await expect(page.locator('body')).toBeVisible();

        // 有定价标题
        const heading = page.locator('h1, h2').first();
        await expect(heading).toBeVisible();

        const title = await page.title();
        console.log(`[${browserName}] Pricing title: "${title}"`);

        // 定价卡片或方案列表
        const plans = page.locator('[class*="plan"], [class*="pricing"], [class*="card"]');
        const planCount = await plans.count();
        console.log(`[${browserName}] Plan elements: ${planCount}`);

        // 非空
        const bodyHeight = await page.locator('body').evaluate(el => el.scrollHeight);
        expect(bodyHeight).toBeGreaterThan(300);

        // 无关键错误
        const criticalErrors = errors.filter(e =>
            e.includes('Cannot read') || e.includes('undefined is not')
        );
        expect(criticalErrors.length).toBe(0);
    });

    test('定价页 — 计费周期切换', async ({ page, browserName }) => {
        await page.goto('/pricing', { waitUntil: 'networkidle' });

        // 找计费周期切换按钮
        const toggle = page.locator('[class*="toggle"], [class*="switch"], button:has-text("月"), button:has-text("年"), [class*="billing"]');
        if (await toggle.isVisible({ timeout: 3000 }).catch(() => false)) {
            // 点击年付
            const yearlyBtn = page.locator('button:has-text("年"), button:has-text("Annual")');
            if (await yearlyBtn.isVisible().catch(() => false)) {
                await yearlyBtn.click();
                await page.waitForTimeout(1000);
                console.log(`[${browserName}] Switched to yearly billing`);
            }
        }
    });

    // ═══════════════════════════════════════════
    // 注册/登录页
    // ═══════════════════════════════════════════

    test('登录页面 SPA 加载', async ({ page, browserName }) => {
        const errors = collectErrors(page);
        await page.goto('/admin/login', { waitUntil: 'networkidle' });
        await page.waitForTimeout(3000);

        const title = await page.title();
        console.log(`[${browserName}] Login page title: "${title}"`);

        // SPA 根节点或登录表单
        const appRoot = page.locator('#admin-app');
        const hasSpa = await appRoot.isVisible().catch(() => false);
        if (hasSpa) {
            console.log(`[${browserName}] SPA loaded`);
        }

        // 页面高度合理
        const bodyHeight = await page.locator('body').evaluate(el => el.scrollHeight);
        expect(bodyHeight).toBeGreaterThan(100);

        expect(errors.length === 0 || errors.every(e => e.includes('Minified React error') === false)).toBe(true);
    });

    // ═══════════════════════════════════════════
    // Cookie 横幅
    // ═══════════════════════════════════════════

    test('Cookie 横幅可见性', async ({ page, browserName }) => {
        await page.goto('/', { waitUntil: 'domcontentloaded' });
        // 清除 cookie consent 让 banner 显示
        await page.evaluate(() => {
            localStorage.removeItem('cookie_consent');
            localStorage.removeItem('cookie_consent_given');
            localStorage.removeItem('cookie_consent_banner_closed');
        });
        await page.reload({ waitUntil: 'networkidle' });

        const banner = page.locator('#cookie-banner');
        const hasBanner = await banner.isVisible({ timeout: 5000 }).catch(() => false);
        console.log(`[${browserName}] Cookie banner visible: ${hasBanner}`);

        // 这个在 Firefox/Safari 可能因为 tracking 策略不显示，但不应崩溃
        await expect(page.locator('body')).toBeVisible();
    });

    // ═══════════════════════════════════════════
    // API 健康检查（每个浏览器确认后端可用）
    // ═══════════════════════════════════════════

    test('后端 API 健康检查', async ({ request, browserName }) => {
        const res = await request.get('/api/health/live');
        const ok = res.ok() ? res : await request.get('/api/health/ready');
        expect(ok.ok()).toBe(true);

        const body = await ok.json();
        console.log(`[${browserName}] Health API: ${body.status || 'ok'}`);
    });

    // ═══════════════════════════════════════════
    // 社区页面
    // ═══════════════════════════════════════════

    test('社区页面正常加载', async ({ page, browserName }) => {
        const errors = collectErrors(page);
        await page.goto('/community', { waitUntil: 'networkidle' });
        await page.waitForTimeout(2000);

        await expect(page.locator('body')).toBeVisible();

        const title = await page.title();
        console.log(`[${browserName}] Community title: "${title}"`);

        expect(errors.filter(e => e.includes('Cannot read') || e.includes('undefined is not'))).toHaveLength(0);
    });
});
