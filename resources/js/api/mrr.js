import apiClient from './client';

export function getMrrWaterfall(params = {}) {
    return apiClient.get('/admin/revenue/mrr-waterfall', { params });
}

export function getMrrDrilldown(params = {}) {
    return apiClient.get('/admin/revenue/mrr-drilldown', { params });
}

export function getMrrSummary(params = {}) {
    return apiClient.get('/admin/revenue/mrr-summary', { params });
}

export function getMrrBreakdownByProduct(params = {}) {
    return apiClient.get('/admin/revenue/mrr-breakdown/product', { params });
}

export function getMrrBreakdownByRegion(params = {}) {
    return apiClient.get('/admin/revenue/mrr-breakdown/region', { params });
}

export function getMrrBreakdownByCustomerSegment(params = {}) {
    return apiClient.get('/admin/revenue/mrr-breakdown/customer-segment', { params });
}

export function scanMrrChanges(params = {}) {
    return apiClient.post('/admin/revenue/mrr-scan-changes', params);
}

export default {
    getMrrWaterfall,
    getMrrDrilldown,
    getMrrSummary,
    getMrrBreakdownByProduct,
    getMrrBreakdownByRegion,
    getMrrBreakdownByCustomerSegment,
    scanMrrChanges,
};
