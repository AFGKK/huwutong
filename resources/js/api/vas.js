import apiClient from './client'

// ─── 元数据 ───
export function getVasCategories() { return apiClient.get('/admin/vas/categories') }
export function getVasBillingModes() { return apiClient.get('/admin/vas/billing-modes') }

// ─── 服务目录 ───
export function getVasServices(params) { return apiClient.get('/admin/vas/services', { params }) }
export function getVasService(id) { return apiClient.get(`/admin/vas/services/${id}`) }
export function createVasService(data) { return apiClient.post('/admin/vas/services', data) }
export function updateVasService(id, data) { return apiClient.put(`/admin/vas/services/${id}`, data) }
export function deleteVasService(id) { return apiClient.delete(`/admin/vas/services/${id}`) }

// ─── 开通管理 ───
export function getVasSubscriptions(params) { return apiClient.get('/admin/vas/subscriptions', { params }) }
export function subscribeVas(data) { return apiClient.post('/admin/vas/subscribe', data) }
export function cancelVasSubscription(id, reason) { return apiClient.post(`/admin/vas/subscriptions/${id}/cancel`, { reason }) }

// ─── 统计 ───
export function getVasStats() { return apiClient.get('/admin/vas/stats') }

// ─── 门户市场 ───
export function getVasMarketplace() { return apiClient.get('/admin/vas/marketplace') }
