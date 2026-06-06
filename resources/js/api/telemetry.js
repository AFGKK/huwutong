import client from './client';

export default {
    // SDK 端：心跳上报
    heartbeat(data) {
        return client.post('/telemetry/heartbeat', data);
    },

    // SDK 端：事件批量上报
    reportEvents(data) {
        return client.post('/telemetry/events', data);
    },

    // 管理端：仪表盘概览
    dashboard() {
        return client.get('/telemetry/dashboard');
    },

    // 管理端：心跳历史
    heartbeats(params = {}) {
        return client.get('/telemetry/heartbeats', { params });
    },

    // 管理端：SDK 版本分布
    versions() {
        return client.get('/telemetry/versions');
    },

    // 管理端：事件统计
    events(params = {}) {
        return client.get('/telemetry/events', { params });
    },

    // 管理端：异常心跳
    unhealthy(params = {}) {
        return client.get('/telemetry/unhealthy', { params });
    },

    // 管理端：版本趋势
    trend(params = {}) {
        return client.get('/telemetry/trend', { params });
    },
};
