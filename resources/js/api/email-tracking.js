import apiClient from './client';

export default {
    overview(params) {
        return apiClient.get('/email-tracking/overview', { params });
    },
    logs(params) {
        return apiClient.get('/email-tracking/logs', { params });
    },
    templateDetail(templateCode, params) {
        return apiClient.get(`/email-tracking/template/${encodeURIComponent(templateCode)}`, { params });
    },
    bounceStats() {
        return apiClient.get('/email-tracking/bounces');
    },
};
