import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: [
        ['html', { outputFolder: 'e2e-report' }],
        ['list'],
    ],
    use: {
        baseURL: process.env.BASE_URL || 'http://localhost:8000',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        // 避免 SW 将 /api/ 请求绕过 Playwright route mock
        serviceWorkers: 'block',
    },
    projects: [
        // ── 桌面浏览器 ──
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'firefox',
            use: { ...devices['Desktop Firefox'] },
        },
        {
            name: 'webkit',
            use: { ...devices['Desktop Safari'] },
        },
        // ── 移动浏览器 ──
        {
            name: 'mobile-chrome',
            use: {
                ...devices['Pixel 5'],
                viewport: { width: 375, height: 812 },
            },
        },
        {
            name: 'mobile-safari',
            use: {
                ...devices['iPhone 13'],
                viewport: { width: 390, height: 844 },
            },
        },
        // ── 平板浏览器 ──
        {
            name: 'tablet-chrome',
            use: {
                ...devices['iPad (gen 7)'],
                browserName: 'chromium',
            },
        },
        {
            name: 'tablet-safari',
            use: {
                ...devices['iPad (gen 7)'],
                browserName: 'webkit',
            },
        },
    ],
    webServer: {
        command: 'php artisan serve --port=8000',
        url: 'http://localhost:8000',
        reuseExistingServer: !process.env.CI,
        timeout: 30000,
    },
});
