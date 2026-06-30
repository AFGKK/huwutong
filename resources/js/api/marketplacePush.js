import apiClient from './client';

export default {
    campaigns(params) {
        return apiClient.get('/marketplace/push/campaigns', { params });
    },
    campaignShow(id) {
        return apiClient.get(`/marketplace/push/campaigns/${id}`);
    },
    campaignCreate(data) {
        return apiClient.post('/marketplace/push/campaigns', data);
    },
    campaignUpdate(id, data) {
        return apiClient.put(`/marketplace/push/campaigns/${id}`, data);
    },
    campaignSend(id) {
        return apiClient.post(`/marketplace/push/campaigns/${id}/send`);
    },
    campaignCancel(id) {
        return apiClient.post(`/marketplace/push/campaigns/${id}/cancel`);
    },
    campaignDelete(id) {
        return apiClient.delete(`/marketplace/push/campaigns/${id}`);
    },
    stats() {
        return apiClient.get('/marketplace/push/stats');
    },
};
