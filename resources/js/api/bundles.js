import apiClient from '@/api/client'

export function getBundleList(params = {}) {
    return apiClient.get('/admin/bundles', { params })
}

export function getBundleStats() {
    return apiClient.get('/admin/bundles/stats')
}

export function getBundleDetail(id) {
    return apiClient.get(`/admin/bundles/${id}`)
}

export function createBundle(data) {
    return apiClient.post('/admin/bundles', data)
}

export function updateBundle(id, data) {
    return apiClient.put(`/admin/bundles/${id}`, data)
}

export function deleteBundle(id) {
    return apiClient.delete(`/admin/bundles/${id}`)
}

export function getAvailableItems() {
    return apiClient.get('/admin/bundles/available-items')
}

export function getBundlePurchases(params = {}) {
    return apiClient.get('/admin/bundles/purchases', { params })
}

export function purchaseBundle(id, customerId) {
    return apiClient.post(`/admin/bundles/${id}/purchase`, { customer_id: customerId })
}
