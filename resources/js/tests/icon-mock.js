// @element-plus/icons-vue 全局 Mock
// 此文件通过 vitest.config.js 的 setupFiles 加载
// 
// 注意：vi.mock 在 setup 文件中不能保证对所有测试文件生效。
// 因此，这里使用 Proxy mock 方式在全局模拟图标。
// 
// 如果测试依然报 No "XXX" export，请在测试文件顶部添加：
//   vi.mock('@element-plus/icons-vue', () => ({
//     default: {},
//     Bell: { render: () => {} },
//     ...
//   }))

import { vi } from 'vitest';

try {
    vi.mock('@element-plus/icons-vue', () => {
        const icon = { render: () => {}, name: 'MockIcon' };
        return new Proxy({}, {
            get(_, prop) {
                if (prop === 'default' || prop === '__esModule') return;
                return icon;
            },
        });
    });
} catch {
    // ignore if already mocked
}
