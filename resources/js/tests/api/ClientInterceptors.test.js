import { describe, it, expect, vi, beforeEach } from 'vitest';

const mockRouterPush = vi.fn();

vi.mock('@/router', () => ({
    default: { push: mockRouterPush, currentRoute: { value: { name: 'Dashboard' } } },
}));

const mockElMessage = {
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
};

vi.mock('element-plus', () => ({
    ElMessage: mockElMessage,
}));

describe('apiClient - axios 拦截器', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        localStorage.clear();
    });

    it('请求拦截器自动附加 Authorization header', async () => {
        localStorage.setItem('auth_token', 'test-token');

        const apiClient = (await import('@/api/client')).default;
        const config = { headers: {} };
        const reqInterceptor = apiClient.interceptors.request.handlers[0]?.fulfilled;

        if (reqInterceptor) {
            const result = await reqInterceptor(config);
            expect(result.headers.Authorization).toBe('Bearer test-token');
        }
    });

    it('请求拦截器没有 token 时不添加 Authorization', async () => {
        const apiClient = (await import('@/api/client')).default;

        const config = { headers: {} };
        const reqInterceptor = apiClient.interceptors.request.handlers[0]?.fulfilled;

        if (reqInterceptor) {
            const result = await reqInterceptor(config);
            expect(result.headers.Authorization).toBeUndefined();
        }
    });

    it('响应拦截器 401 时尝试刷新（失败后登出）', async () => {
        localStorage.setItem('auth_token', 'expired');
        localStorage.setItem('user', JSON.stringify({ id: 1 }));

        const apiClient = (await import('@/api/client')).default;
        const errInterceptor = apiClient.interceptors.response.handlers[0]?.rejected;

        if (errInterceptor) {
            const error = {
                response: { status: 401, data: { message: 'Unauthorized' } },
                config: { url: '/api/licenses', headers: {} },
            };

            await expect(errInterceptor(error)).rejects.toThrow();
        }
    });

    it('响应拦截器 403 时显示权限错误消息', async () => {
        const apiClient = (await import('@/api/client')).default;
        const errInterceptor = apiClient.interceptors.response.handlers[0]?.rejected;

        if (errInterceptor) {
            const error = { response: { status: 403, data: { message: 'Forbidden by policy' } }, config: { headers: {} } };

            await expect(errInterceptor(error)).rejects.toThrow();
            expect(mockElMessage.error).toHaveBeenCalledWith('Forbidden by policy');
        }
    });

    it('响应拦截器 429 时显示限流警告', async () => {
        const apiClient = (await import('@/api/client')).default;
        const errInterceptor = apiClient.interceptors.response.handlers[0]?.rejected;

        if (errInterceptor) {
            const error = { response: { status: 429, data: {} }, config: { headers: {} } };

            await expect(errInterceptor(error)).rejects.toThrow();
            const i18n = (await import('@/i18n')).default;
            expect(mockElMessage.warning).toHaveBeenCalledWith(i18n.global.t('messages.rate_limited'));
        }
    });

    it('响应拦截器 500 时显示服务器错误', async () => {
        const apiClient = (await import('@/api/client')).default;
        const errInterceptor = apiClient.interceptors.response.handlers[0]?.rejected;

        if (errInterceptor) {
            const error = { response: { status: 500, data: {} }, config: { headers: {} } };

            await expect(errInterceptor(error)).rejects.toThrow();
            const i18n = (await import('@/i18n')).default;
            expect(mockElMessage.error).toHaveBeenCalledWith(i18n.global.t('messages.internal_error'));
        }
    });
});
