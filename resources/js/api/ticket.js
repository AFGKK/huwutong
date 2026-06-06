import apiClient from './client';

const ticketApi = {
    // ── 管理后台 API ──

    list(params) {
        return apiClient.get('/tickets', { params });
    },
    show(id) {
        return apiClient.get(`/tickets/${id}`);
    },
    stats() {
        return apiClient.get('/tickets/stats');
    },
    assign(id, userId) {
        return apiClient.post(`/tickets/${id}/assign`, { user_id: userId });
    },
    resolve(id) {
        return apiClient.post(`/tickets/${id}/resolve`);
    },
    close(id) {
        return apiClient.post(`/tickets/${id}/close`);
    },
    reopen(id) {
        return apiClient.post(`/tickets/${id}/reopen`);
    },

    // ── 客户自助 API ──

    myTickets(params) {
        return apiClient.get('/tickets/my', { params });
    },
    create(data) {
        return apiClient.post('/tickets', data);
    },
    reply(id, data) {
        return apiClient.post(`/tickets/${id}/reply`, data);
    },
    satisfaction(id, rating) {
        return apiClient.post(`/tickets/${id}/satisfaction`, { rating });
    },

    // ── 分类管理 ──

    categories() {
        return apiClient.get('/tickets/categories');
    },
    createCategory(data) {
        return apiClient.post('/tickets/categories', data);
    },
    deleteCategory(id) {
        return apiClient.delete(`/tickets/categories/${id}`);
    },

    // SLA
    checkSla(data) {
        return apiClient.post('/tickets/check-sla', data);
    },
};

export default ticketApi;
