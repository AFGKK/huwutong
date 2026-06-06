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

describe('billingApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('subscriptions 发送 GET /billing/subscriptions 带参数', async () => {
        const billingApi = (await import('@/api/billing')).default;
        billingApi.subscriptions({ page: 1, status: 'active' });
        expect(mockGet).toHaveBeenCalledWith('/billing/subscriptions', { params: { page: 1, status: 'active' } });
    });

    it('createSubscription 发送 POST /billing/subscriptions', async () => {
        const billingApi = (await import('@/api/billing')).default;
        const data = { customer_id: 1, product_id: 2, plan: 'pro', price: 199 };
        billingApi.createSubscription(data);
        expect(mockPost).toHaveBeenCalledWith('/billing/subscriptions', data);
    });

    it('getSubscription 发送 GET /billing/subscriptions/:id', async () => {
        const billingApi = (await import('@/api/billing')).default;
        billingApi.getSubscription(42);
        expect(mockGet).toHaveBeenCalledWith('/billing/subscriptions/42');
    });

    it('changePlan 发送 PUT /billing/subscriptions/:id/plan', async () => {
        const billingApi = (await import('@/api/billing')).default;
        billingApi.changePlan(1, { plan: 'enterprise', price: 599 });
        expect(mockPut).toHaveBeenCalledWith('/billing/subscriptions/1/plan', { plan: 'enterprise', price: 599 });
    });

    it('cancelSubscription 发送 POST /billing/subscriptions/:id/cancel', async () => {
        const billingApi = (await import('@/api/billing')).default;
        billingApi.cancelSubscription(1, { reason: '太贵' });
        expect(mockPost).toHaveBeenCalledWith('/billing/subscriptions/1/cancel', { reason: '太贵' });
    });

    it('resumeSubscription 发送 POST /billing/subscriptions/:id/resume', async () => {
        const billingApi = (await import('@/api/billing')).default;
        billingApi.resumeSubscription(1);
        expect(mockPost).toHaveBeenCalledWith('/billing/subscriptions/1/resume');
    });

    it('invoices 发送 GET /billing/invoices 带参数', async () => {
        const billingApi = (await import('@/api/billing')).default;
        billingApi.invoices({ page: 1 });
        expect(mockGet).toHaveBeenCalledWith('/billing/invoices', { params: { page: 1 } });
    });

    it('markInvoicePaid 发送 POST /billing/invoices/:id/mark-paid', async () => {
        const billingApi = (await import('@/api/billing')).default;
        billingApi.markInvoicePaid(1, 'txn_123');
        expect(mockPost).toHaveBeenCalledWith('/billing/invoices/1/mark-paid', { transaction_id: 'txn_123' });
    });

    it('stats 发送 GET /billing/stats', async () => {
        const billingApi = (await import('@/api/billing')).default;
        billingApi.stats();
        expect(mockGet).toHaveBeenCalledWith('/billing/stats');
    });

    it('invoiceStats 发送 GET /billing/invoice-stats', async () => {
        const billingApi = (await import('@/api/billing')).default;
        billingApi.invoiceStats();
        expect(mockGet).toHaveBeenCalledWith('/billing/invoice-stats');
    });
});

describe('ragApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('retrieve 发送 GET /rag/retrieve 带 q 参数', async () => {
        const ragApi = (await import('@/api/rag')).default;
        ragApi.retrieve('how to activate');
        expect(mockGet).toHaveBeenCalledWith('/rag/retrieve', { params: { q: 'how to activate' } });
    });

    it('ask 发送 POST /rag/ask', async () => {
        const ragApi = (await import('@/api/rag')).default;
        ragApi.ask('什么是 License', 'sess_001', { top_k: 5 });
        expect(mockPost).toHaveBeenCalledWith('/rag/ask', { q: '什么是 License', session_id: 'sess_001', top_k: 5 });
    });

    it('history 发送 GET /rag/history', async () => {
        const ragApi = (await import('@/api/rag')).default;
        ragApi.history('sess_001');
        expect(mockGet).toHaveBeenCalledWith('/rag/history', { params: { session_id: 'sess_001' } });
    });

    it('feedback 发送 POST /rag/feedback', async () => {
        const ragApi = (await import('@/api/rag')).default;
        ragApi.feedback('msg_1', true);
        expect(mockPost).toHaveBeenCalledWith('/rag/feedback', { message_id: 'msg_1', was_helpful: true });
    });

    it('rebuildIndex 发送 POST /rag/rebuild', async () => {
        const ragApi = (await import('@/api/rag')).default;
        ragApi.rebuildIndex();
        expect(mockPost).toHaveBeenCalledWith('/rag/rebuild');
    });

    it('stats 发送 GET /rag/stats', async () => {
        const ragApi = (await import('@/api/rag')).default;
        ragApi.stats();
        expect(mockGet).toHaveBeenCalledWith('/rag/stats');
    });
});

describe('deviceApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('list 发送 GET /devices 带参数', async () => {
        const deviceApi = (await import('@/api/device')).default;
        deviceApi.list({ 'filter[platform]': 'windows' });
        expect(mockGet).toHaveBeenCalledWith('/devices', { params: { 'filter[platform]': 'windows' } });
    });

    it('show 发送 GET /devices/:id', async () => {
        const deviceApi = (await import('@/api/device')).default;
        deviceApi.show(5);
        expect(mockGet).toHaveBeenCalledWith('/devices/5');
    });

    it('deactivate 发送 POST /devices/:id/deactivate', async () => {
        const deviceApi = (await import('@/api/device')).default;
        deviceApi.deactivate(1, true);
        expect(mockPost).toHaveBeenCalledWith('/devices/1/deactivate', { blacklist: true });
    });

    it('stats 发送 GET /devices/stats', async () => {
        const deviceApi = (await import('@/api/device')).default;
        deviceApi.stats();
        expect(mockGet).toHaveBeenCalledWith('/devices/stats');
    });

    it('batch 发送 POST /devices/batch', async () => {
        const deviceApi = (await import('@/api/device')).default;
        deviceApi.batch([1, 2, 3], 'deactivate');
        expect(mockPost).toHaveBeenCalledWith('/devices/batch', { ids: [1, 2, 3], action: 'deactivate' });
    });
});

describe('notificationApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('list 发送 GET /notifications 带参数', async () => {
        const notificationApi = (await import('@/api/notification')).default;
        notificationApi.list({ page: 1 });
        expect(mockGet).toHaveBeenCalledWith('/notifications', { params: { page: 1 } });
    });

    it('unreadCount 发送 GET /notifications/unread-count', async () => {
        const notificationApi = (await import('@/api/notification')).default;
        notificationApi.unreadCount();
        expect(mockGet).toHaveBeenCalledWith('/notifications/unread-count');
    });

    it('markRead 发送 POST /notifications/:id/read', async () => {
        const notificationApi = (await import('@/api/notification')).default;
        notificationApi.markRead(10);
        expect(mockPost).toHaveBeenCalledWith('/notifications/10/read');
    });

    it('markAllRead 发送 POST /notifications/read-all', async () => {
        const notificationApi = (await import('@/api/notification')).default;
        notificationApi.markAllRead();
        expect(mockPost).toHaveBeenCalledWith('/notifications/read-all');
    });

    it('destroy 发送 DELETE /notifications/:id', async () => {
        const notificationApi = (await import('@/api/notification')).default;
        notificationApi.destroy(5);
        expect(mockDelete).toHaveBeenCalledWith('/notifications/5');
    });

    it('preferences 发送 GET /notifications/preferences', async () => {
        const notificationApi = (await import('@/api/notification')).default;
        notificationApi.preferences();
        expect(mockGet).toHaveBeenCalledWith('/notifications/preferences');
    });

    it('updatePreferences 发送 PUT /notifications/preferences', async () => {
        const notificationApi = (await import('@/api/notification')).default;
        notificationApi.updatePreferences({ email: true, sms: false });
        expect(mockPut).toHaveBeenCalledWith('/notifications/preferences', { email: true, sms: false });
    });
});

describe('ticketApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('list 发送 GET /tickets 带参数', async () => {
        const ticketApi = (await import('@/api/ticket')).default;
        ticketApi.list({ status: 'open' });
        expect(mockGet).toHaveBeenCalledWith('/tickets', { params: { status: 'open' } });
    });

    it('create 发送 POST /tickets 带数据', async () => {
        const ticketApi = (await import('@/api/ticket')).default;
        ticketApi.create({ subject: 'help', description: 'test' });
        expect(mockPost).toHaveBeenCalledWith('/tickets', { subject: 'help', description: 'test' });
    });
});

describe('kbApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('categories 发送 GET /kb/categories', async () => {
        const kbApi = (await import('@/api/kb')).default;
        kbApi.categories();
        expect(mockGet).toHaveBeenCalledWith('/kb/categories', { params: undefined });
    });

    it('search 发送 GET /kb/search 带参数', async () => {
        const kbApi = (await import('@/api/kb')).default;
        kbApi.search({ q: 'license' });
        expect(mockGet).toHaveBeenCalledWith('/kb/search', { params: { q: 'license' } });
    });

    it('adminArticles 发送 GET /kb/articles', async () => {
        const kbApi = (await import('@/api/kb')).default;
        kbApi.adminArticles();
        expect(mockGet).toHaveBeenCalledWith('/kb/articles', { params: undefined });
    });

    it('createArticle 发送 POST /kb/articles', async () => {
        const kbApi = (await import('@/api/kb')).default;
        kbApi.createArticle({ title: 'FAQ', content: '...' });
        expect(mockPost).toHaveBeenCalledWith('/kb/articles', { title: 'FAQ', content: '...' });
    });
});

describe('mfaApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('list 发送 GET /mfa/devices', async () => {
        const mfaApi = (await import('@/api/mfa')).default;
        mfaApi.list();
        expect(mockGet).toHaveBeenCalledWith('/mfa/devices', { params: undefined });
    });

    it('verify 发送 POST /mfa/verify 带 data', async () => {
        const mfaApi = (await import('@/api/mfa')).default;
        mfaApi.verify({ code: '123456' });
        expect(mockPost).toHaveBeenCalledWith('/mfa/verify', { code: '123456' });
    });

    it('disable 发送 POST /mfa/disable 带 data', async () => {
        const mfaApi = (await import('@/api/mfa')).default;
        mfaApi.disable({ code: '123456' });
        expect(mockPost).toHaveBeenCalledWith('/mfa/disable', { code: '123456' });
    });
});

describe('auditLogApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('list 发送 GET /audit-logs 带参数', async () => {
        const auditLogApi = (await import('@/api/auditLog')).default;
        auditLogApi.list({ page: 1 });
        expect(mockGet).toHaveBeenCalledWith('/audit-logs', { params: { page: 1 } });
    });
});

describe('apiKeyApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('list 发送 GET /api-keys 带参数', async () => {
        const apiKeyApi = (await import('@/api/apiKey')).default;
        apiKeyApi.list();
        expect(mockGet).toHaveBeenCalledWith('/api-keys', { params: undefined });
    });

    it('create 发送 POST /api-keys', async () => {
        const apiKeyApi = (await import('@/api/apiKey')).default;
        apiKeyApi.create({ name: 'My Key' });
        expect(mockPost).toHaveBeenCalledWith('/api-keys', { name: 'My Key' });
    });
});

describe('webhookApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('list 发送 GET /webhook-replay/events 带参数', async () => {
        const webhookApi = (await import('@/api/webhook')).default;
        webhookApi.list();
        expect(mockGet).toHaveBeenCalledWith('/webhook-replay/events', { params: undefined });
    });

    it('replay 发送 POST /webhook-replay/events/:id/replay', async () => {
        const webhookApi = (await import('@/api/webhook')).default;
        webhookApi.replay(1);
        expect(mockPost).toHaveBeenCalledWith('/webhook-replay/events/1/replay');
    });

    it('stats 发送 GET /webhook-replay/stats', async () => {
        const webhookApi = (await import('@/api/webhook')).default;
        webhookApi.stats();
        expect(mockGet).toHaveBeenCalledWith('/webhook-replay/stats');
    });
});

describe('trialApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('create 发送 POST /trial', async () => {
        const trialApi = (await import('@/api/trial')).default;
        trialApi.create({ product_id: 1 });
        expect(mockPost).toHaveBeenCalledWith('/trial', { product_id: 1 });
    });

    it('status 发送 GET /trial/:id', async () => {
        const trialApi = (await import('@/api/trial')).default;
        trialApi.status(1);
        expect(mockGet).toHaveBeenCalledWith('/trial/1');
    });
});

describe('healthApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('live 发送 GET /health/live', async () => {
        const healthApi = (await import('@/api/health')).default;
        healthApi.live();
        expect(mockGet).toHaveBeenCalledWith('/health/live');
    });

    it('ready 发送 GET /health/ready', async () => {
        const healthApi = (await import('@/api/health')).default;
        healthApi.ready();
        expect(mockGet).toHaveBeenCalledWith('/health/ready');
    });

    it('status 发送 GET /health/status', async () => {
        const healthApi = (await import('@/api/health')).default;
        healthApi.status();
        expect(mockGet).toHaveBeenCalledWith('/health/status');
    });
});

describe('domainApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('list 发送 GET /domains', async () => {
        const domainApi = (await import('@/api/domain')).default;
        domainApi.list();
        expect(mockGet).toHaveBeenCalledWith('/domains');
    });

    it('create 发送 POST /domains', async () => {
        const domainApi = (await import('@/api/domain')).default;
        domainApi.create({ domain: 'example.com' });
        expect(mockPost).toHaveBeenCalledWith('/domains', { domain: 'example.com' });
    });
});

describe('sandboxApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('create 发送 POST /sandbox/create', async () => {
        const sandboxApi = (await import('@/api/sandbox')).default;
        sandboxApi.create();
        expect(mockPost).toHaveBeenCalledWith('/sandbox/create');
    });

    it('status 发送 GET /sandbox/status', async () => {
        const sandboxApi = (await import('@/api/sandbox')).default;
        sandboxApi.status();
        expect(mockGet).toHaveBeenCalledWith('/sandbox/status');
    });
});

describe('taxApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('countries 发送 GET /tax/countries', async () => {
        const taxApi = (await import('@/api/tax')).default;
        taxApi.countries();
        expect(mockGet).toHaveBeenCalledWith('/tax/countries');
    });

    it('rates 发送 GET /tax/rates 带参数', async () => {
        const taxApi = (await import('@/api/tax')).default;
        taxApi.rates({ country: 'CN' });
        expect(mockGet).toHaveBeenCalledWith('/tax/rates', { params: { country: 'CN' } });
    });
});

describe('offlineApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('generate 发送 POST /offline/generate', async () => {
        const offlineApi = (await import('@/api/offline')).default;
        offlineApi.generate(1);
        expect(mockPost).toHaveBeenCalledWith('/offline/generate', { license_id: 1 });
    });

    it('publicKey 发送 GET /offline/public-key', async () => {
        const offlineApi = (await import('@/api/offline')).default;
        offlineApi.publicKey();
        expect(mockGet).toHaveBeenCalledWith('/offline/public-key', { params: {} });
    });
});

describe('licenseFileCdnApi', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('generate 发送 POST /license-files/generate', async () => {
        const cdnApi = (await import('@/api/license-file-cdn')).default;
        cdnApi.generate(1);
        expect(mockPost).toHaveBeenCalledWith('/license-files/generate', { license_id: 1 });
    });

    it('stats 发送 GET /license-files/stats', async () => {
        const cdnApi = (await import('@/api/license-file-cdn')).default;
        cdnApi.stats();
        expect(mockGet).toHaveBeenCalledWith('/license-files/stats');
    });
});
