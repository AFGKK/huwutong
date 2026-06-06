import apiClient from './client';

export default {
    list(params) {
        return apiClient.get('/email-templates', { params });
    },
    show(id) {
        return apiClient.get(`/email-templates/${id}`);
    },
    create(data) {
        return apiClient.post('/email-templates', data);
    },
    update(id, data) {
        return apiClient.put(`/email-templates/${id}`, data);
    },
    destroy(id) {
        return apiClient.delete(`/email-templates/${id}`);
    },
    preview(data) {
        return apiClient.post('/email-templates/preview', data);
    },
    defaults() {
        return apiClient.get('/email-templates/defaults');
    },
    initDefaults() {
        return apiClient.post('/email-templates/init-defaults');
    },
    variables() {
        return apiClient.get('/email-templates/variables');
    },
};
