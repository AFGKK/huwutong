import apiClient from './client';

export default {
    list(params) {
        return apiClient.get('/pages', { params });
    },
    create(data) {
        return apiClient.post('/pages', data);
    },
    show(id) {
        return apiClient.get(`/pages/${id}`);
    },
    update(id, data) {
        return apiClient.put(`/pages/${id}`, data);
    },
    publish(id) {
        return apiClient.post(`/pages/${id}/publish`);
    },
    draft(id) {
        return apiClient.post(`/pages/${id}/draft`);
    },
    destroy(id) {
        return apiClient.delete(`/pages/${id}`);
    },
    showBySlug(slug) {
        return apiClient.get(`/pages/public/${slug}`);
    },
};
