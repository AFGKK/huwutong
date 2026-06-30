import client from './client';

export default {
    // 仪表盘
    dashboard() {
        return client.get('/admin/queue-monitor/dashboard');
    },

    // 失败任务
    listFailedJobs(params = {}) {
        return client.get('/admin/queue-monitor/failed-jobs', { params });
    },

    // 死信队列
    listDeadLetters(params = {}) {
        return client.get('/admin/queue-monitor/dead-letters', { params });
    },
    retryDeadLetter(id) {
        return client.post(`/admin/queue-monitor/dead-letters/${id}/retry`);
    },
    batchRetryDeadLetters(ids) {
        return client.post('/admin/queue-monitor/dead-letters/batch-retry', { ids });
    },
    ignoreDeadLetter(id) {
        return client.post(`/admin/queue-monitor/dead-letters/${id}/ignore`);
    },

    // 趋势
    getTrend(params = {}) {
        return client.get('/admin/queue-monitor/trend', { params });
    },

    // 清理
    cleanup() {
        return client.post('/admin/queue-monitor/cleanup');
    },
};
