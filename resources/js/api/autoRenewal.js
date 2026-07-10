import apiClient from './client';

const BASE = '/admin/auto-renewal';

export default {
    dashboard() {
        return apiClient.get(`${BASE}/dashboard`);
    },
    plans(params) {
        return apiClient.get(`${BASE}/plans`, { params });
    },
    storePlan(data) {
        return apiClient.post(`${BASE}/plans`, data);
    },
    updatePlan(id, data) {
        return apiClient.put(`${BASE}/plans/${id}`, data);
    },
    subscriptions(params) {
        return apiClient.get(`${BASE}/subscriptions`, { params });
    },
    renew(id) {
        return apiClient.post(`${BASE}/subscriptions/${id}/renew`);
    },
    pause(id) {
        return apiClient.post(`${BASE}/subscriptions/${id}/pause`);
    },
    resume(id) {
        return apiClient.post(`${BASE}/subscriptions/${id}/resume`);
    },
    cancel(id) {
        return apiClient.post(`${BASE}/subscriptions/${id}/cancel`);
    },
};
