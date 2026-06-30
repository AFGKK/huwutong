import client from './client';

export default {
    dashboard() {
        return client.get('/admin/conversion-funnel/dashboard');
    },
    getFunnelData(params = {}) {
        return client.get('/admin/conversion-funnel/data', { params });
    },
    getBySource(params = {}) {
        return client.get('/admin/conversion-funnel/by-source', { params });
    },
    getTrend(params = {}) {
        return client.get('/admin/conversion-funnel/trend', { params });
    },
    trackEvent(data) {
        return client.post('/admin/conversion-funnel/track', data);
    },
};
