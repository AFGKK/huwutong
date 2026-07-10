import client from './client';

export default {
    // ─── 活动管理 ───
    campaigns(params = {}) {
        return client.get('/store-affiliate/campaigns', { params });
    },
    showCampaign(id) {
        return client.get(`/store-affiliate/campaigns/${id}`);
    },
    createCampaign(data) {
        return client.post('/store-affiliate/campaigns', data);
    },
    updateCampaign(id, data) {
        return client.put(`/store-affiliate/campaigns/${id}`, data);
    },
    refreshCampaign(id) {
        return client.post(`/store-affiliate/campaigns/${id}/refresh`);
    },
    depositBudget(campaignId, amount, paymentMethod = 'mock_instant') {
        return client.post(`/store-affiliate/campaigns/${campaignId}/deposit`, { amount, payment_method: paymentMethod });
    },

    // ─── 素材管理 ───
    creatives(campaignId) {
        return client.get(`/store-affiliate/campaigns/${campaignId}/creatives`);
    },
    createCreative(campaignId, data) {
        return client.post(`/store-affiliate/campaigns/${campaignId}/creatives`, data);
    },
    updateCreative(campaignId, id, data) {
        return client.put(`/store-affiliate/campaigns/${campaignId}/creatives/${id}`, data);
    },
    destroyCreative(campaignId, id) {
        return client.delete(`/store-affiliate/campaigns/${campaignId}/creatives/${id}`);
    },
    creativeStats(campaignId) {
        return client.get(`/store-affiliate/campaigns/${campaignId}/creative-stats`);
    },
    reviewCreative(campaignId, id, action, reviewNotes = '') {
        return client.post(`/store-affiliate/campaigns/${campaignId}/creatives/${id}/review`, { action, review_notes: reviewNotes });
    },
    pendingCreatives(params = {}) {
        return client.get('/store-affiliate/pending-creatives', { params });
    },
    resubmitCreative(id) {
        return client.post(`/store-affiliate/creatives/${id}/resubmit`);
    },
    submitCreative(data) {
        return client.post('/store-affiliate/creatives/submit', data);
    },
    myCreatives(params = {}) {
        return client.get('/store-affiliate/my-creatives', { params });
    },
    applyAgent() {
        return client.post('/store-affiliate/apply-agent');
    },
    pendingAgents(params = {}) {
        return client.get('/store-affiliate/pending-agents', { params });
    },
    reviewAgent(id, action, notes = '') {
        return client.post(`/store-affiliate/agents/${id}/review`, { action, notes });
    },

    // ─── 多级关系链 ───
    buildTree(data) {
        return client.post('/store-affiliate/tree', data);
    },
    upline(agentId) {
        return client.get(`/store-affiliate/agents/${agentId}/upline`);
    },
    downline(agentId) {
        return client.get(`/store-affiliate/agents/${agentId}/downline`);
    },

    // ─── 点击/转化 ───
    recordClick(data) {
        return client.post('/store-affiliate/clicks', data);
    },
    clickLogs(params = {}) {
        return client.get('/store-affiliate/clicks', { params });
    },
    attributeConversion(data) {
        return client.post('/store-affiliate/attribute', data);
    },

    // ─── 商品推广 ───
    promotableSkus(params = {}) {
        return client.get('/store-affiliate/promotable-skus', { params });
    },
    generateLink(data) {
        return client.post('/store-affiliate/generate-links', data);
    },

    // ─── 看板 ───
    dashboard() {
        return client.get('/store-affiliate/dashboard');
    },
    myCampaignLink(campaignId) {
        return client.get(`/store-affiliate/campaigns/${campaignId}/my-link`);
    },
    agentSummary(agentId) {
        return client.get(`/store-affiliate/agents/${agentId}/summary`);
    },
    myAgent() {
        return client.get('/store-affiliate/my-agent');
    },
};
