import apiClient from './client';

export default {
    dashboard() {
        return apiClient.get('/admin/marketing/dashboard');
    },
    campaigns(params = {}) {
        return apiClient.get('/admin/marketing/campaigns', { params });
    },
    showCampaign(id) {
        return apiClient.get(`/admin/marketing/campaigns/${id}`);
    },
    createCampaign(data) {
        return apiClient.post('/admin/marketing/campaigns', data);
    },
    updateCampaign(id, data) {
        return apiClient.put(`/admin/marketing/campaigns/${id}`, data);
    },
    deleteCampaign(id) {
        return apiClient.delete(`/admin/marketing/campaigns/${id}`);
    },
    launchCampaign(id) {
        return apiClient.post(`/admin/marketing/campaigns/${id}/launch`);
    },
    toggleCampaign(id) {
        return apiClient.post(`/admin/marketing/campaigns/${id}/toggle`);
    },
    completeCampaign(id) {
        return apiClient.post(`/admin/marketing/campaigns/${id}/complete`);
    },
    cancelCampaign(id) {
        return apiClient.post(`/admin/marketing/campaigns/${id}/cancel`);
    },
    updateSteps(id, steps) {
        return apiClient.put(`/admin/marketing/campaigns/${id}/steps`, { steps });
    },
    simulateSend(id) {
        return apiClient.post(`/admin/marketing/campaigns/${id}/simulate`);
    },
    analytics(id) {
        return apiClient.get(`/admin/marketing/campaigns/${id}/analytics`);
    },
    previewAudience(data) {
        return apiClient.post('/admin/marketing/preview-audience', data);
    },
    stats() {
        return apiClient.get('/admin/marketing/stats');
    },
};
