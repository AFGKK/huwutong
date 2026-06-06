import client from './client';

export default {
    getDashboard() {
        return client.get('/health-score/dashboard');
    },
    calculate(customerId) {
        return client.post('/health-score/calculate', { customer_id: customerId });
    },
    calculateAll() {
        return client.post('/health-score/calculate-all');
    },
    show(customerId) {
        return client.get(`/health-score/customer/${customerId}`);
    },
    getTrend(customerId, limit = 30) {
        return client.get(`/health-score/customer/${customerId}/trend`, { params: { limit } });
    },
    getList(params = {}) {
        return client.get('/health-score/list', { params });
    },
    getChurnList(params = {}) {
        return client.get('/health-score/churn-list', { params });
    },
};
