import client from './client';

export default {
    // ─── 推荐链接 ───
    generateLink(data) {
        return client.post('/affiliate/enhanced/generate-link', data);
    },
    agentLinks(agentId) {
        return client.get(`/affiliate/enhanced/agents/${agentId}/links`);
    },
    agentPortal(agentId) {
        return client.get(`/affiliate/enhanced/agents/${agentId}/portal`);
    },

    // ─── 收益账户结算 ───
    settleCommission(data) {
        return client.post('/affiliate/enhanced/settle-commission', data);
    },
    attributeWithSettlement(data) {
        return client.post('/affiliate/enhanced/attribute', data);
    },

    // ─── 商品级推广 ───
    generateProductLink(data) {
        return client.post('/affiliate/enhanced/product-link', data);
    },
    attributeOrder(data) {
        return client.post('/affiliate/enhanced/attribute-order', data);
    },
    productStats(productId) {
        return client.get(`/affiliate/enhanced/product-stats/${productId}`);
    },
    storeDashboard() {
        return client.get('/affiliate/enhanced/store-dashboard');
    },
};
