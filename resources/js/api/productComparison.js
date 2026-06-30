import apiClient from '@/api/client'

export function getProductSpecs(productId) {
    return apiClient.get(`/admin/comparison/products/${productId}/specs`)
}

export function createSpecGroup(productId, data) {
    return apiClient.post(`/admin/comparison/products/${productId}/spec-groups`, data)
}

export function updateSpecGroup(groupId, data) {
    return apiClient.put(`/admin/comparison/spec-groups/${groupId}`, data)
}

export function deleteSpecGroup(groupId) {
    return apiClient.delete(`/admin/comparison/spec-groups/${groupId}`)
}

export function createSpec(groupId, data) {
    return apiClient.post(`/admin/comparison/spec-groups/${groupId}/specs`, data)
}

export function updateSpec(specId, data) {
    return apiClient.put(`/admin/comparison/specs/${specId}`, data)
}

export function deleteSpec(specId) {
    return apiClient.delete(`/admin/comparison/specs/${specId}`)
}

export function setSpecValue(productId, specId, data) {
    return apiClient.post(`/admin/comparison/products/${productId}/specs/${specId}/value`, data)
}

export function getAdminSpecList(params = {}) {
    return apiClient.get('/admin/comparison/specs', { params })
}

export function compareProducts(data) {
    return apiClient.post('/compare', data)
}

export function getComparison(id) {
    return apiClient.get(`/compare/${id}`)
}
