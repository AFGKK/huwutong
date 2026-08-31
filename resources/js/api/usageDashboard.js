import apiClient from './client';

export function getUsageOverview(params = {}) {
    return apiClient.get('/usage/overview', { params });
}

export function getUsageApiCalls(params = {}) {
    return apiClient.get('/usage/api-calls', { params });
}

export function getUsageEndpointStats(params = {}) {
    return apiClient.get('/usage/endpoint-stats', { params });
}

export function getUsageFeatures(params = {}) {
    return apiClient.get('/usage/features', { params });
}
