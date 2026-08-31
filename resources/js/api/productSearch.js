import apiClient from './client';

export function getProductSearchStats() {
    return apiClient.get('/admin/product-search/stats');
}

export function getProductSearchHotTerms(params = {}) {
    return apiClient.get('/admin/product-search/hot-terms', { params });
}

export function getProductSearchZeroResultTerms(params = {}) {
    return apiClient.get('/admin/product-search/zero-result-terms', { params });
}

export function getProductSearchConfig() {
    return apiClient.get('/admin/product-search/config');
}

export function getProductSearchLogs(params = {}) {
    return apiClient.get('/admin/product-search/logs', { params });
}
