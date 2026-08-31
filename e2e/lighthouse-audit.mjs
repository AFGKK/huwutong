/**
 * Lighthouse 性能审计脚本 (ESM version)
 *
 * 用法:
 *   node e2e/lighthouse-audit.mjs                    # 审计所有页面
 *   node e2e/lighthouse-audit.mjs --page=home         # 仅审计首页
 *   node e2e/lighthouse-audit.mjs --output=json       # JSON 格式输出
 */

import lighthouse from 'lighthouse';
import * as fs from 'fs';
import * as path from 'path';
import { fileURLToPath } from 'url';
import { createRequire } from 'module';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(import.meta.url);

// 使用 lighthouse 内置的 chrome launcher
import * as chromeLauncher from 'chrome-launcher';

const BASE_URL = process.env.BASE_URL || 'http://localhost:8000';
const OUTPUT_MODE = process.argv.includes('--output=json') ? 'json' : 'html';
const PAGE_FILTER = process.argv.find(a => a.startsWith('--page='))?.split('=')[1];

const PAGES = [
    { name: 'home',     path: '/',                        label: '首页' },
    { name: 'license',  path: '/license/query',           label: 'License 查询页' },
    { name: 'products', path: '/products',                 label: '产品列表页' },
    { name: 'pricing',  path: '/pricing',                  label: '定价页' },
    { name: 'login',    path: '/admin/login',              label: '管理后台登录' },
    { name: 'community',path: '/community',                label: '社区' },
];

const DESKTOP_CONFIG = {
    extends: 'lighthouse:default',
    settings: {
        formFactor: 'desktop',
        throttling: {
            rttMs: 40,
            throughputKbps: 10240,
            cpuSlowdownMultiplier: 1,
        },
        screenEmulation: {
            mobile: false,
            width: 1350,
            height: 940,
            deviceScaleFactor: 1,
            disabled: false,
        },
        onlyCategories: ['performance', 'accessibility', 'best-practices', 'seo'],
    },
};

const MOBILE_CONFIG = {
    extends: 'lighthouse:default',
    settings: {
        formFactor: 'mobile',
        throttling: {
            rttMs: 150,
            throughputKbps: 1638.4,
            cpuSlowdownMultiplier: 4,
        },
        screenEmulation: {
            mobile: true,
            width: 375,
            height: 812,
            deviceScaleFactor: 2,
            disabled: false,
        },
        onlyCategories: ['performance', 'accessibility', 'best-practices', 'seo'],
    },
};

function pad(s, n = 20) { return s.padEnd(n); }

async function runLighthouse(url, config, { name, label }) {
    console.log(`\n  ═══════════════════════════════════════`);
    console.log(`  审计: ${label}`);
    console.log(`  URL:  ${url}`);
    console.log(`  模式: ${config.settings.formFactor}`);
    console.log(`  ═══════════════════════════════════════\n`);

    const chrome = await chromeLauncher.launch({
        chromeFlags: ['--headless', '--no-sandbox', '--disable-setuid-sandbox'],
    });

    try {
        const result = await lighthouse(url, {
            port: chrome.port,
            output: OUTPUT_MODE,
            logLevel: 'warn',
        }, config);

        if (!result) {
            console.log('  ⚠️  Lighthouse 未返回结果');
            return { name, label, scores: {}, metrics: {} };
        }

        const report = result.report;
        const lhr = result.lhr;

        const scores = {
            performance: lhr.categories.performance?.score ?? 0,
            accessibility: lhr.categories.accessibility?.score ?? 0,
            'best-practices': lhr.categories['best-practices']?.score ?? 0,
            seo: lhr.categories.seo?.score ?? 0,
        };

        console.log(`  ┌──────────────────────┬──────┐`);
        console.log(`  │ 指标                 │ 分数 │`);
        console.log(`  ├──────────────────────┼──────┤`);
        for (const [key, score] of Object.entries(scores)) {
            const bar = score >= 0.9 ? '🟢' : score >= 0.5 ? '🟡' : '🔴';
            console.log(`  │ ${pad(key)}│ ${bar} ${(score * 100).toFixed(0).padStart(2)}  │`);
        }
        console.log(`  └──────────────────────┴──────┘\n`);

        // 关键性能指标
        const metrics = lhr.audits;
        const keyMetrics = {
            'FCP (首次内容绘制)': metrics['first-contentful-paint']?.numericValue,
            'LCP (最大内容绘制)': metrics['largest-contentful-paint']?.numericValue,
            'TBT (总阻塞时间)':   metrics['total-blocking-time']?.numericValue,
            'CLS (累计布局偏移)': metrics['cumulative-layout-shift']?.numericValue,
            'SI (速度指数)':      metrics['speed-index']?.numericValue,
            'TTI (可交互时间)':   metrics['interactive']?.numericValue,
        };

        console.log(`  ┌──────────────────────┬──────────────────┐`);
        console.log(`  │ 指标                 │ 值               │`);
        console.log(`  ├──────────────────────┼──────────────────┤`);
        for (const [key, val] of Object.entries(keyMetrics)) {
            if (val !== undefined) {
                const fmt = key.includes('CLS') ? val.toFixed(3) : (val / 1000).toFixed(2) + 's';
                console.log(`  │ ${pad(key)}│ ${fmt.padStart(16)} │`);
            }
        }
        console.log(`  └──────────────────────┴──────────────────┘\n`);

        // 诊断建议
        const diagnostics = [];
        const diagAudits = [
            'render-blocking-resources', 'uses-responsive-images',
            'offscreen-images', 'unminified-css', 'unminified-javascript',
            'uses-text-compression', 'uses-optimized-images', 'uses-rel-preconnect',
            'uses-rel-preload', 'server-response-time', 'redirects',
            'dom-size', 'no-document-write', 'image-aspect-ratio',
            'deprecations', 'errors-in-console', 'unused-javascript',
            'unused-css-rules', 'modern-image-formats',
        ];

        for (const auditId of diagAudits) {
            const audit = metrics[auditId];
            if (audit && audit.score !== null && audit.score < 1) {
                const severity = audit.score >= 0.9 ? '   🟡' : '   🔴';
                diagnostics.push(`${severity} ${audit.title}: ${audit.displayValue || '要优化'}`);
                if (audit.details?.items?.length > 0) {
                    const topItems = audit.details.items.slice(0, 2);
                    for (const item of topItems) {
                        const desc = item.url || item.source || item.description || '';
                        if (desc && desc.length > 10) {
                            diagnostics.push(`      └ ${desc.length > 80 ? desc.slice(0, 80) + '...' : desc}`);
                        }
                    }
                }
            }
        }

        if (diagnostics.length > 0) {
            console.log(`  诊断建议:\n`);
            for (const d of diagnostics) console.log(d);
            console.log();
        }

        // 保存报告
        const reportDir = path.join(__dirname, '..', 'e2e-report', 'lighthouse');
        fs.mkdirSync(reportDir, { recursive: true });

        const mode = config.settings.formFactor;
        const ext = OUTPUT_MODE === 'json' ? 'json' : 'html';
        const reportFile = path.join(reportDir, `${name}-${mode}.${ext}`);
        fs.writeFileSync(reportFile, report);
        console.log(`  报告已保存: ${reportFile}\n`);

        return { name, label, scores, metrics: keyMetrics, diagnostics: diagnostics.length };
    } finally {
        await chrome.kill();
    }
}

async function main() {
    const pages = PAGE_FILTER
        ? PAGES.filter(p => p.name === PAGE_FILTER)
        : PAGES;

    if (pages.length === 0) {
        console.error(`未找到匹配的页面: ${PAGE_FILTER}`);
        console.error(`可用页面: ${PAGES.map(p => p.name).join(', ')}`);
        process.exit(1);
    }

    console.log(`\n══════════════════════════════════════════════════`);
    console.log(`  互物通 — Lighthouse 性能审计 (T-20)`);
    console.log(`  ${new Date().toISOString()}`);
    console.log(`══════════════════════════════════════════════════\n`);

    const allResults = [];

    for (const page of pages) {
        const url = `${BASE_URL}${page.path}`;

        console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
        console.log(`  桌面端 — ${page.label}`);
        console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
        const desktopResult = await runLighthouse(url, DESKTOP_CONFIG, { ...page, label: `${page.label} (桌面)` });

        console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
        console.log(`  移动端 — ${page.label}`);
        console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
        const mobileResult = await runLighthouse(url, MOBILE_CONFIG, { ...page, label: `${page.label} (移动)` });

        allResults.push(desktopResult, mobileResult);
    }

    // 总结表
    console.log(`\n══════════════════════════════════════════════════`);
    console.log(`  审计总结`);
    console.log(`══════════════════════════════════════════════════\n`);

    // 根据分数排序
    allResults.sort((a, b) => (a.scores.performance || 0) - (b.scores.performance || 0));

    console.log(`  ┌───────────────────────────────────────┬────────┬────────┬────────┬──────┐`);
    console.log(`  │ 页面                                  │ 性能   │ 可访问 │ 最佳   │ SEO  │`);
    console.log(`  ├───────────────────────────────────────┼────────┼────────┼────────┼──────┤`);
    for (const r of allResults) {
        const perf = r.scores.performance !== undefined ? (r.scores.performance * 100).toFixed(0) : 'N/A';
        const acc = r.scores.accessibility !== undefined ? (r.scores.accessibility * 100).toFixed(0) : 'N/A';
        const bp = r.scores['best-practices'] !== undefined ? (r.scores['best-practices'] * 100).toFixed(0) : 'N/A';
        const seo = r.scores.seo !== undefined ? (r.scores.seo * 100).toFixed(0) : 'N/A';
        const label = `${r.label}`;
        console.log(`  │ ${pad(label, 37)}│ ${perf.padStart(4)}  │ ${acc.padStart(4)}  │ ${bp.padStart(4)}  │ ${seo.padStart(3)} │`);
    }
    console.log(`  └───────────────────────────────────────┴────────┴────────┴────────┴──────┘\n`);

    // 优化优先级
    console.log(`  优化优先级建议:\n`);
    const worst = allResults.filter(r => r.scores.performance !== undefined).sort((a, b) => a.scores.performance - b.scores.performance);
    if (worst.length > 0) {
        const worstLabel = worst[0].label;
        console.log(`  1. 🔴 首要优化: ${worstLabel} (性能 ${(worst[0].scores.performance * 100).toFixed(0)}分)`);
        console.log(`     检查报告: e2e-report/lighthouse/${worst[0].name}-desktop.html`);
        console.log(`  2. 🟡 次要优化: 图片格式、JS/CSS 压缩、预连接`);
        console.log(`  3. 🟢 定期监控: 所有页面 SEO 和可访问性\n`);
    }
}

main().catch(err => {
    console.error('Lighthouse 审计失败:', err);
    process.exit(1);
});
