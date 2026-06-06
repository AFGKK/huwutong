import { describe, it, expect, vi, beforeEach } from 'vitest';

describe('errorReporter', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('模块能够正确导入', async () => {
        const reporter = (await import('@/utils/errorReporter')).default;
        expect(reporter).toBeDefined();
        expect(typeof reporter.vueError).toBe('function');
        expect(typeof reporter.unhandledRejection).toBe('function');
    });

    it('console 输出（开发环境默认行为）', async () => {
        const reporter = (await import('@/utils/errorReporter')).default;
        const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

        reporter.vueError(new Error('测试错误'), null, 'render');

        expect(consoleSpy).toHaveBeenCalled();
        consoleSpy.mockRestore();
    });

    it('unhandledRejection 被调用时阻止默认行为', async () => {
        const reporter = (await import('@/utils/errorReporter')).default;
        const preventDefault = vi.fn();
        const event = { reason: new Error('Promise 错误'), preventDefault };
        const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

        reporter.unhandledRejection(event);

        expect(preventDefault).toHaveBeenCalled();
        expect(consoleSpy).toHaveBeenCalled();
        consoleSpy.mockRestore();
    });
});

describe('AdminLayout error boundary', () => {
    it('onErrorCaptured 处理器正确导入', async () => {
        // 仅验证模块可以加载而不报错
        await import('@/layouts/AdminLayout.vue');
    });
});

describe('PortalLayout error boundary', () => {
    it('模块能够正确加载', async () => {
        await import('@/layouts/PortalLayout.vue');
    });
});
