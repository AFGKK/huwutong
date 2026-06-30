import client from './client';

export default {
    // 管理员
    dashboard(params = {}) {
        return client.get('/channel/dashboard', { params });
    },
    partners(params = {}) {
        return client.get('/channel/partners', { params });
    },
    showPartner(id) {
        return client.get(`/channel/partners/${id}`);
    },
    approvePartner(id) {
        return client.post(`/channel/partners/${id}/approve`);
    },
    updatePartnerLevel(id, data) {
        return client.put(`/channel/partners/${id}/level`, data);
    },
    settlements(params = {}) {
        return client.get('/channel/settlements', { params });
    },
    referralLinks(params = {}) {
        return client.get('/channel/referral-links', { params });
    },
    // 代理端
    myDashboard() {
        return client.get('/channel/my/dashboard');
    },
    myPayouts(params = {}) {
        return client.get('/channel/my/payouts', { params });
    },
    requestPayout(data) {
        return client.post('/channel/my/payouts', data);
    },
    tierBenefits() {
        return client.get('/channel/tier-benefits');
    },
};
