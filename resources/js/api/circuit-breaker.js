import apiClient from '@/api/client';

export function getCircuitBreakerStatus() {
    return apiClient.get('/circuit-breaker/status');
}

export function resetCircuitBreaker(service = null) {
    return apiClient.post('/circuit-breaker/reset', { service });
}

export function getCircuitBreakerLogs() {
    return apiClient.get('/circuit-breaker/logs');
}
