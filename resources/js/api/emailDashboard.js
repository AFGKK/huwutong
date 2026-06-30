import apiClient from './client';

export default {
    overview(params) {
        return apiClient.get('/admin/email-dashboard/overview', { params });
    },
    logs(params) {
        return apiClient.get('/admin/email-dashboard/logs', { params });
    },
    logDetail(id) {
        return apiClient.get(`/admin/email-dashboard/logs/${id}`);
    },
    templateDetail(code, params) {
        return apiClient.get(`/admin/email-dashboard/templates/${code}`, { params });
    },
};
