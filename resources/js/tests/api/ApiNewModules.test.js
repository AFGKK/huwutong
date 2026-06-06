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

describe('diagnosticApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('diagnose 发送 POST /diagnostic/diagnose', async () => {
        const api = (await import('@/api/diagnostic')).default;
        api.diagnose({ error_log: 'test error' });
        expect(mockPost).toHaveBeenCalledWith('/diagnostic/diagnose', { error_log: 'test error' });
    });

    it('diagnoseActivation 发送 POST /diagnostic/activation', async () => {
        const api = (await import('@/api/diagnostic')).default;
        api.diagnoseActivation({ license_key: 'XXXX-XXXX' });
        expect(mockPost).toHaveBeenCalledWith('/diagnostic/activation', { license_key: 'XXXX-XXXX' });
    });

    it('diagnoseBatch 发送 POST /diagnostic/batch', async () => {
        const api = (await import('@/api/diagnostic')).default;
        api.diagnoseBatch({ errors: ['err1', 'err2'] });
        expect(mockPost).toHaveBeenCalledWith('/diagnostic/batch', { errors: ['err1', 'err2'] });
    });

    it('sdkSuggestions 发送 GET /diagnostic/sdk-suggestions', async () => {
        const api = (await import('@/api/diagnostic')).default;
        api.sdkSuggestions();
        expect(mockGet).toHaveBeenCalledWith('/diagnostic/sdk-suggestions');
    });
});

describe('ssoApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('providers 发送 GET /sso/providers', async () => {
        const api = (await import('@/api/sso')).default;
        api.providers();
        expect(mockGet).toHaveBeenCalledWith('/sso/providers');
    });

    it('configure 发送 POST /sso/providers', async () => {
        const api = (await import('@/api/sso')).default;
        api.configure({ type: 'oidc', client_id: 'xxx', client_secret: 'yyy' });
        expect(mockPost).toHaveBeenCalledWith('/sso/providers', { type: 'oidc', client_id: 'xxx', client_secret: 'yyy' });
    });

    it('toggle 发送 POST /sso/providers/:id/toggle', async () => {
        const api = (await import('@/api/sso')).default;
        api.toggle(1);
        expect(mockPost).toHaveBeenCalledWith('/sso/providers/1/toggle');
    });

    it('loginUrl 发送 GET /sso/providers/:id/login-url', async () => {
        const api = (await import('@/api/sso')).default;
        api.loginUrl(1);
        expect(mockGet).toHaveBeenCalledWith('/sso/providers/1/login-url');
    });

    it('connections 发送 GET /sso/connections', async () => {
        const api = (await import('@/api/sso')).default;
        api.connections();
        expect(mockGet).toHaveBeenCalledWith('/sso/connections');
    });

    it('disconnect 发送 DELETE /sso/connections/:id', async () => {
        const api = (await import('@/api/sso')).default;
        api.disconnect(5);
        expect(mockDelete).toHaveBeenCalledWith('/sso/connections/5');
    });
});

describe('featureFlagApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('list 发送 GET /feature-flags', async () => {
        const api = (await import('@/api/featureFlag')).default;
        api.list();
        expect(mockGet).toHaveBeenCalledWith('/feature-flags');
    });

    it('assign 发送 POST /feature-flags/assign', async () => {
        const api = (await import('@/api/featureFlag')).default;
        api.assign({ license_id: 1, feature: 'premium', enabled: true });
        expect(mockPost).toHaveBeenCalledWith('/feature-flags/assign', { license_id: 1, feature: 'premium', enabled: true });
    });
});

describe('chatApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('send 发送 POST /chat/send', async () => {
        const api = (await import('@/api/chat')).default;
        api.send({ message: 'Hello', session_id: 'abc' });
        expect(mockPost).toHaveBeenCalledWith('/chat/send', { message: 'Hello', session_id: 'abc' });
    });

    it('sendStream 发送 POST /chat/stream 带 responseType', async () => {
        const api = (await import('@/api/chat')).default;
        api.sendStream({ message: 'Stream me' });
        expect(mockPost).toHaveBeenCalledWith('/chat/stream', { message: 'Stream me' }, { responseType: 'stream' });
    });

    it('history 发送 GET /chat/history 带参数', async () => {
        const api = (await import('@/api/chat')).default;
        api.history({ session_id: 'abc' });
        expect(mockGet).toHaveBeenCalledWith('/chat/history', { params: { session_id: 'abc' } });
    });

    it('feedback 发送 POST /chat/feedback', async () => {
        const api = (await import('@/api/chat')).default;
        api.feedback({ message_id: 1, rating: 5 });
        expect(mockPost).toHaveBeenCalledWith('/chat/feedback', { message_id: 1, rating: 5 });
    });

    it('intents 发送 GET /chat/intents', async () => {
        const api = (await import('@/api/chat')).default;
        api.intents();
        expect(mockGet).toHaveBeenCalledWith('/chat/intents');
    });

    it('stats 发送 GET /chat/stats', async () => {
        const api = (await import('@/api/chat')).default;
        api.stats();
        expect(mockGet).toHaveBeenCalledWith('/chat/stats');
    });
});

describe('retentionApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('failureStats 发送 GET /retention/failure-stats', async () => {
        const api = (await import('@/api/retention')).default;
        api.failureStats();
        expect(mockGet).toHaveBeenCalledWith('/retention/failure-stats');
    });

    it('subscriptionFailures 发送 GET 带参数', async () => {
        const api = (await import('@/api/retention')).default;
        api.subscriptionFailures(1, { page: 1 });
        expect(mockGet).toHaveBeenCalledWith('/retention/subscriptions/1/failures', { params: { page: 1 } });
    });

    it('manualRetry 发送 POST', async () => {
        const api = (await import('@/api/retention')).default;
        api.manualRetry(1, { force: true });
        expect(mockPost).toHaveBeenCalledWith('/retention/subscriptions/1/manual-retry', { force: true });
    });

    it('pendingEscalations 发送 GET 带参数', async () => {
        const api = (await import('@/api/retention')).default;
        api.pendingEscalations({ status: 'open' });
        expect(mockGet).toHaveBeenCalledWith('/retention/escalations', { params: { status: 'open' } });
    });

    it('resolveEscalation 发送 POST', async () => {
        const api = (await import('@/api/retention')).default;
        api.resolveEscalation(1, { note: 'Resolved' });
        expect(mockPost).toHaveBeenCalledWith('/retention/escalations/1/resolve', { note: 'Resolved' });
    });
});

describe('openFeatureApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('manageAllFlags 发送 GET /openfeature/manage/flags', async () => {
        const api = (await import('@/api/openFeature')).default;
        api.manageAllFlags();
        expect(mockGet).toHaveBeenCalledWith('/openfeature/manage/flags');
    });
});

describe('statusPageApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('status 发送 GET /status', async () => {
        const api = (await import('@/api/statusPage')).default;
        api.status();
        expect(mockGet).toHaveBeenCalledWith('/status');
    });

    it('history 发送 GET /status/history', async () => {
        const api = (await import('@/api/statusPage')).default;
        api.history();
        expect(mockGet).toHaveBeenCalledWith('/status/history');
    });

    it('subscribe 发送 POST /status/subscribe', async () => {
        const api = (await import('@/api/statusPage')).default;
        api.subscribe('user@example.com');
        expect(mockPost).toHaveBeenCalledWith('/status/subscribe', { email: 'user@example.com' });
    });
});

describe('pageApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('list 发送 GET /pages 带参数', async () => {
        const api = (await import('@/api/page')).default;
        api.list({ page: 1, status: 'published' });
        expect(mockGet).toHaveBeenCalledWith('/pages', { params: { page: 1, status: 'published' } });
    });

    it('create 发送 POST /pages', async () => {
        const api = (await import('@/api/page')).default;
        api.create({ title: 'About', content: '# About us', status: 'draft' });
        expect(mockPost).toHaveBeenCalledWith('/pages', { title: 'About', content: '# About us', status: 'draft' });
    });

    it('show 发送 GET /pages/:id', async () => {
        const api = (await import('@/api/page')).default;
        api.show(42);
        expect(mockGet).toHaveBeenCalledWith('/pages/42');
    });

    it('update 发送 PUT /pages/:id', async () => {
        const api = (await import('@/api/page')).default;
        api.update(1, { content: 'Updated' });
        expect(mockPut).toHaveBeenCalledWith('/pages/1', { content: 'Updated' });
    });

    it('publish 发送 POST /pages/:id/publish', async () => {
        const api = (await import('@/api/page')).default;
        api.publish(1);
        expect(mockPost).toHaveBeenCalledWith('/pages/1/publish');
    });

    it('draft 发送 POST /pages/:id/draft', async () => {
        const api = (await import('@/api/page')).default;
        api.draft(1);
        expect(mockPost).toHaveBeenCalledWith('/pages/1/draft');
    });

    it('destroy 发送 DELETE /pages/:id', async () => {
        const api = (await import('@/api/page')).default;
        api.destroy(1);
        expect(mockDelete).toHaveBeenCalledWith('/pages/1');
    });

    it('showBySlug 发送 GET /pages/public/:slug', async () => {
        const api = (await import('@/api/page')).default;
        api.showBySlug('about-us');
        expect(mockGet).toHaveBeenCalledWith('/pages/public/about-us');
    });
});

describe('llmFallbackApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('status 发送 GET /llm/fallback/status', async () => {
        const api = (await import('@/api/llmFallback')).default;
        api.status();
        expect(mockGet).toHaveBeenCalledWith('/llm/fallback/status');
    });

    it('reset 发送 POST /llm/fallback/reset', async () => {
        const api = (await import('@/api/llmFallback')).default;
        api.reset();
        expect(mockPost).toHaveBeenCalledWith('/llm/fallback/reset');
    });
});

describe('licenseActivationApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('activate 发送 POST /license/activate', async () => {
        const api = (await import('@/api/licenseActivation')).default;
        api.activate({ license_key: 'XXXX', machine_fingerprint: 'fp123' });
        expect(mockPost).toHaveBeenCalledWith('/license/activate', { license_key: 'XXXX', machine_fingerprint: 'fp123' });
    });

    it('validate 发送 POST /license/validate', async () => {
        const api = (await import('@/api/licenseActivation')).default;
        api.validate({ license_key: 'XXXX' });
        expect(mockPost).toHaveBeenCalledWith('/license/validate', { license_key: 'XXXX' });
    });
});
