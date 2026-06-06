import { describe, it, expect, vi, beforeEach } from 'vitest';

const mockGet = vi.fn();
const mockPost = vi.fn();
const mockPut = vi.fn();
const mockDelete = vi.fn();

vi.mock('@/api/client', () => ({
    default: {
        get: (...args) => mockGet(...args),
        post: (...args) => mockPost(...args),
        put: (...args) => mockPut(...args),
        delete: (...args) => mockDelete(...args),
    },
}));

describe('webhookEndpointApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('list 发送 GET /webhook-endpoints 带参数', async () => {
        const api = (await import('@/api/webhookEndpoint')).default;
        api.list({ per_page: 20, is_active: true });
        expect(mockGet).toHaveBeenCalledWith('/webhook-endpoints', { params: { per_page: 20, is_active: true } });
    });

    it('create 发送 POST /webhook-endpoints', async () => {
        const api = (await import('@/api/webhookEndpoint')).default;
        const data = { name: 'Test', url: 'https://example.com/hook', events: ['*'] };
        api.create(data);
        expect(mockPost).toHaveBeenCalledWith('/webhook-endpoints', data);
    });

    it('show 发送 GET /webhook-endpoints/:id', async () => {
        const api = (await import('@/api/webhookEndpoint')).default;
        api.show(1);
        expect(mockGet).toHaveBeenCalledWith('/webhook-endpoints/1');
    });

    it('update 发送 PUT /webhook-endpoints/:id', async () => {
        const api = (await import('@/api/webhookEndpoint')).default;
        api.update(1, { name: 'Updated' });
        expect(mockPut).toHaveBeenCalledWith('/webhook-endpoints/1', { name: 'Updated' });
    });

    it('destroy 发送 DELETE /webhook-endpoints/:id', async () => {
        const api = (await import('@/api/webhookEndpoint')).default;
        api.destroy(1);
        expect(mockDelete).toHaveBeenCalledWith('/webhook-endpoints/1');
    });

    it('togglePause 发送 POST /webhook-endpoints/:id/toggle-pause', async () => {
        const api = (await import('@/api/webhookEndpoint')).default;
        api.togglePause(1);
        expect(mockPost).toHaveBeenCalledWith('/webhook-endpoints/1/toggle-pause');
    });

    it('test 发送 POST /webhook-endpoints/:id/test', async () => {
        const api = (await import('@/api/webhookEndpoint')).default;
        api.test(1);
        expect(mockPost).toHaveBeenCalledWith('/webhook-endpoints/1/test');
    });

    it('eventTypes 发送 GET /webhook-endpoints/event-types', async () => {
        const api = (await import('@/api/webhookEndpoint')).default;
        api.eventTypes();
        expect(mockGet).toHaveBeenCalledWith('/webhook-endpoints/event-types');
    });
});
