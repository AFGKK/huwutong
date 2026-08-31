import apiClient from './client';

const BASE = '/feature-flags';

export default {
    list(params = {}) {
        return apiClient.get(BASE, { params });
    },
    create(data) {
        return apiClient.post(BASE, data);
    },
    update(id, data) {
        return apiClient.put(`${BASE}/${id}`, data);
    },
    toggle(id, isActive) {
        return apiClient.patch(`${BASE}/${id}`, { is_active: isActive });
    },
    destroy(id) {
        return apiClient.delete(`${BASE}/${id}`);
    },
    assignments() {
        return apiClient.get(`${BASE}/assignments`);
    },
    assign(data) {
        return apiClient.post(`${BASE}/assign`, data);
    },
};
