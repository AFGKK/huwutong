import apiClient from './client';

// ─── 仪表盘 ───
export function getMarketplaceStatus() {
    return apiClient.get('/admin/marketplace/status');
}

// ─── 产品/Offer 映射 ───
export function getMarketplaceProducts(params = {}) {
    return apiClient.get('/admin/marketplace/products', { params });
}

export function createMarketplaceProduct(data) {
    return apiClient.post('/admin/marketplace/products', data);
}

export function updateMarketplaceProduct(id, data) {
    return apiClient.put(`/admin/marketplace/products/${id}`, data);
}

export function deleteMarketplaceProduct(id) {
    return apiClient.delete(`/admin/marketplace/products/${id}`);
}

// ─── 订阅管理 ───
export function getMarketplaceSubscriptions(params = {}) {
    return apiClient.get('/admin/marketplace/subscriptions', { params });
}

export function getMarketplaceSubscription(id) {
    return apiClient.get(`/admin/marketplace/subscriptions/${id}`);
}

// ─── 计量记录 ───
export function getMarketplaceMetering(params = {}) {
    return apiClient.get('/admin/marketplace/metering', { params });
}

export function reportMarketplaceMetering(data) {
    return apiClient.post('/admin/marketplace/metering/report', data);
}
