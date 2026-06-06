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
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command: 'php artisan serve --port=8000',
        url: 'http://localhost:8000',
        reuseExistingServer: !process.env.CI,
        timeout: 30000,
    },
});
