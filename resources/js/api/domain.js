import apiClient from './client';

export default {
    list(params = {}) {
        return apiClient.get('/domains', { params });
    },
    create(data) {
        return apiClient.post('/domains', data);
    },
    show(id) {
        return apiClient.get(`/domains/${id}`);
    },
    verify(id) {
        return apiClient.post(`/domains/${id}/verify`);
    },
    issueSsl(id) {
        return apiClient.post(`/domains/${id}/ssl/issue`);
    },
    uploadSsl(id, data) {
        return apiClient.post(`/domains/${id}/ssl/upload`, data);
    },
    dnsInfo(id) {
        return apiClient.get(`/domains/${id}/dns`);
    },
    updateRoute(id, data) {
        return apiClient.put(`/domains/${id}/route`, data);
    },
    destroy(id) {
        return apiClient.delete(`/domains/${id}`);
    },
};
