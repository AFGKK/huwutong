import apiClient from './client';

const cacheInvalidationApi = {
    // 公开端点（SDK 调用）
    streamEvents(params) {
        // SSE 端点，用于 EventSource
        return `/api/sdk/cache/events?tenant_id=${params.tenant_id}&last_event_id=${params.last_event_id || ''}`;
    },
    pending(params) {
        return apiClient.get('/api/sdk/cache/pending', { params });
    },

    // 管理端点
    stats() { return apiClient.get('/api/sdk/cache/stats'); },
    list(params) { return apiClient.get('/api/sdk/cache/pending', { params }); },
    invalidate(data) { return apiClient.post('/api/sdk/cache/invalidate', data); },
    invalidateBatch(data) { return apiClient.post('/api/sdk/cache/invalidate-batch', data); },

    // Webhook 配置
    listWebhooks() { return apiClient.get('/api/sdk/cache/webhooks'); },
    storeWebhook(data) { return apiClient.post('/api/sdk/cache/webhooks', data); },
    updateWebhook(id, data) { return apiClient.put(`/api/sdk/cache/webhooks/${id}`, data); },
    destroyWebhook(id) { return apiClient.delete(`/api/sdk/cache/webhooks/${id}`); },
};

export default cacheInvalidationApi;
