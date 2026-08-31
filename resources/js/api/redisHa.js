import request from '@/utils/request';

export function getRedisHaStatus() {
    return request.get('/admin/redis-ha/status');
}

export function getRedisHaSentinel() {
    return request.get('/admin/redis-ha/sentinel');
}

export function getRedisHaStats() {
    return request.get('/admin/redis-ha/stats');
}

export function triggerRedisFailover() {
    return request.post('/admin/redis-ha/failover');
}

export function flushRedisCache() {
    return request.post('/admin/redis-ha/flush-cache');
}

export function resetRedisCircuitBreaker() {
    return request.post('/admin/redis-ha/reset-circuit-breaker');
}
