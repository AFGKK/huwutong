import request from '@/utils/request';

const dbReadWrite = {
    status() {
        return request({ url: '/admin/db-read-write/status', method: 'get' });
    },
    resetCircuitBreaker() {
        return request({ url: '/admin/db-read-write/reset-circuit-breaker', method: 'post' });
    },
    healthCheck() {
        return request({ url: '/admin/db-read-write/health-check', method: 'post' });
    },
    cacheStatus() {
        return request({ url: '/admin/db-read-write/cache-status', method: 'get' });
    },
    triggerWarmup(source) {
        return request({ url: '/admin/db-read-write/trigger-warmup', method: 'post', data: { source } });
    },
};

export default dbReadWrite;
