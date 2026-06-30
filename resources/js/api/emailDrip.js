import client from './client';

export default {
    dashboard() {
        return client.get('/admin/email-drip/dashboard');
    },
    listCampaigns(params = {}) {
        return client.get('/admin/email-drip/campaigns', { params });
    },
    getCampaign(id) {
        return client.get(`/admin/email-drip/campaigns/${id}`);
    },
    createCampaign(data) {
        return client.post('/admin/email-drip/campaigns', data);
    },
    addSequence(campaignId, data) {
        return client.post(`/admin/email-drip/campaigns/${campaignId}/sequences`, data);
    },
    activateCampaign(id) {
        return client.post(`/admin/email-drip/campaigns/${id}/activate`);
    },
    pauseCampaign(id) {
        return client.post(`/admin/email-drip/campaigns/${id}/pause`);
    },
    getTriggers() {
        return client.get('/admin/email-drip/triggers');
    },
};
