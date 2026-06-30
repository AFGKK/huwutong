import client from './client';

/**
 * M2-132 API 用量分端点统计 API
 */
export default {
    overview() {
        return client.get('/usage/endpoint/overview');
    },
    trend(params = {}) {
        return client.get('/usage/endpoint/trend', { params });
    },
    latency(params = {}) {
        return client.get('/usage/endpoint/latency', { params });
    },
    errors(params = {}) {
        return client.get('/usage/endpoint/errors', { params });
    },
    alerts() {
        return client.get('/usage/endpoint/alerts');
    },
    dashboard() {
        return client.get('/usage/endpoint/dashboard');
    },
};
