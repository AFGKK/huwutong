import apiClient from './client';

export default {
    // 仪表盘
    dashboard() { return apiClient.get('/admin/automation/dashboard'); },

    // 可用选项
    triggers() { return apiClient.get('/admin/automation/triggers'); },
    availableActions() { return apiClient.get('/admin/automation/available-actions'); },

    // 规则 CRUD
    getRules(params) { return apiClient.get('/admin/automation/rules', { params }); },
    getRule(id) { return apiClient.get(`/admin/automation/rules/${id}`); },
    createRule(data) { return apiClient.post('/admin/automation/rules', data); },
    updateRule(id, data) { return apiClient.put(`/admin/automation/rules/${id}`, data); },
    deleteRule(id) { return apiClient.delete(`/admin/automation/rules/${id}`); },
    toggleRule(id) { return apiClient.post(`/admin/automation/rules/${id}/toggle`); },
    executeRule(id, context) { return apiClient.post(`/admin/automation/rules/${id}/execute`, { context }); },

    // 执行历史
    getExecutions(ruleId, params) { return apiClient.get(`/admin/automation/rules/${ruleId}/executions`, { params }); },
    getAllExecutions(params) { return apiClient.get('/admin/automation/executions', { params }); },

    // Webhook 管理
    getWebhooks() { return apiClient.get('/admin/automation/webhooks'); },
    createWebhook(data) { return apiClient.post('/admin/automation/webhooks', data); },
    updateWebhook(id, data) { return apiClient.put(`/admin/automation/webhooks/${id}`, data); },
    deleteWebhook(id) { return apiClient.delete(`/admin/automation/webhooks/${id}`); },
    testWebhook(id) { return apiClient.post(`/admin/automation/webhooks/${id}/test`); },
};
