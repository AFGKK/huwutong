import { describe, it, expect, vi, beforeEach } from 'vitest';

const mockPost = vi.fn();

vi.mock('@/api/client', () => ({
    default: {
        post: (...args) => mockPost(...args),
    },
}));

describe('authApi - refreshToken', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('refreshToken 发送 POST /token/refresh', async () => {
        const authApi = (await import('@/api/auth')).default;
        authApi.refreshToken();
        expect(mockPost).toHaveBeenCalledWith('/token/refresh');
    });
});
