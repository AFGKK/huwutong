import apiClient from './client';

const BASE = '/admin/sla-probes';

export default {
    dashboard() {
        return apiClient.get(`${BASE}/dashboard`);
    },
    list(params) {
        return apiClient.get(BASE, { params });
    },
    store(data) {
        return apiClient.post(BASE, data);
    },
    update(id, data) {
        return apiClient.put(`${BASE}/${id}`, data);
    },
    destroy(id) {
        return apiClient.delete(`${BASE}/${id}`);
    },
    toggle(id) {
        return apiClient.post(`${BASE}/${id}/toggle`);
    },
    run(id) {
        return apiClient.post(`${BASE}/${id}/run`);
    },
    results(id, params) {
        return apiClient.get(`${BASE}/${id}/results`, { params });
    },
};
