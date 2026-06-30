import client from './client';

export default {
    dashboard() {
        return client.get('/anomaly-detection/dashboard');
    },
    list(params = {}) {
        return client.get('/anomaly-detection', { params });
    },
    detect() {
        return client.post('/anomaly-detection/detect');
    },
    resolve(id, note = '') {
        return client.post(`/anomaly-detection/${id}/resolve`, { note });
    },
    rules() {
        return client.get('/anomaly-detection/rules');
    },
};
