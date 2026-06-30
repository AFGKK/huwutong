import client from './client';

export default {
    // 仪表盘
    dashboard() {
        return client.get('/crm/dashboard');
    },

    // 分群
    segments(params = {}) {
        return client.get('/crm/segments', { params });
    },
    createSegment(data) {
        return client.post('/crm/segments', data);
    },
    updateSegment(id, data) {
        return client.put(`/crm/segments/${id}`, data);
    },
    deleteSegment(id) {
        return client.delete(`/crm/segments/${id}`);
    },
    refreshSegment(id) {
        return client.post(`/crm/segments/${id}/refresh`);
    },
    segmentCustomers(id, params = {}) {
        return client.get(`/crm/segments/${id}/customers`, { params });
    },
    assignSegment(data) {
        return client.post('/crm/segments/assign', data);
    },
    removeSegmentCustomer(data) {
        return client.post('/crm/segments/remove', data);
    },

    // RFM
    rfmScores(params = {}) {
        return client.get('/crm/rfm-scores', { params });
    },
    recalculateRfm() {
        return client.post('/crm/rfm-scores/recalculate');
    },

    // 流失预测
    churnPredictions(params = {}) {
        return client.get('/crm/churn-predictions', { params });
    },
    recalculateChurn() {
        return client.post('/crm/churn-predictions/recalculate');
    },
};
